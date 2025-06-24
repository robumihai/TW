<?php
/**
 * REMS - Real Estate Management System
 * Database Configuration
 * 
 * Supports both SQLite (development) and MySQL (production)
 */

declare(strict_types=1);

// ==========================================================================
// Database Configuration
// ==========================================================================

return [
    // Default database connection
    'default' => $_ENV['DB_CONNECTION'] ?? 'sqlite',
    
    // Database connections
    'connections' => [
        
        // SQLite configuration (for development)
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => __DIR__ . '/../../database/real_estate.db',
            'prefix' => '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        ],
        
        // MySQL configuration (for production)
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_DATABASE'] ?? 'rems',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'",
            ],
        ],
        
        // Test database configuration
        'testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
    ],
    
    // Database settings
    'settings' => [
        // Query logging (disable in production)
        'log_queries' => $_ENV['DB_LOG_QUERIES'] ?? false,
        
        // Query cache settings
        'cache_queries' => $_ENV['DB_CACHE_QUERIES'] ?? false,
        'cache_duration' => 300, // 5 minutes
        
        // Connection pool settings
        'max_connections' => 20,
        'connection_timeout' => 30,
        
        // Transaction settings
        'transaction_isolation' => 'READ_COMMITTED',
        
        // Performance settings
        'enable_profiling' => $_ENV['DB_PROFILING'] ?? false,
        'slow_query_threshold' => 1.0, // seconds
    ],
    
    // Migration settings
    'migrations' => [
        'table' => 'migrations',
        'path' => __DIR__ . '/../../database/migrations',
    ],
    
    // Backup settings
    'backup' => [
        'path' => __DIR__ . '/../../storage/backups',
        'retain_days' => 30,
        'compress' => true,
    ],
]; 