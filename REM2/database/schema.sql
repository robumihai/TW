-- REMS - Real Estate Management System
-- Database Schema for SQLite/MySQL
-- Version: 1.0

-- ==========================================================================
-- Database Configuration
-- ==========================================================================

-- Enable foreign key constraints for SQLite
PRAGMA foreign_keys = ON;

-- ==========================================================================
-- Users Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role TEXT CHECK(role IN ('admin', 'agent', 'user')) DEFAULT 'user',
    status TEXT CHECK(status IN ('active', 'inactive', 'pending', 'suspended')) DEFAULT 'pending',
    avatar VARCHAR(255),
    email_verified_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================================================
-- Properties Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS properties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    short_description VARCHAR(500),
    
    -- Property details
    property_type TEXT CHECK(property_type IN ('apartment', 'house', 'studio', 'villa', 'duplex', 'penthouse', 'office', 'commercial', 'industrial', 'land', 'garage', 'warehouse')) NOT NULL,
    transaction_type TEXT CHECK(transaction_type IN ('sale', 'rent')) NOT NULL,
    
    -- Pricing
    price DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'RON',
    price_per_sqm DECIMAL(10,2),
    
    -- Location
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    county VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Romania',
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    
    -- Property specifications
    surface_total DECIMAL(8,2),
    surface_useful DECIMAL(8,2),
    rooms INTEGER,
    bedrooms INTEGER,
    bathrooms INTEGER,
    floor INTEGER,
    total_floors INTEGER,
    construction_year INTEGER,
    condition_type TEXT CHECK(condition_type IN ('new', 'excellent', 'very_good', 'good', 'renovated', 'needs_renovation', 'undeveloped')),
    heating_type VARCHAR(50),
    parking_spaces INTEGER DEFAULT 0,
    
    -- Additional data
    utilities TEXT, -- JSON array
    amenities TEXT, -- JSON array
    
    -- Property management
    status TEXT CHECK(status IN ('draft', 'active', 'inactive', 'sold', 'rented', 'expired')) DEFAULT 'draft',
    featured BOOLEAN DEFAULT 0,
    priority INTEGER DEFAULT 0,
    view_count INTEGER DEFAULT 0,
    
    -- SEO and metadata
    meta_title VARCHAR(255),
    meta_description VARCHAR(500),
    meta_keywords VARCHAR(255),
    
    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    published_at DATETIME,
    expires_at DATETIME,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ==========================================================================
-- Property Images Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS property_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    alt_text VARCHAR(255),
    caption VARCHAR(500),
    is_primary BOOLEAN DEFAULT 0,
    sort_order INTEGER DEFAULT 0,
    file_size INTEGER,
    mime_type VARCHAR(50),
    width INTEGER,
    height INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- ==========================================================================
