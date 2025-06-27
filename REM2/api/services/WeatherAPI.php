<?php
/**
 * Weather API Service
 * 
 * Handles weather data retrieval from OpenWeatherMap API
 * Includes caching, rate limiting, and error handling
 */

// Try different paths for dependencies
$cachePaths = ['../cache/CacheManager.php', 'api/cache/CacheManager.php', './api/cache/CacheManager.php'];
foreach ($cachePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

$modelPaths = ['../models/LayerData.php', 'api/models/LayerData.php', './api/models/LayerData.php'];
foreach ($modelPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

class WeatherAPI {
    private $config;
    private $cache;
    private $layerData;
    private $rateLimitFile;
    
    public function __construct($config = null) {
        if ($config === null) {
            // Try different paths for config file
            $configPaths = [
                '../config/external_apis.php',
                'config/external_apis.php',
                './config/external_apis.php'
            ];
            
            foreach ($configPaths as $path) {
                if (file_exists($path)) {
                    $config = require_once $path;
                    break;
                }
            }
            
            if ($config === null) {
                throw new Exception('Configuration file not found');
            }
        }
        
        $this->config = $config;
        $this->cache = new CacheManager($this->config);
        $this->layerData = new LayerData();
        $this->rateLimitFile = 'api/cache/weather_rate_limit.json';
    }
    
    /**
     * Get current weather for coordinates
     */
    public function getCurrentWeather($lat, $lon, $units = 'metric') {
        try {
            if (!$this->isServiceEnabled()) {
                return $this->createErrorResponse('Weather service is disabled');
            }
            
            // Check rate limits
            if (!$this->checkRateLimit()) {
                return $this->createErrorResponse('Rate limit exceeded');
            }
            
            // Check cache first
            $cacheParams = ['lat' => $lat, 'lon' => $lon, 'units' => $units];
            $cachedData = $this->cache->get('weather', 'current', $cacheParams);
            
            if ($cachedData !== null) {
                return [
                    'success' => true,
                    'data' => $cachedData,
                    'source' => 'cache'
                ];
            }
            
            // Build API URL
            $apiKey = $this->config['weather']['api_key'];
            $baseUrl = $this->config['weather']['base_url'];
            $endpoint = $this->config['weather']['endpoints']['current'];
            
            $url = $baseUrl . $endpoint . '?' . http_build_query([
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $apiKey,
                'units' => $units
            ]);
            
            // Make API request
            $response = $this->makeRequest($url);
            
            if (!$response['success']) {
                return $response;
            }
            
            // Standardize and store data
            $standardizedData = $this->layerData->standardizeWeatherData($response['data']);
            
            // Cache the data
            $cacheDuration = $this->config['weather']['cache_duration'];
            $this->cache->set('weather', 'current', $cacheParams, $standardizedData, $cacheDuration);
            
            // Store in database
            $expiresAt = date('Y-m-d H:i:s', time() + $cacheDuration);
            $this->layerData->store(
                LayerData::LAYER_WEATHER,
                'openweathermap',
                $lat,
                $lon,
                $standardizedData,
                1000,
                $expiresAt
            );
            
            return [
                'success' => true,
                'data' => $standardizedData,
                'source' => 'api'
            ];
            
        } catch (Exception $e) {
            return $this->createErrorResponse('Weather API error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get weather forecast for coordinates
     */
    public function getForecast($lat, $lon, $units = 'metric', $days = 5) {
        try {
            if (!$this->isServiceEnabled()) {
                return $this->createErrorResponse('Weather service is disabled');
            }
            
            if (!$this->checkRateLimit()) {
                return $this->createErrorResponse('Rate limit exceeded');
            }
            
            // Check cache first
            $cacheParams = ['lat' => $lat, 'lon' => $lon, 'units' => $units, 'days' => $days];
            $cachedData = $this->cache->get('weather', 'forecast', $cacheParams);
            
            if ($cachedData !== null) {
                return [
                    'success' => true,
                    'data' => $cachedData,
                    'source' => 'cache'
                ];
            }
            
            // Build API URL
            $apiKey = $this->config['weather']['api_key'];
            $baseUrl = $this->config['weather']['base_url'];
            $endpoint = $this->config['weather']['endpoints']['forecast'];
            
            $url = $baseUrl . $endpoint . '?' . http_build_query([
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $apiKey,
                'units' => $units,
                'cnt' => $days * 8 // 8 forecasts per day (3-hour intervals)
            ]);
            
            // Make API request
            $response = $this->makeRequest($url);
            
            if (!$response['success']) {
                return $response;
            }
            
            // Process forecast data
            $forecastData = $this->processForecastData($response['data']);
            
            // Cache the data
            $cacheDuration = $this->config['weather']['cache_duration'];
            $this->cache->set('weather', 'forecast', $cacheParams, $forecastData, $cacheDuration);
            
            return [
                'success' => true,
                'data' => $forecastData,
                'source' => 'api'
            ];
            
        } catch (Exception $e) {
            return $this->createErrorResponse('Weather forecast API error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get weather data for multiple locations
     */
    public function getBulkWeather($locations, $units = 'metric') {
        $results = [];
        
        foreach ($locations as $location) {
            $lat = $location['lat'];
            $lon = $location['lon'];
            $name = $location['name'] ?? "{$lat},{$lon}";
            
            $weatherData = $this->getCurrentWeather($lat, $lon, $units);
            $results[$name] = $weatherData;
            
            // Add small delay to respect rate limits
            usleep(100000); // 0.1 second
        }
        
        return $results;
    }
    
    /**
     * Get weather statistics for an area
     */
    public function getAreaWeatherStats($bounds, $timeframe = '24h') {
        try {
            // Get cached data from database
            $layerStats = $this->layerData->getAreaStats(LayerData::LAYER_WEATHER, $bounds, $timeframe);
            
            if (!$layerStats) {
                return $this->createErrorResponse('No weather data available for this area');
            }
            
            return [
                'success' => true,
                'data' => [
                    'area_bounds' => $bounds,
                    'timeframe' => $timeframe,
                    'statistics' => $layerStats,
                    'data_quality' => $layerStats['quality_score'] ?? 0
                ],
                'source' => 'database'
            ];
            
        } catch (Exception $e) {
            return $this->createErrorResponse('Weather stats error: ' . $e->getMessage());
        }
    }
    
    /**
     * Make HTTP request to weather API
     */
    private function makeRequest($url) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'REMS Real Estate Platform/1.0'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return $this->createErrorResponse('Failed to fetch weather data');
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createErrorResponse('Invalid JSON response from weather API');
            }
            
            // Check for API errors
            if (isset($data['cod']) && $data['cod'] !== 200) {
                $message = $data['message'] ?? 'Weather API error';
                return $this->createErrorResponse($message);
            }
            
            // Update rate limit tracking
            $this->updateRateLimit();
            
            return [
                'success' => true,
                'data' => $data
            ];
            
        } catch (Exception $e) {
            return $this->createErrorResponse('Weather API request error: ' . $e->getMessage());
        }
    }
    
    /**
     * Process forecast data into daily summaries
     */
    private function processForecastData($forecastData) {
        $processed = [
            'type' => 'weather_forecast',
            'source' => 'openweathermap',
            'timestamp' => time(),
            'daily_forecasts' => []
        ];
        
        $dailyData = [];
        
        foreach ($forecastData['list'] as $forecast) {
            $date = date('Y-m-d', $forecast['dt']);
            
            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [
                    'date' => $date,
                    'temperatures' => [],
                    'conditions' => [],
                    'humidity' => [],
                    'wind_speeds' => []
                ];
            }
            
            $dailyData[$date]['temperatures'][] = $forecast['main']['temp'];
            $dailyData[$date]['conditions'][] = $forecast['weather'][0]['main'];
            $dailyData[$date]['humidity'][] = $forecast['main']['humidity'];
            $dailyData[$date]['wind_speeds'][] = $forecast['wind']['speed'] ?? 0;
        }
        
        // Calculate daily summaries
        foreach ($dailyData as $date => $data) {
            $processed['daily_forecasts'][] = [
                'date' => $date,
                'temperature' => [
                    'min' => min($data['temperatures']),
                    'max' => max($data['temperatures']),
                    'avg' => round(array_sum($data['temperatures']) / count($data['temperatures']), 1)
                ],
                'condition' => $this->getMostFrequent($data['conditions']),
                'humidity_avg' => round(array_sum($data['humidity']) / count($data['humidity'])),
                'wind_speed_avg' => round(array_sum($data['wind_speeds']) / count($data['wind_speeds']), 1)
            ];
        }
        
        return $processed;
    }
    
    /**
     * Check if weather service is enabled
     */
    private function isServiceEnabled() {
        return $this->config['weather']['enabled'] ?? false;
    }
    
    /**
     * Check rate limits
     */
    private function checkRateLimit() {
        if (!$this->config['rate_limiting']['enabled']) {
            return true;
        }
        
        $rateLimitData = $this->getRateLimitData();
        $currentTime = time();
        $currentMinute = floor($currentTime / 60);
        
        // Clean old entries
        $rateLimitData = array_filter($rateLimitData, function($timestamp) use ($currentTime) {
            return $currentTime - $timestamp < 86400; // Keep last 24 hours
        });
        
        // Count requests in current minute
        $requestsThisMinute = count(array_filter($rateLimitData, function($timestamp) use ($currentMinute) {
            return floor($timestamp / 60) === $currentMinute;
        }));
        
        $maxPerMinute = $this->config['weather']['rate_limit']['calls_per_minute'];
        
        return $requestsThisMinute < $maxPerMinute;
    }
    
    /**
     * Update rate limit tracking
     */
    private function updateRateLimit() {
        if (!$this->config['rate_limiting']['enabled']) {
            return;
        }
        
        $rateLimitData = $this->getRateLimitData();
        $rateLimitData[] = time();
        
        // Keep only last 24 hours
        $dayAgo = time() - 86400;
        $rateLimitData = array_filter($rateLimitData, function($timestamp) use ($dayAgo) {
            return $timestamp > $dayAgo;
        });
        
        $this->saveRateLimitData($rateLimitData);
    }
    
    /**
     * Get rate limit data from file
     */
    private function getRateLimitData() {
        if (!file_exists($this->rateLimitFile)) {
            return [];
        }
        
        $data = @file_get_contents($this->rateLimitFile);
        if ($data === false) {
            return [];
        }
        
        return json_decode($data, true) ?: [];
    }
    
    /**
     * Save rate limit data to file
     */
    private function saveRateLimitData($data) {
        $dir = dirname($this->rateLimitFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        @file_put_contents($this->rateLimitFile, json_encode($data));
    }
    
    /**
     * Get most frequent value from array
     */
    private function getMostFrequent($array) {
        if (empty($array)) {
            return null;
        }
        
        $counts = array_count_values($array);
        arsort($counts);
        
        return array_key_first($counts);
    }
    
    /**
     * Create error response
     */
    private function createErrorResponse($message) {
        return [
            'success' => false,
            'error' => $message,
            'data' => null
        ];
    }
} 