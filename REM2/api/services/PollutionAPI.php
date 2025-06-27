<?php
/**
 * Air Pollution API Service
 * 
 * Handles air quality data retrieval from OpenWeatherMap Air Pollution API
 * Includes caching, rate limiting, and data standardization
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

class PollutionAPI {
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
        $this->rateLimitFile = 'api/cache/pollution_rate_limit.json';
    }
    
    /**
     * Get current air pollution data for coordinates
     */
    public function getCurrentPollution($lat, $lon) {
        try {
            if (!$this->isServiceEnabled()) {
                return $this->createErrorResponse('Pollution service is disabled');
            }
            
            // Check rate limits
            if (!$this->checkRateLimit()) {
                return $this->createErrorResponse('Rate limit exceeded');
            }
            
            // Check cache first
            $cacheParams = ['lat' => $lat, 'lon' => $lon];
            $cachedData = $this->cache->get('pollution', 'current', $cacheParams);
            
            if ($cachedData !== null) {
                return [
                    'success' => true,
                    'data' => $cachedData,
                    'source' => 'cache'
                ];
            }
            
            // Build API URL
            $apiKey = $this->config['pollution']['api_key'];
            $baseUrl = $this->config['pollution']['base_url'];
            $endpoint = $this->config['pollution']['endpoints']['current'];
            
            $url = $baseUrl . $endpoint . '?' . http_build_query([
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $apiKey
            ]);
            
            // Make API request
            $response = $this->makeRequest($url);
            
            if (!$response['success']) {
                return $response;
            }
            
            // Standardize data
            $standardizedData = $this->layerData->standardizePollutionData($response['data']);
            
            // Add additional analysis
            $standardizedData['analysis'] = $this->analyzePollutionData($standardizedData['data']);
            
            // Cache the data
            $cacheDuration = $this->config['pollution']['cache_duration'];
            $this->cache->set('pollution', 'current', $cacheParams, $standardizedData, $cacheDuration);
            
            // Store in database
            $expiresAt = date('Y-m-d H:i:s', time() + $cacheDuration);
            $this->layerData->store(
                LayerData::LAYER_POLLUTION,
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
            return $this->createErrorResponse('Pollution API error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get air pollution forecast for coordinates
     */
    public function getPollutionForecast($lat, $lon, $hours = 24) {
        try {
            if (!$this->isServiceEnabled()) {
                return $this->createErrorResponse('Pollution service is disabled');
            }
            
            if (!$this->checkRateLimit()) {
                return $this->createErrorResponse('Rate limit exceeded');
            }
            
            // Check cache first
            $cacheParams = ['lat' => $lat, 'lon' => $lon, 'hours' => $hours];
            $cachedData = $this->cache->get('pollution', 'forecast', $cacheParams);
            
            if ($cachedData !== null) {
                return [
                    'success' => true,
                    'data' => $cachedData,
                    'source' => 'cache'
                ];
            }
            
            // Build API URL
            $apiKey = $this->config['pollution']['api_key'];
            $baseUrl = $this->config['pollution']['base_url'];
            $endpoint = $this->config['pollution']['endpoints']['forecast'];
            
            $url = $baseUrl . $endpoint . '?' . http_build_query([
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $apiKey
            ]);
            
            // Make API request
            $response = $this->makeRequest($url);
            
            if (!$response['success']) {
                return $response;
            }
            
            // Process forecast data
            $forecastData = $this->processPollutionForecast($response['data'], $hours);
            
            // Cache the data
            $cacheDuration = $this->config['pollution']['cache_duration'];
            $this->cache->set('pollution', 'forecast', $cacheParams, $forecastData, $cacheDuration);
            
            return [
                'success' => true,
                'data' => $forecastData,
                'source' => 'api'
            ];
            
        } catch (Exception $e) {
            return $this->createErrorResponse('Pollution forecast API error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get air pollution data for multiple locations
     */
    public function getBulkPollution($locations) {
        $results = [];
        
        foreach ($locations as $location) {
            $lat = $location['lat'];
            $lon = $location['lon'];
            $name = $location['name'] ?? "{$lat},{$lon}";
            
            $pollutionData = $this->getCurrentPollution($lat, $lon);
            $results[$name] = $pollutionData;
            
            // Add small delay to respect rate limits
            usleep(150000); // 0.15 second
        }
        
        return $results;
    }
    
    /**
     * Get pollution statistics for an area
     */
    public function getAreaPollutionStats($bounds, $timeframe = '24h') {
        try {
            // Get cached data from database
            $layerStats = $this->layerData->getAreaStats(LayerData::LAYER_POLLUTION, $bounds, $timeframe);
            
            if (!$layerStats) {
                return $this->createErrorResponse('No pollution data available for this area');
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
            return $this->createErrorResponse('Pollution stats error: ' . $e->getMessage());
        }
    }
    
    /**
     * Analyze pollution data and provide insights
     */
    private function analyzePollutionData($pollutionData) {
        $analysis = [
            'overall_quality' => 'unknown',
            'health_recommendations' => [],
            'main_pollutants' => [],
            'severity_score' => 0
        ];
        
        if (!isset($pollutionData['aqi']['value'])) {
            return $analysis;
        }
        
        $aqi = $pollutionData['aqi']['value'];
        $analysis['overall_quality'] = $pollutionData['aqi']['level'];
        
        // Calculate severity score (0-100)
        $analysis['severity_score'] = ($aqi - 1) * 25; // Convert 1-5 scale to 0-100
        
        // Health recommendations based on AQI
        switch ($aqi) {
            case 1: // Good
                $analysis['health_recommendations'][] = 'Air quality is good. Perfect for outdoor activities.';
                break;
            case 2: // Fair
                $analysis['health_recommendations'][] = 'Air quality is acceptable. Outdoor activities are generally safe.';
                break;
            case 3: // Moderate
                $analysis['health_recommendations'][] = 'Sensitive individuals should consider limiting outdoor activities.';
                break;
            case 4: // Poor
                $analysis['health_recommendations'][] = 'Everyone should limit outdoor activities, especially children and elderly.';
                break;
            case 5: // Very Poor
                $analysis['health_recommendations'][] = 'Avoid outdoor activities. Stay indoors if possible.';
                break;
        }
        
        // Identify main pollutants
        $pollutants = [
            'pm2_5' => $pollutionData['pm2_5']['value'] ?? 0,
            'pm10' => $pollutionData['pm10']['value'] ?? 0,
            'no2' => $pollutionData['no2']['value'] ?? 0,
            'o3' => $pollutionData['o3']['value'] ?? 0,
            'co' => $pollutionData['co']['value'] ?? 0,
            'so2' => $pollutionData['so2']['value'] ?? 0
        ];
        
        arsort($pollutants);
        $analysis['main_pollutants'] = array_keys(array_slice($pollutants, 0, 3, true));
        
        return $analysis;
    }
    
    /**
     * Process pollution forecast data
     */
    private function processPollutionForecast($forecastData, $hours) {
        $processed = [
            'type' => 'pollution_forecast',
            'source' => 'openweathermap',
            'timestamp' => time(),
            'forecast_hours' => $hours,
            'hourly_forecasts' => []
        ];
        
        $forecastList = array_slice($forecastData['list'], 0, $hours);
        
        foreach ($forecastList as $forecast) {
            $standardized = $this->layerData->standardizePollutionData(['list' => [$forecast]]);
            $processed['hourly_forecasts'][] = [
                'datetime' => date('Y-m-d H:i:s', $forecast['dt']),
                'timestamp' => $forecast['dt'],
                'aqi' => $standardized['data']['aqi'],
                'main_pollutant' => $this->getMainPollutant($forecast['components']),
                'components' => $forecast['components']
            ];
        }
        
        return $processed;
    }
    
    /**
     * Get the main pollutant from components
     */
    private function getMainPollutant($components) {
        $weights = [
            'pm2_5' => 2.0,  // Higher weight for more dangerous pollutants
            'pm10' => 1.5,
            'no2' => 1.0,
            'o3' => 1.0,
            'co' => 0.5,
            'so2' => 0.8
        ];
        
        $weightedValues = [];
        foreach ($components as $pollutant => $value) {
            $weight = $weights[$pollutant] ?? 1.0;
            $weightedValues[$pollutant] = $value * $weight;
        }
        
        arsort($weightedValues);
        return array_key_first($weightedValues);
    }
    
    /**
     * Make HTTP request to pollution API
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
                return $this->createErrorResponse('Failed to fetch pollution data');
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createErrorResponse('Invalid JSON response from pollution API');
            }
            
            // Update rate limit tracking
            $this->updateRateLimit();
            
            return [
                'success' => true,
                'data' => $data
            ];
            
        } catch (Exception $e) {
            return $this->createErrorResponse('Pollution API request error: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if pollution service is enabled
     */
    private function isServiceEnabled() {
        return $this->config['pollution']['enabled'] ?? false;
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
        
        $maxPerMinute = $this->config['pollution']['rate_limit']['calls_per_minute'];
        
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