-- Favorites Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS favorites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    property_id INTEGER NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(user_id, property_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

-- ==========================================================================
-- Property Views Table (for analytics)
-- ==========================================================================

CREATE TABLE IF NOT EXISTS property_views (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    user_id INTEGER,
    ip_address VARCHAR(45),
    user_agent TEXT,
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ==========================================================================
-- Search History Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS search_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    query_text VARCHAR(255),
    filters TEXT, -- JSON
    results_count INTEGER,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ==========================================================================
-- Sessions Table (for secure session management)
-- ==========================================================================

CREATE TABLE IF NOT EXISTS user_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ==========================================================================
-- API Rate Limiting Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier VARCHAR(255) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    attempts INTEGER DEFAULT 1,
    reset_time DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(identifier, endpoint)
);

-- ==========================================================================
-- Activity Logs Table (for security and audit)
-- ==========================================================================

CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INTEGER,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details TEXT, -- JSON
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ==========================================================================
-- Configuration Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS site_config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    config_type TEXT CHECK(config_type IN ('string', 'integer', 'boolean', 'json')) DEFAULT 'string',
    is_public BOOLEAN DEFAULT 0,
    description VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================================================
-- Insert Default Data
-- ==========================================================================

-- Default admin user (password: admin123 - CHANGE IN PRODUCTION!)
INSERT OR REPLACE INTO users (
    id, name, email, password, role, status, email_verified_at, created_at, updated_at
) VALUES (
    1,
    'Administrator',
    'admin@rems.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'admin',
    'active',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
);

-- Default site configuration
INSERT OR REPLACE INTO site_config (config_key, config_value, config_type, is_public, description) VALUES
('site_name', 'REMS - Real Estate Management System', 'string', 1, 'Site name'),
('site_description', 'Professional real estate management platform', 'string', 1, 'Site description'),
('contact_email', 'contact@rems.local', 'string', 1, 'Contact email'),
('contact_phone', '+40 123 456 789', 'string', 1, 'Contact phone'),
('properties_per_page', '20', 'integer', 1, 'Properties per page'),
('max_image_size', '5242880', 'integer', 0, 'Maximum image size in bytes'),
('allowed_image_types', '["jpg","jpeg","png","webp"]', 'json', 0, 'Allowed image types'),
('enable_registration', '1', 'boolean', 1, 'Enable user registration'),
('require_email_verification', '1', 'boolean', 0, 'Require email verification'),
('default_currency', 'RON', 'string', 1, 'Default currency'),
('google_maps_api_key', '', 'string', 0, 'Google Maps API key'),
('smtp_host', '', 'string', 0, 'SMTP host'),
('smtp_port', '587', 'integer', 0, 'SMTP port'),
('smtp_username', '', 'string', 0, 'SMTP username'),
('smtp_password', '', 'string', 0, 'SMTP password');

-- ==========================================================================
-- Database Functions and Triggers
-- ==========================================================================

-- Trigger to update updated_at timestamp on users table
CREATE TRIGGER IF NOT EXISTS update_users_timestamp 
    AFTER UPDATE ON users
    BEGIN
        UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

-- Trigger to update updated_at timestamp on properties table
CREATE TRIGGER IF NOT EXISTS update_properties_timestamp 
    AFTER UPDATE ON properties
    BEGIN
        UPDATE properties SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
    END;

-- Trigger to update favorites_count on properties table
CREATE TRIGGER IF NOT EXISTS update_favorites_count_insert
    AFTER INSERT ON favorites
    BEGIN
        UPDATE properties 
        SET favorites_count = (
            SELECT COUNT(*) FROM favorites WHERE property_id = NEW.property_id
        ) 
        WHERE id = NEW.property_id;
    END;

CREATE TRIGGER IF NOT EXISTS update_favorites_count_delete
    AFTER DELETE ON favorites
    BEGIN
        UPDATE properties 
        SET favorites_count = (
            SELECT COUNT(*) FROM favorites WHERE property_id = OLD.property_id
        ) 
        WHERE id = OLD.property_id;
    END;

-- Trigger to clean up expired sessions
CREATE TRIGGER IF NOT EXISTS cleanup_expired_sessions
    AFTER INSERT ON user_sessions
    BEGIN
        DELETE FROM user_sessions WHERE expires_at < CURRENT_TIMESTAMP;
    END;

-- ==========================================================================
-- Views for Common Queries
-- ==========================================================================

-- View for property listings with user info
CREATE VIEW IF NOT EXISTS property_listings AS
SELECT 
    p.*,
    u.username,
    u.first_name,
    u.last_name,
    u.email as user_email,
    u.phone as user_phone,
    (SELECT filename FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
    (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as images_count
FROM properties p
LEFT JOIN users u ON p.user_id = u.id
WHERE p.status = 'active'
AND p.published_at <= CURRENT_TIMESTAMP
AND (p.expires_at IS NULL OR p.expires_at > CURRENT_TIMESTAMP);

-- View for user statistics
CREATE VIEW IF NOT EXISTS user_stats AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.role,
    u.status,
    u.created_at,
    COUNT(p.id) as properties_count,
    COUNT(CASE WHEN p.status = 'active' THEN 1 END) as active_properties,
    COUNT(CASE WHEN p.status = 'sold' THEN 1 END) as sold_properties,
    COUNT(CASE WHEN p.status = 'rented' THEN 1 END) as rented_properties
FROM users u
LEFT JOIN properties p ON u.id = p.user_id
GROUP BY u.id;

-- ==========================================================================
-- Indexes for Full-Text Search (if supported)
-- ==========================================================================

-- Note: SQLite FTS5 extension would be used for better search performance
-- This would be implemented in the PHP layer for compatibility

COMMIT; 