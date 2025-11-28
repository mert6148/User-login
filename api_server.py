"""
Flask REST API for User Login System
Provides endpoints for user management, authentication, and attributes
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import json
import sys
from pathlib import Path

# Add current directory to path so we can import print module
sys.path.insert(0, str(Path(__file__).parent))

try:
    import print as login_system
except ImportError:
    print("Error: Could not import print module. Make sure print.py is in the same directory.")
    sys.exit(1)

app = Flask(__name__)
CORS(app)

# Initialize database and stores
login_system.init_db()
login_system.load_user_store()
login_system.load_sessions()

# Error handler
@app.errorhandler(400)
def bad_request(error):
    return jsonify({"error": "Bad Request", "message": str(error)}), 400

@app.errorhandler(401)
def unauthorized(error):
    return jsonify({"error": "Unauthorized", "message": "Invalid credentials"}), 401

@app.errorhandler(404)
def not_found(error):
    return jsonify({"error": "Not Found", "message": str(error)}), 404

@app.errorhandler(500)
def internal_error(error):
    return jsonify({"error": "Internal Server Error", "message": str(error)}), 500

# ============================================================================
# USER MANAGEMENT ENDPOINTS
# ============================================================================

@app.route("/api/v1/users", methods=["GET"])
def get_users():
    """Get all users (admin only)"""
    try:
        users = login_system.list_users()
        return jsonify({"success": True, "users": users}), 200
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

@app.route("/api/v1/users", methods=["POST"])
def create_user():
    """Create a new user"""
    data = request.get_json()
    
    if not data or "username" not in data or "password" not in data:
        return jsonify({"success": False, "error": "Missing username or password"}), 400
    
    username = data.get("username")
    password = data.get("password")
    full_name = data.get("full_name", None)
    
    try:
        ok = login_system.create_user(username, password, full_name)
        if ok:
            return jsonify({"success": True, "message": "User created"}), 201
        else:
            return jsonify({"success": False, "error": "User already exists"}), 409
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

@app.route("/api/v1/users/<username>", methods=["DELETE"])
def delete_user(username):
    """Delete a user"""
    try:
        ok = login_system.delete_user(username)
        if ok:
            return jsonify({"success": True, "message": "User deleted"}), 200
        else:
            return jsonify({"success": False, "error": "User not found"}), 404
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

# ============================================================================
# AUTHENTICATION ENDPOINTS
# ============================================================================

@app.route("/api/v1/auth/login", methods=["POST"])
def api_login():
    """Login endpoint"""
    data = request.get_json()
    
    if not data or "username" not in data or "password" not in data:
        return jsonify({"success": False, "error": "Missing username or password"}), 400
    
    username = data.get("username")
    password = data.get("password")
    
    try:
        ok = login_system.login_command(username, password, prompt_if_missing=False)
        if ok:
            return jsonify({
                "success": True,
                "message": "Login successful",
                "username": username,
                "session_id": login_system.CURRENT_SESSION_ID
            }), 200
        else:
            return jsonify({"success": False, "error": "Invalid credentials"}), 401
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

@app.route("/api/v1/auth/logout", methods=["POST"])
def api_logout():
    """Logout endpoint"""
    data = request.get_json()
    username = data.get("username") if data else None
    
    try:
        login_system.logout_command(username)
        return jsonify({"success": True, "message": "Logout successful"}), 200
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

# ============================================================================
# USER ATTRIBUTES ENDPOINTS
# ============================================================================

@app.route("/api/v1/users/<username>/attributes", methods=["GET"])
def get_user_attributes(username):
    """Get all attributes for a user"""
    try:
        attrs = login_system.get_user_attributes(username)
        return jsonify({"success": True, "username": username, "attributes": attrs}), 200
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

@app.route("/api/v1/users/<username>/attributes/<attribute_name>", methods=["GET"])
def get_user_attribute(username, attribute_name):
    """Get a specific attribute for a user"""
    try:
        value = login_system.get_user_attribute(username, attribute_name)
        if value is not None:
            return jsonify({
                "success": True,
                "username": username,
                "attribute_name": attribute_name,
                "value": value
            }), 200
        else:
            return jsonify({"success": False, "error": "Attribute not found"}), 404
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

@app.route("/api/v1/users/<username>/attributes", methods=["POST"])
def set_user_attribute(username):
    """Set a user attribute"""
    data = request.get_json()
    
    if not data or "attribute_name" not in data or "attribute_value" not in data:
        return jsonify({"success": False, "error": "Missing attribute_name or attribute_value"}), 400
    
    attribute_name = data.get("attribute_name")
    attribute_value = data.get("attribute_value")
    attribute_type = data.get("attribute_type", "string")
    
    try:
        ok = login_system.set_user_attribute(username, attribute_name, attribute_value, attribute_type)
        if ok:
            return jsonify({
                "success": True,
                "message": "Attribute set",
                "username": username,
                "attribute_name": attribute_name,
                "attribute_value": attribute_value
            }), 201
        else:
            return jsonify({"success": False, "error": "User not found"}), 404
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

@app.route("/api/v1/users/<username>/attributes/<attribute_name>", methods=["DELETE"])
def delete_user_attribute(username, attribute_name):
    """Delete a user attribute"""
    try:
        ok = login_system.delete_user_attribute(username, attribute_name)
        if ok:
            return jsonify({"success": True, "message": "Attribute deleted"}), 200
        else:
            return jsonify({"success": False, "error": "Attribute not found"}), 404
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

# ============================================================================
# SESSION ENDPOINTS
# ============================================================================

@app.route("/api/v1/sessions", methods=["GET"])
def get_sessions():
    """Get all sessions"""
    try:
        return jsonify({
            "success": True,
            "sessions": login_system.SESSIONS
        }), 200
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

@app.route("/api/v1/sessions", methods=["POST"])
def create_session():
    """Create a new session"""
    data = request.get_json()
    username = data.get("username") if data else None
    
    if not username:
        return jsonify({"success": False, "error": "Username required"}), 400
    
    try:
        system_info = login_system.gather_system_info()
        code_dirs = login_system.list_code_directories()
        session_id = login_system.start_session(username, system_info, code_dirs)
        return jsonify({
            "success": True,
            "session_id": session_id,
            "username": username
        }), 201
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

@app.route("/api/v1/sessions/<session_id>", methods=["POST"])
def end_session(session_id):
    """End a session"""
    try:
        ok = login_system.end_session(session_id)
        if ok:
            return jsonify({"success": True, "message": "Session ended"}), 200
        else:
            return jsonify({"success": False, "error": "Session not found"}), 404
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

# ============================================================================
# LOG ENDPOINTS
# ============================================================================

@app.route("/api/v1/logs", methods=["GET"])
def get_logs():
    """Get login logs (JSON-lines parsed)"""
    try:
        logs = []
        if Path(login_system.LOG_FILE).exists():
            with open(login_system.LOG_FILE, "r", encoding="utf-8") as f:
                for line in f:
                    line = line.strip()
                    if line:
                        try:
                            logs.append(json.loads(line))
                        except Exception:
                            pass
        return jsonify({"success": True, "logs": logs}), 200
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 500

# ============================================================================
# HEALTH CHECK
# ============================================================================

@app.route("/api/v1/health", methods=["GET"])
def health_check():
    """Health check endpoint"""
    return jsonify({
        "success": True,
        "status": "ok",
        "version": "1.0.0"
    }), 200

# ============================================================================
# ROOT ENDPOINT
# ============================================================================

@app.route("/", methods=["GET"])
def root():
    """API documentation"""
    return jsonify({
        "name": "User Login System API",
        "version": "1.0.0",
        "endpoints": {
            "health": "/api/v1/health",
            "users": "/api/v1/users",
            "auth": {
                "login": "POST /api/v1/auth/login",
                "logout": "POST /api/v1/auth/logout"
            },
            "attributes": {
                "get_all": "GET /api/v1/users/<username>/attributes",
                "get_one": "GET /api/v1/users/<username>/attributes/<attribute_name>",
                "set": "POST /api/v1/users/<username>/attributes",
                "delete": "DELETE /api/v1/users/<username>/attributes/<attribute_name>"
            },
            "sessions": "/api/v1/sessions",
            "logs": "/api/v1/logs"
        }
    }), 200

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)