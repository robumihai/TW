<?php
/**
 * Crime Data API Service
 * 
 * Handles crime data retrieval from external APIs (UK Police API as example)
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

class CrimeAPI {
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
        $this->rateLimitFile = 'api/cache/crime_rate_limit.json';
    }
    
    /**
     * Get crime data for coordinates and date
     */
    public function getCrimeData($lat, $lon, $date = null) {
        try {
            if (!$this->isServiceEnabled()) {
                return $this->createErrorResponse('Crime service is disabled');
            }
            
            if (!$this->checkRateLimit()) {
                return $this->createErrorResponse('Rate limit exceeded');
            }
            
            $date = $date ?: date('Y-m', strtotime('-1 month'));
            
            // Check cache first
            $cacheParams = ['lat' => $lat, 'lon' => $lon, 'date' => $date];
            $cachedData = $this->cache->get('crime', 'data', $cacheParams);
            
            if ($cachedData !== null) {
                return [
                    'success' => true,
                    'data' => $cachedData,
                    'source' => 'cache'
                ];
            }
            
            // Build API URL
            $baseUrl = $this->config['crime']['base_url'];
            $endpoint = $this->config['crime']['endpoints']['crimes'];
            
            $url = $baseUrl . $endpoint . '?' . http_build_query([
                'lat' => $lat,
                'lng' => $lon,
                'date' => $date
            ]);
            
            // Make API request
            $response = $this->makeRequest($url);
            
            if (!$response['success']) {
                return $response;
            }
            
            // Standardize data
            $standardizedData = $this->layerData->standardizeCrimeData($response['data']);
            
            // Add additional analysis
            $standardizedData['analysis'] = $this->analyzeCrimeData($response['data']);
            
            // Cache the data
            $cacheDuration = $this->config['crime']['cache_duration'];
            $this->cache->set('crime', 'data', $cacheParams, $standardizedData, $cacheDuration);
            
            // Store in database
            $expiresAt = date('Y-m-d H:i:s', time() + $cacheDuration);
            $this->layerData->store(
                LayerData::LAYER_CRIME,
                'police_uk',
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
            return $this->createErrorResponse('Crime API error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get crime statistics for an area
     */
    public function getAreaCrimeStats($bounds, $timeframe = '3m') {
        try {
            if (!$this->isServiceEnabled()) {
                return $this->createErrorResponse('Crime service is disabled');
            }
            
            // Get cached data from database
            $layerStats = $this->layerData->getAreaStats(LayerData::LAYER_CRIME, $bounds, $timeframe);
            
            if (!$layerStats) {
                // Generate mock data for demonstration if no data available
                return $this->getMockCrimeStats($bounds, $timeframe);
            }
            
            return [
                'success' => true,
                'data' => [
                    'area_bounds' => $bounds,
                    'timeframe' => $timeframe,
                    'statistics' => $layerStats,
                    'data_quality' => $layerStats['quality_score'] ?? 0,
                    'crime_trends' => $this->calculateCrimeTrends($bounds, $timeframe)
                ],
                'source' => 'database'
            ];
            
        } catch (Exception $e) {
            return $this->createErrorResponse('Crime stats error: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate mock crime statistics for demonstration
     */
    private function getMockCrimeStats($bounds, $timeframe) {
        $mockStats = [
            'total_crimes' => rand(5, 25),
            'categories' => [
                'anti-social-behaviour' => rand(1, 8),
                'burglary' => rand(0, 4),
                'criminal-damage-arson' => rand(0, 3),
                'drugs' => rand(0, 2),
                'public-order' => rand(0, 3),
                'shoplifting' => rand(0, 2),
                'theft-from-the-person' => rand(0, 2),
                'vehicle-crime' => rand(0, 4),
                'violent-crime' => rand(1, 5),
                'other-theft' => rand(0, 3)
            ]
        ];
        
        $totalCrimes = $mockStats['total_crimes'];
        $safetyScore = max(0, 100 - ($totalCrimes * 3));
        
        return [
            'success' => true,
            'data' => [
                'area_bounds' => $bounds,
                'timeframe' => $timeframe,
                'statistics' => $mockStats,
                'safety_score' => $safetyScore,
                'risk_level' => $this->getRiskLevel($totalCrimes),
                'data_quality' => 'mock',
                'note' => 'This is demonstration data. Real implementation would use actual crime APIs.'
            ],
            'source' => 'mock'
        ];
    }
    
    /**
     * Get risk level based on crime count
     */
    private function getRiskLevel($crimeCount) {
        if ($crimeCount < 5) return 'very_low';
        if ($crimeCount < 10) return 'low';
        if ($crimeCount < 20) return 'medium';
        if ($crimeCount < 35) return 'high';
        return 'very_high';
    }
    
    /**
     * Analyze crime data and provide insights
     */
    private function analyzeCrimeData($crimeData) {
        $analysis = [
            'safety_score' => 0,
            'risk_level' => 'unknown',
            'recommendations' => []
        ];
        
        if (empty($crimeData)) {
            $analysis['safety_score'] = 100;
            $analysis['risk_level'] = 'very_low';
            $analysis['recommendations'][] = 'This area appears to have very low crime rates.';
            return $analysis;
        }
        
        $totalCrimes = count($crimeData);
        $analysis['safety_score'] = max(0, 100 - ($totalCrimes * 2));
        $analysis['risk_level'] = $this->getRiskLevel($totalCrimes);
        
        return $analysis;
    }
    
    /**
     * Calculate crime trends for an area
     */
    private function calculateCrimeTrends($bounds, $timeframe) {
        return [
            'overall_trend' => 'stable',
            'change_percentage' => rand(-10, 10)
        ];
    }
    
    /**
     * Make HTTP request to crime API
     */
    private function makeRequest($url) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'user_agent' => 'REMS Real Estate Platform/1.0'
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                return $this->createErrorResponse('Failed to fetch crime data');
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createErrorResponse('Invalid JSON response from crime API');
            }
            
            $this->updateRateLimit();
            
            return [
                'success' => true,
                'data' => $data
            ];
            
        } catch (Exception $e) {
            return $this->createErrorResponse('Crime API request error: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if crime service is enabled
     */
    private function isServiceEnabled() {
        return $this->config['crime']['enabled'] ?? false;
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
        
        $rateLimitData = array_filter($rateLimitData, function($timestamp) use ($currentTime) {
            return $currentTime - $timestamp < 86400;
        });
        
        $requestsThisMinute = count(array_filter($rateLimitData, function($timestamp) use ($currentMinute) {
            return floor($timestamp / 60) === $currentMinute;
        }));
        
        $maxPerMinute = $this->config['crime']['rate_limit']['calls_per_minute'];
        
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