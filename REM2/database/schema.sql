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
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('admin', 'agent', 'user') DEFAULT 'user',
    status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    password_reset_token VARCHAR(255),
    password_reset_expires DATETIME,
    login_attempts INTEGER DEFAULT 0,
    locked_until DATETIME,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_users_email (email),
    INDEX idx_users_username (username),
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
);

-- ==========================================================================
-- Properties Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS properties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    
    -- Property details
    property_type ENUM('apartament', 'casa', 'teren', 'comercial', 'birou', 'garsoniera') NOT NULL,
    transaction_type ENUM('vanzare', 'inchiriere') NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'RON',
    
    -- Location
    address VARCHAR(500) NOT NULL,
    city VARCHAR(100) NOT NULL,
    county VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    
    -- Property specifications
    surface_total DECIMAL(8,2), -- Total surface in sqm
    surface_useful DECIMAL(8,2), -- Useful surface in sqm
    rooms INTEGER,
    bedrooms INTEGER,
    bathrooms INTEGER,
    floor INTEGER,
    total_floors INTEGER,
    construction_year INTEGER,
    
    -- Property condition and features
    condition_type ENUM('nou', 'foarte_bun', 'bun', 'satisfacator', 'renovare') DEFAULT 'bun',
    heating_type ENUM('centrala_proprie', 'centrala_bloc', 'termoficare', 'soba', 'aer_conditionat', 'altele'),
    parking BOOLEAN DEFAULT FALSE,
    balcony BOOLEAN DEFAULT FALSE,
    terrace BOOLEAN DEFAULT FALSE,
    garden BOOLEAN DEFAULT FALSE,
    basement BOOLEAN DEFAULT FALSE,
    attic BOOLEAN DEFAULT FALSE,
    
    -- Utilities and amenities
    utilities JSON, -- JSON array: ["gaz", "apa", "canalizare", "electricitate", "internet", "cablu"]
    amenities JSON, -- JSON array: ["lift", "interfon", "alarma", "clima", "mobilat", "utilat"]
    
    -- Energy efficiency
    energy_class ENUM('A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G'),
    
    -- Additional info
    contact_name VARCHAR(100),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(255),
    
    -- Status and moderation
    status ENUM('draft', 'active', 'inactive', 'sold', 'rented', 'expired') DEFAULT 'draft',
    featured BOOLEAN DEFAULT FALSE,
    views_count INTEGER DEFAULT 0,
    favorites_count INTEGER DEFAULT 0,
    
    -- SEO and metadata
    slug VARCHAR(300) UNIQUE,
    meta_title VARCHAR(255),
    meta_description TEXT,
    
    -- Timestamps
    published_at DATETIME,
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes for performance
    INDEX idx_properties_user_id (user_id),
    INDEX idx_properties_type (property_type),
    INDEX idx_properties_transaction (transaction_type),
    INDEX idx_properties_city (city),
    INDEX idx_properties_status (status),
    INDEX idx_properties_price (price),
    INDEX idx_properties_location (latitude, longitude),
    INDEX idx_properties_published (published_at),
    INDEX idx_properties_slug (slug)
);

-- ==========================================================================
-- Property Images Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS property_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    mime_type VARCHAR(100),
    file_size INTEGER,
    width INTEGER,
    height INTEGER,
    alt_text VARCHAR(255),
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_property_images_property_id (property_id),
    INDEX idx_property_images_primary (is_primary),
    INDEX idx_property_images_order (sort_order)
);

-- ==========================================================================
-- Favorites Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS favorites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    property_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    
    -- Unique constraint to prevent duplicates
    UNIQUE(user_id, property_id),
    
    -- Indexes
    INDEX idx_favorites_user_id (user_id),
    INDEX idx_favorites_property_id (property_id)
);

-- ==========================================================================
-- Property Views Table (for analytics)
-- ==========================================================================

CREATE TABLE IF NOT EXISTS property_views (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    user_id INTEGER, -- NULL for anonymous views
    ip_address VARCHAR(45),
    user_agent TEXT,
    referer VARCHAR(500),
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_property_views_property_id (property_id),
    INDEX idx_property_views_user_id (user_id),
    INDEX idx_property_views_date (viewed_at),
    INDEX idx_property_views_ip (ip_address)
);

-- ==========================================================================
-- Search History Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS search_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER, -- NULL for anonymous searches
    search_query VARCHAR(500),
    filters JSON, -- JSON object with search filters
    results_count INTEGER,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_search_history_user_id (user_id),
    INDEX idx_search_history_date (created_at),
    INDEX idx_search_history_query (search_query)
);

-- ==========================================================================
-- Sessions Table (for secure session management)
-- ==========================================================================

CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INTEGER NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    
    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_user_sessions_user_id (user_id),
    INDEX idx_user_sessions_last_activity (last_activity),
    INDEX idx_user_sessions_expires (expires_at)
);

-- ==========================================================================
-- API Rate Limiting Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier VARCHAR(255) NOT NULL, -- IP address or user ID
    endpoint VARCHAR(255) NOT NULL,
    requests_count INTEGER DEFAULT 1,
    window_start DATETIME DEFAULT CURRENT_TIMESTAMP,
    reset_at DATETIME NOT NULL,
    
    -- Unique constraint
    UNIQUE(identifier, endpoint),
    
    -- Indexes
    INDEX idx_rate_limits_identifier (identifier),
    INDEX idx_rate_limits_reset (reset_at)
);

-- ==========================================================================
-- Activity Logs Table (for security and audit)
-- ==========================================================================

CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id INTEGER,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_activity_logs_user_id (user_id),
    INDEX idx_activity_logs_action (action),
    INDEX idx_activity_logs_date (created_at),
    INDEX idx_activity_logs_resource (resource_type, resource_id)
);

-- ==========================================================================
-- Configuration Table
-- ==========================================================================

CREATE TABLE IF NOT EXISTS site_config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    config_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_site_config_key (config_key),
    INDEX idx_site_config_public (is_public)
);

-- ==========================================================================
-- Insert Default Data
-- ==========================================================================

-- Default admin user (password: admin123 - CHANGE IN PRODUCTION!)
INSERT OR IGNORE INTO users (
    username, email, password_hash, first_name, last_name, 
    role, status, email_verified
) VALUES (
    'admin', 
    'admin@rems.local', 
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- bcrypt hash of 'admin123'
    'Administrator', 
    'System', 
    'admin', 
    'active', 
    TRUE
);

-- Default site configuration
INSERT OR IGNORE INTO site_config (config_key, config_value, config_type, description, is_public) VALUES
('site_name', 'REMS - Real Estate Management System', 'string', 'Site name', TRUE),
('site_description', 'Modern real estate management platform', 'string', 'Site description', TRUE),
('contact_email', 'contact@rems.local', 'string', 'Contact email', TRUE),
('contact_phone', '+40123456789', 'string', 'Contact phone', TRUE),
('max_upload_size', '10485760', 'integer', 'Maximum file upload size in bytes (10MB)', FALSE),
('max_images_per_property', '20', 'integer', 'Maximum images per property', FALSE),
('session_lifetime', '7200', 'integer', 'Session lifetime in seconds (2 hours)', FALSE),
('rate_limit_requests', '100', 'integer', 'Rate limit requests per window', FALSE),
('rate_limit_window', '3600', 'integer', 'Rate limit window in seconds (1 hour)', FALSE),
('email_verification_required', 'true', 'boolean', 'Require email verification for new users', FALSE),
('maintenance_mode', 'false', 'boolean', 'Enable maintenance mode', FALSE);

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