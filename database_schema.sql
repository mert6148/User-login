-- Database Schema for User Login/Logout System
-- Compatible with SQLite, PostgreSQL, MySQL
-- Tables: users, sessions, login_logs, user_attributes

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    salt TEXT NOT NULL,
    hash TEXT NOT NULL,
    full_name TEXT,
    email TEXT UNIQUE,
    phone TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP,
    is_active BOOLEAN DEFAULT 1,
    is_admin BOOLEAN DEFAULT 0,
    notes TEXT
);

-- User Attributes/Factors Table (user properties ve meta data)
CREATE TABLE IF NOT EXISTS user_attributes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    attribute_name TEXT NOT NULL,
    attribute_value TEXT,
    attribute_type TEXT DEFAULT 'string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, attribute_name)
);

-- Sessions Table
CREATE TABLE IF NOT EXISTS sessions (
    id TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    login_ts TIMESTAMP NOT NULL,
    logout_ts TIMESTAMP,
    system_info TEXT,
    code_dirs TEXT,
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Login Logs Table (detaylı kayıt tutuş için)
CREATE TABLE IF NOT EXISTS login_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_type TEXT NOT NULL,
    user_id INTEGER,
    username TEXT,
    full_name TEXT,
    system_info TEXT,
    code_dirs TEXT,
    ip_address TEXT,
    user_agent TEXT,
    status TEXT DEFAULT 'success',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users(created_at);
CREATE INDEX IF NOT EXISTS idx_user_attributes_user_id ON user_attributes(user_id);
CREATE INDEX IF NOT EXISTS idx_user_attributes_name ON user_attributes(attribute_name);
CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_login_ts ON sessions(login_ts);
CREATE INDEX IF NOT EXISTS idx_login_logs_timestamp ON login_logs(timestamp);
CREATE INDEX IF NOT EXISTS idx_login_logs_user_id ON login_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_login_logs_event_type ON login_logs(event_type);

-- Views for common queries
CREATE VIEW IF NOT EXISTS active_sessions AS
SELECT 
    s.id,
    u.username,
    u.full_name,
    s.login_ts,
    s.system_info,
    CASE WHEN s.logout_ts IS NULL THEN 'Aktif' ELSE 'Kapalı' END as status
FROM sessions s
JOIN users u ON s.user_id = u.id
ORDER BY s.login_ts DESC;

CREATE VIEW IF NOT EXISTS user_login_summary AS
SELECT 
    u.id,
    u.username,
    u.full_name,
    COUNT(ll.id) as total_logins,
    MAX(ll.timestamp) as last_login_time,
    u.created_at,
    u.is_active
FROM users u
LEFT JOIN login_logs ll ON u.id = ll.user_id AND ll.event_type = 'Giriş'
GROUP BY u.id, u.username, u.full_name, u.created_at, u.is_active
ORDER BY u.username;
