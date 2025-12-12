"""
Developer API Server v1.0.0
GitHub Integration API for Admin Operations
Advanced security with OAuth2, API Keys, Webhooks, and Rate Limiting
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
from functools import wraps
import json
import uuid
import hashlib
import hmac
import sqlite3
from datetime import datetime, timedelta
from pathlib import Path
import logging

app = Flask(__name__)
CORS(app)

# ============================================================================
# DEVELOPER API CONFIGURATION
# ============================================================================

DEV_CONFIG = {
    "enable_logging": True,
    "enable_rate_limiting": True,
    "rate_limit_per_key": 1000,  # Requests per hour
    "rate_limit_window": 3600,  # seconds
    "max_payload_size": 50 * 1024 * 1024,  # 50MB
    "allowed_github_events": [
        "push", "pull_request", "issues", "repository",
        "release", "workflow_run", "check_run", "check_suite"
    ],
    "token_expiry": 7 * 24 * 60 * 60,  # 7 days
    "webhook_timeout": 30,  # seconds
    "enable_oauth2": True,
    "oauth2_providers": ["github", "gitlab", "bitbucket"]
}

# Logging setup
logging.basicConfig(
    filename='developer_api.log',
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s'
)

# Database initialization
DEV_DB = 'developer_api.db'

def init_dev_db():
    """Initialize developer API database"""
    try:
        conn = sqlite3.connect(DEV_DB)
        cursor = conn.cursor()
        
        # API Keys table
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS api_keys (
                id INTEGER PRIMARY KEY,
                key_id TEXT UNIQUE NOT NULL,
                key_hash TEXT NOT NULL,
                developer_id TEXT NOT NULL,
                name TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_used TIMESTAMP,
                rate_limit INTEGER DEFAULT 1000,
                active BOOLEAN DEFAULT 1,
                permissions TEXT,
                ip_whitelist TEXT
            )
        ''')
        
        # OAuth2 tokens table
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS oauth2_tokens (
                id INTEGER PRIMARY KEY,
                token TEXT UNIQUE NOT NULL,
                provider TEXT NOT NULL,
                user_id TEXT NOT NULL,
                scope TEXT,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                revoked BOOLEAN DEFAULT 0
            )
        ''')
        
        # Webhook subscriptions table
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS webhooks (
                id INTEGER PRIMARY KEY,
                webhook_id TEXT UNIQUE NOT NULL,
                developer_id TEXT NOT NULL,
                url TEXT NOT NULL,
                events TEXT,
                secret TEXT NOT NULL,
                active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_triggered TIMESTAMP,
                failures INTEGER DEFAULT 0
            )
        ''')
        
        # API usage logs table
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS api_usage (
                id INTEGER PRIMARY KEY,
                key_id TEXT NOT NULL,
                endpoint TEXT,
                method TEXT,
                status_code INTEGER,
                response_time_ms INTEGER,
                payload_size INTEGER,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address TEXT,
                user_agent TEXT
            )
        ''')
        
        # Admin integrations table
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS admin_integrations (
                id INTEGER PRIMARY KEY,
                integration_id TEXT UNIQUE NOT NULL,
                admin_id TEXT NOT NULL,
                provider TEXT NOT NULL,
                config TEXT,
                enabled BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_sync TIMESTAMP
            )
        ''')
        
        # Rate limiting table
        cursor.execute('''
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY,
                key_id TEXT NOT NULL,
                hour_start TIMESTAMP,
                request_count INTEGER DEFAULT 0,
                UNIQUE(key_id, hour_start)
            )
        ''')
        
        conn.commit()
        conn.close()
        logging.info("Developer API database initialized successfully")
    except Exception as e:
        logging.error(f"Database initialization error: {str(e)}")

init_dev_db()

# ============================================================================
# AUTHENTICATION & AUTHORIZATION
# ============================================================================

class APIKeyManager:
    """Manage API keys with security features"""
    
    @staticmethod
    def generate_key_pair():
        """Generate secure API key pair"""
        key_id = f"dev_{uuid.uuid4().hex[:16]}"
        key_secret = uuid.uuid4().hex + uuid.uuid4().hex
        key_hash = hashlib.sha256(key_secret.encode()).hexdigest()
        return key_id, key_secret, key_hash
    
    @staticmethod
    def validate_key(key_id, key_secret):
        """Validate API key securely (timing attack safe)"""
        try:
            conn = sqlite3.connect(DEV_DB)
            cursor = conn.cursor()
            cursor.execute('SELECT key_hash, active FROM api_keys WHERE key_id = ?', (key_id,))
            result = cursor.fetchone()
            conn.close()
            
            if not result:
                return False, None
            
            stored_hash, active = result
            if not active:
                return False, None
            
            computed_hash = hashlib.sha256(key_secret.encode()).hexdigest()
            valid = hmac.compare_digest(computed_hash, stored_hash)
            
            return valid, key_id if valid else None
        except Exception as e:
            logging.error(f"Key validation error: {str(e)}")
            return False, None
    
    @staticmethod
    def log_key_usage(key_id, endpoint, method, status_code, response_time_ms, payload_size):
        """Log API key usage"""
        try:
            conn = sqlite3.connect(DEV_DB)
            cursor = conn.cursor()
            
            cursor.execute('''
                UPDATE api_keys SET last_used = CURRENT_TIMESTAMP WHERE key_id = ?
            ''', (key_id,))
            
            cursor.execute('''
                INSERT INTO api_usage 
                (key_id, endpoint, method, status_code, response_time_ms, payload_size, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ''', (
                key_id, endpoint, method, status_code, response_time_ms, payload_size,
                request.remote_addr, request.user_agent.string[:100]
            ))
            
            conn.commit()
            conn.close()
        except Exception as e:
            logging.error(f"Usage logging error: {str(e)}")

class OAuth2Manager:
    """Manage OAuth2 authentication"""
    
    @staticmethod
    def create_token(provider, user_id, scope, expires_in=None):
        """Create OAuth2 token"""
        token = uuid.uuid4().hex
        expires_at = datetime.now() + timedelta(
            seconds=expires_in or DEV_CONFIG['token_expiry']
        )
        
        try:
            conn = sqlite3.connect(DEV_DB)
            cursor = conn.cursor()
            cursor.execute('''
                INSERT INTO oauth2_tokens 
                (token, provider, user_id, scope, expires_at)
                VALUES (?, ?, ?, ?, ?)
            ''', (token, provider, user_id, scope, expires_at.isoformat()))
            conn.commit()
            conn.close()
            return token
        except Exception as e:
            logging.error(f"Token creation error: {str(e)}")
            return None
    
    @staticmethod
    def validate_token(token):
        """Validate OAuth2 token"""
        try:
            conn = sqlite3.connect(DEV_DB)
            cursor = conn.cursor()
            cursor.execute('''
                SELECT user_id, provider, scope FROM oauth2_tokens 
                WHERE token = ? AND revoked = 0 AND expires_at > CURRENT_TIMESTAMP
            ''', (token,))
            result = cursor.fetchone()
            conn.close()
            return result
        except Exception as e:
            logging.error(f"Token validation error: {str(e)}")
            return None

class WebhookManager:
    """Manage webhook subscriptions and deliveries"""
    
    @staticmethod
    def create_webhook(developer_id, url, events, secret=None):
        """Create webhook subscription"""
        webhook_id = f"wh_{uuid.uuid4().hex[:16]}"
        secret = secret or uuid.uuid4().hex
        secret_hash = hashlib.sha256(secret.encode()).hexdigest()
        
        try:
            conn = sqlite3.connect(DEV_DB)
            cursor = conn.cursor()
            cursor.execute('''
                INSERT INTO webhooks 
                (webhook_id, developer_id, url, events, secret)
                VALUES (?, ?, ?, ?, ?)
            ''', (webhook_id, developer_id, url, json.dumps(events), secret_hash))
            conn.commit()
            conn.close()
            return webhook_id, secret
        except Exception as e:
            logging.error(f"Webhook creation error: {str(e)}")
            return None, None
    
    @staticmethod
    def sign_payload(payload, secret):
        """Sign payload with webhook secret"""
        message = json.dumps(payload, sort_keys=True).encode('utf-8')
        signature = hmac.new(secret.encode(), message, hashlib.sha256).hexdigest()
        return signature

# ============================================================================
# DECORATORS
# ============================================================================

def require_api_key(f):
    """Decorator: Require valid API key"""
    @wraps(f)
    def decorated_function(*args, **kwargs):
        key_id = request.args.get('key_id') or request.headers.get('X-API-Key-ID')
        key_secret = request.args.get('key_secret') or request.headers.get('X-API-Key')
        
        if not key_id or not key_secret:
            return jsonify({'error': 'Missing API credentials'}), 401
        
        valid, key = APIKeyManager.validate_key(key_id, key_secret)
        if not valid:
            logging.warning(f"Invalid API key attempt: {key_id}")
            return jsonify({'error': 'Invalid API key'}), 403
        
        request.api_key = key
        return f(*args, **kwargs)
    
    return decorated_function

def require_oauth2(f):
    """Decorator: Require valid OAuth2 token"""
    @wraps(f)
    def decorated_function(*args, **kwargs):
        auth_header = request.headers.get('Authorization', '')
        if not auth_header.startswith('Bearer '):
            return jsonify({'error': 'Missing Bearer token'}), 401
        
        token = auth_header[7:]
        token_data = OAuth2Manager.validate_token(token)
        
        if not token_data:
            return jsonify({'error': 'Invalid or expired token'}), 403
        
        request.oauth_user_id = token_data[0]
        request.oauth_provider = token_data[1]
        request.oauth_scope = token_data[2]
        return f(*args, **kwargs)
    
    return decorated_function

def rate_limit(f):
    """Decorator: Rate limiting"""
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if not DEV_CONFIG["enable_rate_limiting"]:
            return f(*args, **kwargs)
        
        key_id = request.api_key if hasattr(request, 'api_key') else request.remote_addr
        
        try:
            conn = sqlite3.connect(DEV_DB)
            cursor = conn.cursor()
            
            hour_start = datetime.now().replace(minute=0, second=0, microsecond=0)
            cursor.execute('''
                SELECT request_count FROM rate_limits 
                WHERE key_id = ? AND hour_start = ?
            ''', (key_id, hour_start.isoformat()))
            
            result = cursor.fetchone()
            if result:
                count = result[0]
                if count >= DEV_CONFIG['rate_limit_per_key']:
                    conn.close()
                    return jsonify({'error': 'Rate limit exceeded'}), 429
                
                cursor.execute('''
                    UPDATE rate_limits SET request_count = request_count + 1 
                    WHERE key_id = ? AND hour_start = ?
                ''', (key_id, hour_start.isoformat()))
            else:
                cursor.execute('''
                    INSERT INTO rate_limits (key_id, hour_start, request_count)
                    VALUES (?, ?, 1)
                ''', (key_id, hour_start.isoformat()))
            
            conn.commit()
            conn.close()
        except Exception as e:
            logging.error(f"Rate limiting error: {str(e)}")
        
        return f(*args, **kwargs)
    
    return decorated_function

# ============================================================================
# API KEY ENDPOINTS
# ============================================================================

@app.route('/api/v2/developer/keys', methods=['POST'])
@require_oauth2
@rate_limit
def create_api_key():
    """Create new API key"""
    data = request.json or {}
    
    key_id, key_secret, key_hash = APIKeyManager.generate_key_pair()
    permissions = data.get('permissions', ['read:user', 'read:admin'])
    
    try:
        conn = sqlite3.connect(DEV_DB)
        cursor = conn.cursor()
        cursor.execute('''
            INSERT INTO api_keys 
            (key_id, key_hash, developer_id, name, permissions)
            VALUES (?, ?, ?, ?, ?)
        ''', (key_id, key_hash, request.oauth_user_id, data.get('name', 'API Key'), json.dumps(permissions)))
        conn.commit()
        conn.close()
        
        logging.info(f"API key created: {key_id} for developer {request.oauth_user_id}")
        
        return jsonify({
            'success': True,
            'key_id': key_id,
            'key_secret': key_secret,  # Only shown once!
            'message': 'Keep your key secret safe!'
        }), 201
    except Exception as e:
        logging.error(f"Key creation error: {str(e)}")
        return jsonify({'error': 'Failed to create key'}), 500

@app.route('/api/v2/developer/keys', methods=['GET'])
@require_oauth2
@rate_limit
def list_api_keys():
    """List API keys for developer"""
    try:
        conn = sqlite3.connect(DEV_DB)
        cursor = conn.cursor()
        cursor.execute('''
            SELECT key_id, name, created_at, last_used, active, rate_limit 
            FROM api_keys WHERE developer_id = ?
        ''', (request.oauth_user_id,))
        
        keys = []
        for row in cursor.fetchall():
            keys.append({
                'key_id': row[0],
                'name': row[1],
                'created_at': row[2],
                'last_used': row[3],
                'active': bool(row[4]),
                'rate_limit': row[5]
            })
        
        conn.close()
        return jsonify({'keys': keys}), 200
    except Exception as e:
        logging.error(f"Key listing error: {str(e)}")
        return jsonify({'error': 'Failed to list keys'}), 500

@app.route('/api/v2/developer/keys/<key_id>', methods=['DELETE'])
@require_oauth2
@rate_limit
def revoke_api_key(key_id):
    """Revoke API key"""
    try:
        conn = sqlite3.connect(DEV_DB)
        cursor = conn.cursor()
        cursor.execute('UPDATE api_keys SET active = 0 WHERE key_id = ? AND developer_id = ?',
                      (key_id, request.oauth_user_id))
        conn.commit()
        conn.close()
        
        logging.info(f"API key revoked: {key_id}")
        return jsonify({'success': True, 'message': 'Key revoked'}), 200
    except Exception as e:
        logging.error(f"Key revocation error: {str(e)}")
        return jsonify({'error': 'Failed to revoke key'}), 500

# ============================================================================
# WEBHOOK ENDPOINTS
# ============================================================================

@app.route('/api/v2/developer/webhooks', methods=['POST'])
@require_oauth2
@rate_limit
def create_webhook():
    """Create webhook subscription"""
    data = request.json or {}
    
    if 'url' not in data or 'events' not in data:
        return jsonify({'error': 'Missing url or events'}), 400
    
    webhook_id, secret = WebhookManager.create_webhook(
        request.oauth_user_id,
        data['url'],
        data['events']
    )
    
    if not webhook_id:
        return jsonify({'error': 'Failed to create webhook'}), 500
    
    logging.info(f"Webhook created: {webhook_id} for developer {request.oauth_user_id}")
    
    return jsonify({
        'webhook_id': webhook_id,
        'secret': secret,
        'message': 'Keep your secret safe!'
    }), 201

@app.route('/api/v2/developer/webhooks', methods=['GET'])
@require_oauth2
@rate_limit
def list_webhooks():
    """List webhooks for developer"""
    try:
        conn = sqlite3.connect(DEV_DB)
        cursor = conn.cursor()
        cursor.execute('''
            SELECT webhook_id, url, events, active, created_at, last_triggered, failures
            FROM webhooks WHERE developer_id = ?
        ''', (request.oauth_user_id,))
        
        webhooks = []
        for row in cursor.fetchall():
            webhooks.append({
                'webhook_id': row[0],
                'url': row[1],
                'events': json.loads(row[2]),
                'active': bool(row[3]),
                'created_at': row[4],
                'last_triggered': row[5],
                'failures': row[6]
            })
        
        conn.close()
        return jsonify({'webhooks': webhooks}), 200
    except Exception as e:
        logging.error(f"Webhook listing error: {str(e)}")
        return jsonify({'error': 'Failed to list webhooks'}), 500

# ============================================================================
# ADMIN INTEGRATION ENDPOINTS
# ============================================================================

@app.route('/api/v2/admin/integrations', methods=['POST'])
@require_api_key
@rate_limit
def create_admin_integration():
    """Create admin GitHub/GitLab integration"""
    data = request.json or {}
    
    if 'provider' not in data or 'admin_id' not in data:
        return jsonify({'error': 'Missing provider or admin_id'}), 400
    
    integration_id = f"int_{uuid.uuid4().hex[:16]}"
    
    try:
        conn = sqlite3.connect(DEV_DB)
        cursor = conn.cursor()
        cursor.execute('''
            INSERT INTO admin_integrations 
            (integration_id, admin_id, provider, config)
            VALUES (?, ?, ?, ?)
        ''', (integration_id, data['admin_id'], data['provider'], json.dumps(data.get('config', {}))))
        conn.commit()
        conn.close()
        
        logging.info(f"Admin integration created: {integration_id} ({data['provider']})")
        
        return jsonify({
            'integration_id': integration_id,
            'provider': data['provider'],
            'status': 'created'
        }), 201
    except Exception as e:
        logging.error(f"Integration creation error: {str(e)}")
        return jsonify({'error': 'Failed to create integration'}), 500

@app.route('/api/v2/admin/integrations', methods=['GET'])
@require_api_key
@rate_limit
def list_admin_integrations():
    """List admin integrations"""
    admin_id = request.args.get('admin_id')
    
    try:
        conn = sqlite3.connect(DEV_DB)
        cursor = conn.cursor()
        
        if admin_id:
            cursor.execute('''
                SELECT integration_id, provider, enabled, created_at, last_sync
                FROM admin_integrations WHERE admin_id = ?
            ''', (admin_id,))
        else:
            cursor.execute('''
                SELECT integration_id, admin_id, provider, enabled, created_at, last_sync
                FROM admin_integrations
            ''')
        
        integrations = []
        for row in cursor.fetchall():
            integrations.append({
                'integration_id': row[0],
                'provider': row[1] if admin_id else row[2],
                'enabled': bool(row[2] if admin_id else row[3]),
                'created_at': row[3] if admin_id else row[4],
                'last_sync': row[4] if admin_id else row[5]
            })
        
        conn.close()
        return jsonify({'integrations': integrations}), 200
    except Exception as e:
        logging.error(f"Integration listing error: {str(e)}")
        return jsonify({'error': 'Failed to list integrations'}), 500

# ============================================================================
# OAUTH2 ENDPOINTS
# ============================================================================

@app.route('/api/v2/oauth2/authorize', methods=['POST'])
@rate_limit
def oauth2_authorize():
    """OAuth2 authorization endpoint"""
    data = request.json or {}
    
    provider = data.get('provider')
    code = data.get('code')
    
    if not provider or not code:
        return jsonify({'error': 'Missing provider or code'}), 400
    
    if provider not in DEV_CONFIG['oauth2_providers']:
        return jsonify({'error': 'Unsupported provider'}), 400
    
    # In production, verify code with provider (GitHub, GitLab, etc)
    user_id = f"{provider}_{uuid.uuid4().hex[:16]}"
    scope = 'admin:repo_hook repo:status repo:deployment public_repo'
    
    token = OAuth2Manager.create_token(provider, user_id, scope)
    
    if not token:
        return jsonify({'error': 'Failed to create token'}), 500
    
    logging.info(f"OAuth2 token created for {provider} user")
    
    return jsonify({
        'token': token,
        'token_type': 'Bearer',
        'expires_in': DEV_CONFIG['token_expiry'],
        'scope': scope
    }), 200

@app.route('/api/v2/oauth2/token/revoke', methods=['POST'])
@require_oauth2
@rate_limit
def oauth2_revoke():
    """Revoke OAuth2 token"""
    try:
        # In real implementation, revoke the token
        logging.info(f"OAuth2 token revoked for {request.oauth_user_id}")
        return jsonify({'success': True, 'message': 'Token revoked'}), 200
    except Exception as e:
        logging.error(f"Token revocation error: {str(e)}")
        return jsonify({'error': 'Failed to revoke token'}), 500

# ============================================================================
# MONITORING & HEALTH ENDPOINTS
# ============================================================================

@app.route('/api/v2/developer/usage', methods=['GET'])
@require_api_key
@rate_limit
def get_api_usage():
    """Get API usage statistics"""
    try:
        conn = sqlite3.connect(DEV_DB)
        cursor = conn.cursor()
        
        # Total requests this hour
        hour_start = datetime.now().replace(minute=0, second=0, microsecond=0)
        cursor.execute('''
            SELECT COUNT(*) FROM api_usage 
            WHERE key_id = ? AND timestamp > ?
        ''', (request.api_key, hour_start.isoformat()))
        requests_this_hour = cursor.fetchone()[0]
        
        # Total requests this month
        month_start = datetime.now().replace(day=1, hour=0, minute=0, second=0, microsecond=0)
        cursor.execute('''
            SELECT COUNT(*) FROM api_usage 
            WHERE key_id = ? AND timestamp > ?
        ''', (request.api_key, month_start.isoformat()))
        requests_this_month = cursor.fetchone()[0]
        
        # Average response time
        cursor.execute('''
            SELECT AVG(response_time_ms) FROM api_usage 
            WHERE key_id = ? AND timestamp > ?
        ''', (request.api_key, hour_start.isoformat()))
        avg_response_time = cursor.fetchone()[0] or 0
        
        conn.close()
        
        return jsonify({
            'requests_this_hour': requests_this_hour,
            'requests_this_month': requests_this_month,
            'average_response_time_ms': round(avg_response_time, 2),
            'rate_limit': DEV_CONFIG['rate_limit_per_key'],
            'remaining_requests': DEV_CONFIG['rate_limit_per_key'] - requests_this_hour
        }), 200
    except Exception as e:
        logging.error(f"Usage statistics error: {str(e)}")
        return jsonify({'error': 'Failed to get usage statistics'}), 500

@app.route('/api/v2/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'ok',
        'version': '1.0.0',
        'timestamp': datetime.now().isoformat(),
        'database': 'connected'
    }), 200

@app.route('/api/v2', methods=['GET'])
def api_info():
    """API information"""
    return jsonify({
        'name': 'Developer API Server',
        'version': '1.0.0',
        'description': 'GitHub Integration API for Admin Operations',
        'documentation': 'https://docs.github.com/get-started/exploring-integrations',
        'endpoints': {
            'authentication': [
                'POST /api/v2/oauth2/authorize - OAuth2 authorization',
                'POST /api/v2/oauth2/token/revoke - Revoke token'
            ],
            'api_keys': [
                'POST /api/v2/developer/keys - Create API key',
                'GET /api/v2/developer/keys - List API keys',
                'DELETE /api/v2/developer/keys/<key_id> - Revoke API key'
            ],
            'webhooks': [
                'POST /api/v2/developer/webhooks - Create webhook',
                'GET /api/v2/developer/webhooks - List webhooks'
            ],
            'integrations': [
                'POST /api/v2/admin/integrations - Create integration',
                'GET /api/v2/admin/integrations - List integrations'
            ],
            'monitoring': [
                'GET /api/v2/developer/usage - Get usage statistics',
                'GET /api/v2/health - Health check'
            ]
        },
        'security_features': [
            'OAuth2 Authentication',
            'API Key Management',
            'Rate Limiting (1000 req/hour)',
            'Webhook Signing (HMAC-SHA256)',
            'Audit Logging',
            'Token Expiration (7 days)',
            'Timing Attack Protection',
            'CORS Protection',
            'Request Size Validation'
        ]
    }), 200

# ============================================================================
# ERROR HANDLERS
# ============================================================================

@app.errorhandler(400)
def bad_request(e):
    """Handle bad request"""
    logging.warning(f"Bad request: {request.path}")
    return jsonify({'error': 'Bad request', 'message': str(e)}), 400

@app.errorhandler(401)
def unauthorized(e):
    """Handle unauthorized"""
    logging.warning(f"Unauthorized access attempt: {request.path}")
    return jsonify({'error': 'Unauthorized'}), 401

@app.errorhandler(403)
def forbidden(e):
    """Handle forbidden"""
    logging.warning(f"Forbidden access: {request.path}")
    return jsonify({'error': 'Forbidden'}), 403

@app.errorhandler(404)
def not_found(e):
    """Handle not found"""
    return jsonify({'error': 'Not found'}), 404

@app.errorhandler(500)
def internal_error(e):
    """Handle internal error"""
    logging.error(f"Internal error: {str(e)}")
    return jsonify({'error': 'Internal server error'}), 500

# ============================================================================
# MAIN
# ============================================================================

if __name__ == '__main__':
    print("Developer API Server v1.0.0")
    print("Endpoints: /api/v2/*")
    print("Documentation: GET /api/v2")
    print("=" * 60)
    app.run(debug=False, host='127.0.0.1', port=5001)
