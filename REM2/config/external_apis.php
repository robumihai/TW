<?php
/**
 * External APIs Configuration
 * 
 * Configuration for third-party API services used for additional data layers
 * Including pollution, crime statistics, weather data, etc.
 */

return [
    // OpenWeatherMap API for weather data
    'weather' => [
        'enabled' => true,
        'api_key' => 'demo_key_replace_with_real', // Replace with actual API key
        'base_url' => 'https://api.openweathermap.org/data/2.5',
        'cache_duration' => 3600, // 1 hour
        'rate_limit' => [
            'calls_per_minute' => 60,
            'calls_per_day' => 1000
        ],
        'endpoints' => [
            'current' => '/weather',
            'forecast' => '/forecast',
            'air_pollution' => '/air_pollution'
        ]
    ],
    
    // Crime data API (using UK Police API as example - can be adapted for Romania)
    'crime' => [
        'enabled' => true,
        'api_key' => null, // UK Police API doesn't require key
        'base_url' => 'https://data.police.uk/api',
        'cache_duration' => 86400, // 24 hours
        'rate_limit' => [
            'calls_per_minute' => 15,
            'calls_per_day' => 1000
        ],
        'endpoints' => [
            'crimes' => '/crimes-street/all-crime',
            'outcomes' => '/outcomes-at-location'
        ]
    ],
    
    // Air Quality API (using OpenWeatherMap Air Pollution API)
    'pollution' => [
        'enabled' => true,
        'api_key' => 'demo_key_replace_with_real', // Same as weather API
        'base_url' => 'https://api.openweathermap.org/data/2.5',
        'cache_duration' => 3600, // 1 hour
        'rate_limit' => [
            'calls_per_minute' => 60,
            'calls_per_day' => 1000
        ],
        'endpoints' => [
            'current' => '/air_pollution',
            'forecast' => '/air_pollution/forecast',
            'history' => '/air_pollution/history'
        ]
    ],
    
    // Romanian National Statistics Institute (mock configuration)
    'demographics' => [
        'enabled' => false, // Disabled by default - requires custom implementation
        'api_key' => null,
        'base_url' => 'https://insse.ro/api', // Mock URL
        'cache_duration' => 604800, // 1 week
        'rate_limit' => [
            'calls_per_minute' => 10,
            'calls_per_day' => 100
        ]
    ],
    
    // Noise pollution data (mock service)
    'noise' => [
        'enabled' => false, // Mock service for demonstration
        'api_key' => null,
        'base_url' => 'https://api.noise-service.com',
        'cache_duration' => 7200, // 2 hours
        'rate_limit' => [
            'calls_per_minute' => 30,
            'calls_per_day' => 500
        ]
    ],
    
    // General cache settings
    'cache' => [
        'default_duration' => 3600, // 1 hour
        'max_size' => 100, // MB
        'cleanup_interval' => 3600, // 1 hour
        'storage_path' => 'api/cache/data'
    ],
    
    // Rate limiting settings
    'rate_limiting' => [
        'enabled' => true,
        'storage' => 'file', // file or database
        'cleanup_probability' => 10 // 10% chance to cleanup old entries
    ],
    
    // Geographic boundaries for Romania
    'geographic_bounds' => [
        'north' => 48.2653,
        'south' => 43.6186,
        'east' => 29.7151,
        'west' => 20.2619
    ],
    
    // Default coordinates for major Romanian cities
    'default_locations' => [
        'bucharest' => ['lat' => 44.4268, 'lon' => 26.1025],
        'cluj' => ['lat' => 46.7712, 'lon' => 23.6236],
        'timisoara' => ['lat' => 45.7489, 'lon' => 21.2087],
        'iasi' => ['lat' => 47.1585, 'lon' => 27.6014],
        'constanta' => ['lat' => 44.1598, 'lon' => 28.6348]
    ]
]; 