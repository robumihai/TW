<?php
/**
 * LayerData Model
 * 
 * Handles data structure and operations for external API layer data
 * Standardizes data from different external services
 */

require_once 'Database.php';

class LayerData {
    private $db;
    
    // Standard layer types
    const LAYER_WEATHER = 'weather';
    const LAYER_POLLUTION = 'pollution';
    const LAYER_CRIME = 'crime';
    const LAYER_DEMOGRAPHICS = 'demographics';
    const LAYER_NOISE = 'noise';
    
    // Data quality levels
    const QUALITY_HIGH = 'high';
    const QUALITY_MEDIUM = 'medium';
    const QUALITY_LOW = 'low';
    const QUALITY_UNKNOWN = 'unknown';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create the layer_data table if it doesn't exist
     */
    public function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS layer_data (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            layer_type TEXT NOT NULL,
            data_source TEXT NOT NULL,
            latitude REAL NOT NULL,
            longitude REAL NOT NULL,
            radius INTEGER DEFAULT 1000,
            data_json TEXT NOT NULL,
            quality TEXT DEFAULT 'unknown',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME,
            UNIQUE(layer_type, data_source, latitude, longitude, radius)
        )";
        
        return $this->db->execute($sql);
    }
    
    /**
     * Standardize weather data
     */
    public function standardizeWeatherData($rawData, $source = 'openweathermap') {
        try {
            $standardized = [
                'type' => self::LAYER_WEATHER,
                'source' => $source,
                'timestamp' => time(),
                'data' => []
            ];
            
            if ($source === 'openweathermap') {
                $standardized['data'] = [
                    'temperature' => [
                        'value' => $rawData['main']['temp'] ?? null,
                        'unit' => 'kelvin',
                        'celsius' => isset($rawData['main']['temp']) ? round($rawData['main']['temp'] - 273.15, 1) : null
                    ],
                    'humidity' => [
                        'value' => $rawData['main']['humidity'] ?? null,
                        'unit' => 'percent'
                    ],
                    'pressure' => [
                        'value' => $rawData['main']['pressure'] ?? null,
                        'unit' => 'hPa'
                    ],
                    'wind' => [
                        'speed' => $rawData['wind']['speed'] ?? null,
                        'direction' => $rawData['wind']['deg'] ?? null,
                        'unit' => 'm/s'
                    ],
                    'description' => $rawData['weather'][0]['description'] ?? null,
                    'icon' => $rawData['weather'][0]['icon'] ?? null,
                    'visibility' => $rawData['visibility'] ?? null
                ];
            }
            
            return $standardized;
            
        } catch (Exception $e) {
            return $this->createErrorData(self::LAYER_WEATHER, $e->getMessage());
        }
    }
    
    /**
     * Standardize air pollution data
     */
    public function standardizePollutionData($rawData, $source = 'openweathermap') {
        try {
            $standardized = [
                'type' => self::LAYER_POLLUTION,
                'source' => $source,
                'timestamp' => time(),
                'data' => []
            ];
            
            if ($source === 'openweathermap') {
                $aqi = $rawData['list'][0]['main']['aqi'] ?? null;
                $components = $rawData['list'][0]['components'] ?? [];
                
                $standardized['data'] = [
                    'aqi' => [
                        'value' => $aqi,
                        'level' => $this->getAQILevel($aqi),
                        'scale' => '1-5'
                    ],
                    'pm2_5' => [
                        'value' => $components['pm2_5'] ?? null,
                        'unit' => 'μg/m³'
                    ],
                    'pm10' => [
                        'value' => $components['pm10'] ?? null,
                        'unit' => 'μg/m³'
                    ],
                    'co' => [
                        'value' => $components['co'] ?? null,
                        'unit' => 'μg/m³'
                    ],
                    'no2' => [
                        'value' => $components['no2'] ?? null,
                        'unit' => 'μg/m³'
                    ],
                    'o3' => [
                        'value' => $components['o3'] ?? null,
                        'unit' => 'μg/m³'
                    ],
                    'so2' => [
                        'value' => $components['so2'] ?? null,
                        'unit' => 'μg/m³'
                    ]
                ];
            }
            
            return $standardized;
            
        } catch (Exception $e) {
            return $this->createErrorData(self::LAYER_POLLUTION, $e->getMessage());
        }
    }
    
    /**
     * Standardize crime data
     */
    public function standardizeCrimeData($rawData, $source = 'police_uk') {
        try {
            $standardized = [
                'type' => self::LAYER_CRIME,
                'source' => $source,
                'timestamp' => time(),
                'data' => []
            ];
            
            if ($source === 'police_uk') {
                $crimeStats = [];
                $totalCrimes = count($rawData);
                
                // Group crimes by category
                foreach ($rawData as $crime) {
                    $category = $crime['category'] ?? 'unknown';
                    if (!isset($crimeStats[$category])) {
                        $crimeStats[$category] = 0;
                    }
                    $crimeStats[$category]++;
                }
                
                $standardized['data'] = [
                    'total_crimes' => $totalCrimes,
                    'categories' => $crimeStats,
                    'crime_rate' => $this->calculateCrimeRate($totalCrimes),
                    'safety_level' => $this->getSafetyLevel($totalCrimes),
                    'period' => 'last_month'
                ];
            }
            
            return $standardized;
            
        } catch (Exception $e) {
            return $this->createErrorData(self::LAYER_CRIME, $e->getMessage());
        }
    }
    
    /**
     * Store layer data in database
     */
    public function store($layerType, $dataSource, $latitude, $longitude, $data, $radius = 1000, $expiresAt = null) {
        try {
            $this->createTable();
            
            $quality = $this->assessDataQuality($data);
            $dataJson = json_encode($data);
            $currentTime = date('Y-m-d H:i:s');
            
            $sql = "INSERT OR REPLACE INTO layer_data 
                   (layer_type, data_source, latitude, longitude, radius, data_json, quality, updated_at, expires_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $layerType,
                $dataSource,
                $latitude,
                $longitude,
                $radius,
                $dataJson,
                $quality,
                $currentTime,
                $expiresAt
            ];
            
            return $this->db->execute($sql, $params);
            
        } catch (Exception $e) {
            error_log("LayerData store error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retrieve layer data from database
     */
    public function get($layerType, $latitude, $longitude, $radius = 1000) {
        try {
            $sql = "SELECT * FROM layer_data 
                   WHERE layer_type = ? 
                   AND latitude BETWEEN ? AND ?
                   AND longitude BETWEEN ? AND ?
                   AND (expires_at IS NULL OR expires_at > datetime('now'))
                   ORDER BY updated_at DESC
                   LIMIT 10";
            
            $latRange = 0.01; // Approximately 1km
            $lonRange = 0.01;
            
            $params = [
                $layerType,
                $latitude - $latRange,
                $latitude + $latRange,
                $longitude - $lonRange,
                $longitude + $lonRange
            ];
            
            $results = $this->db->fetchAll($sql, $params);
            
            if ($results) {
                foreach ($results as &$result) {
                    $result['data_json'] = json_decode($result['data_json'], true);
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("LayerData get error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get aggregated statistics for a layer type in an area
     */
    public function getAreaStats($layerType, $bounds, $timeframe = '24h') {
        try {
            $sql = "SELECT 
                       COUNT(*) as data_points,
                       AVG(quality = 'high') * 100 as quality_score,
                       MIN(updated_at) as oldest_data,
                       MAX(updated_at) as newest_data
                   FROM layer_data 
                   WHERE layer_type = ?
                   AND latitude BETWEEN ? AND ?
                   AND longitude BETWEEN ? AND ?
                   AND updated_at > datetime('now', '-' || ? || ' hours')";
            
            $hours = $this->parseTimeframe($timeframe);
            $params = [
                $layerType,
                $bounds['south'],
                $bounds['north'],
                $bounds['west'],
                $bounds['east'],
                $hours
            ];
            
            return $this->db->fetch($sql, $params);
            
        } catch (Exception $e) {
            error_log("LayerData getAreaStats error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Clean up expired data
     */
    public function cleanup() {
        try {
            $sql = "DELETE FROM layer_data 
                   WHERE expires_at IS NOT NULL 
                   AND expires_at < datetime('now')";
            
            return $this->db->execute($sql);
            
        } catch (Exception $e) {
            error_log("LayerData cleanup error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get AQI level description
     */
    private function getAQILevel($aqi) {
        switch ($aqi) {
            case 1: return 'Good';
            case 2: return 'Fair';
            case 3: return 'Moderate';
            case 4: return 'Poor';
            case 5: return 'Very Poor';
            default: return 'Unknown';
        }
    }
    
    /**
     * Calculate crime rate level
     */
    private function calculateCrimeRate($totalCrimes) {
        // Basic crime rate calculation (crimes per 1000 people)
        // This is a simplified version - real implementation would need population data
        if ($totalCrimes < 5) return 'low';
        if ($totalCrimes < 15) return 'medium';
        return 'high';
    }
    
    /**
     * Get safety level based on crime count
     */
    private function getSafetyLevel($totalCrimes) {
        if ($totalCrimes < 5) return 'very_safe';
        if ($totalCrimes < 10) return 'safe';
        if ($totalCrimes < 20) return 'moderate';
        if ($totalCrimes < 35) return 'unsafe';
        return 'very_unsafe';
    }
    
    /**
     * Assess data quality
     */
    private function assessDataQuality($data) {
        if (!is_array($data) || empty($data)) {
            return self::QUALITY_LOW;
        }
        
        $nullCount = 0;
        $totalFields = 0;
        
        array_walk_recursive($data, function($value) use (&$nullCount, &$totalFields) {
            $totalFields++;
            if ($value === null || $value === '') {
                $nullCount++;
            }
        });
        
        if ($totalFields === 0) return self::QUALITY_LOW;
        
        $completeness = 1 - ($nullCount / $totalFields);
        
        if ($completeness >= 0.9) return self::QUALITY_HIGH;
        if ($completeness >= 0.7) return self::QUALITY_MEDIUM;
        return self::QUALITY_LOW;
    }
    
    /**
     * Create error data structure
     */
    private function createErrorData($layerType, $errorMessage) {
        return [
            'type' => $layerType,
            'source' => 'error',
            'timestamp' => time(),
            'error' => $errorMessage,
            'data' => null
        ];
    }
    
    /**
     * Parse timeframe string to hours
     */
    private function parseTimeframe($timeframe) {
        $matches = [];
        if (preg_match('/(\d+)([hmd])/', $timeframe, $matches)) {
            $value = intval($matches[1]);
            $unit = $matches[2];
            
            switch ($unit) {
                case 'h': return $value;
                case 'd': return $value * 24;
                case 'm': return $value * 24 * 30;
                default: return 24;
            }
        }
        
        return 24;
    }
} 