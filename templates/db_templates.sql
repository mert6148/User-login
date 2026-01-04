-- Database shema for templates system
-- This is a simple database schema for a templates system.
-- It contains tables for templates, template values, factors, factor values, and products.
-- The tables are created if they don't exist.
-- Combatible with SQLite and MySQL databases.
-- Tables: template, templatevalues, templateproducts, factory, factoryvalues, products, productsbalues.
-- id: integer primary key autoincrement
-- name: text not null
-- description: text
-- created_at: timestamp default current_timestamp
-- updated_at: timestamp default current_timestamp
-- template_id: integer not null
-- factor_id: integer not null
-- value: text not null
-- factor_id: integer not null
-- value: text not null
-- name: text not null
-- description: text
-- created_at: timestamp default current_timestamp
-- updated_at: timestamp default current_timestamp

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

-- Template Table
CREATE TABLE IF NOT EXISTS Templates
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- TemplateValues Table
CREATE TABLE IF NOT EXISTS TemplateValues
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER NOT NULL,
    factor_id INTEGER NOT NULL,
    value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES Templates(id),
    FOREIGN KEY (factor_id) REFERENCES Factors(id)
);

-- TemplateProducts Table
CREATE TABLE IF NOT EXISTS TemplateProducts
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES Templates(id),
    FOREIGN KEY (product_id) REFERENCES Products(id)
);

-- Factory Table 
CREATE TABLE IF EXITS Factors
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- FactoryValues Table
CREATE TABLE IF NOT EXISTS FactorValues
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    factor_id INTEGER NOT NULL,
    value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (factor_id) REFERENCES Factors(id)
);

-- Product Table
CREATE TABLE IF NOT EXISTS Products
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ProductValues Table
CREATE TABLE IF NOT EXISTS ProductValues
(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    factor_id INTEGER NOT NULL,
    value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES Products(id),
    FOREIGN KEY (factor_id) REFERENCES Factors(id)
);

-- Create a trigger to update the updated_at column when a row is updated
CREATE TRIGGER IF NOT EXISTS update_updated_at
AFTER UPDATE ON Templates
BEGIN
    UPDATE Templates SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

-- Create a trigger to update the updated_at column when a row is updated
CREATE TRIGGER IF NOT EXISTS update_updated_at