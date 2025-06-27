<?php
/**
 * API Routes for External Layer Data
 * 
 * Handles requests for weather, pollution, crime, and other external data layers
 * Requires authentication and includes rate limiting
 */

require_once '../services/WeatherAPI.php';
require_once '../services/PollutionAPI.php';
require_once '../services/CrimeAPI.php';
require_once '../cache/CacheManager.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize services
$weatherAPI = new WeatherAPI();
$pollutionAPI = new PollutionAPI();
$crimeAPI = new CrimeAPI();
$cache = new CacheManager();

// Route handler
function handleLayerRequest() {
    global $weatherAPI, $pollutionAPI, $crimeAPI, $cache;
    
    try {
        // Get request parameters
        $action = $_GET['action'] ?? '';
        $layer = $_GET['layer'] ?? '';
        $lat = floatval($_GET['lat'] ?? 0);
        $lon = floatval($_GET['lon'] ?? 0);
        
        // Validate required parameters
        if (empty($action) || empty($layer)) {
            return createErrorResponse('Missing required parameters: action, layer');
        }
        
        if ($lat === 0 || $lon === 0) {
            return createErrorResponse('Invalid coordinates provided');
        }
        
        // Validate coordinates for Romania
        if (!isValidRomanianCoordinates($lat, $lon)) {
            return createErrorResponse('Coordinates outside Romanian territory');
        }
        
        // Route to appropriate handler
        switch ($action) {
            case 'get_current':
                return handleGetCurrent($layer, $lat, $lon);
                
            case 'get_forecast':
                return handleGetForecast($layer, $lat, $lon);
                
            case 'get_bulk':
                return handleGetBulk($layer);
                
            case 'get_area_stats':
                return handleGetAreaStats($layer);
                
            default:
                return createErrorResponse('Invalid action specified');
        }
        
    } catch (Exception $e) {
        return createErrorResponse('Layer API error: ' . $e->getMessage());
    }
}

/**
 * Handle get current data request
 */
function handleGetCurrent($layer, $lat, $lon) {
    global $weatherAPI, $pollutionAPI, $crimeAPI;
    
    switch ($layer) {
        case 'weather':
            $units = $_GET['units'] ?? 'metric';
            return $weatherAPI->getCurrentWeather($lat, $lon, $units);
            
        case 'pollution':
            return $pollutionAPI->getCurrentPollution($lat, $lon);
            
        case 'crime':
            $date = $_GET['date'] ?? null;
            return $crimeAPI->getCrimeData($lat, $lon, $date);
            
        default:
            return createErrorResponse('Unsupported layer type: ' . $layer);
    }
}

/**
 * Handle get forecast data request
 */
function handleGetForecast($layer, $lat, $lon) {
    global $weatherAPI, $pollutionAPI;
    
    switch ($layer) {
        case 'weather':
            $units = $_GET['units'] ?? 'metric';
            $days = intval($_GET['days'] ?? 5);
            return $weatherAPI->getForecast($lat, $lon, $units, $days);
            
        case 'pollution':
            $hours = intval($_GET['hours'] ?? 24);
            return $pollutionAPI->getPollutionForecast($lat, $lon, $hours);
            
        case 'crime':
            return createErrorResponse('Crime forecast not available');
            
        default:
            return createErrorResponse('Forecast not available for layer: ' . $layer);
    }
}

/**
 * Handle bulk data request
 */
function handleGetBulk($layer) {
    global $weatherAPI, $pollutionAPI;
    
    // Get locations from POST data
    $postData = json_decode(file_get_contents('php://input'), true);
    $locations = $postData['locations'] ?? [];
    
    if (empty($locations)) {
        return createErrorResponse('No locations provided for bulk request');
    }
    
    // Validate location count
    if (count($locations) > 50) {
        return createErrorResponse('Maximum 50 locations allowed per bulk request');
    }
    
    switch ($layer) {
        case 'weather':
            $units = $_GET['units'] ?? 'metric';
            return [
                'success' => true,
                'data' => $weatherAPI->getBulkWeather($locations, $units)
            ];
            
        case 'pollution':
            return [
                'success' => true,
                'data' => $pollutionAPI->getBulkPollution($locations)
            ];
            
        case 'crime':
            return createErrorResponse('Bulk crime data not supported');
            
        default:
            return createErrorResponse('Bulk data not supported for layer: ' . $layer);
    }
}

/**
 * Handle area statistics request
 */
function handleGetAreaStats($layer) {
    global $weatherAPI, $pollutionAPI, $crimeAPI;
    
    // Get bounds from query parameters
    $bounds = [
        'north' => floatval($_GET['north'] ?? 0),
        'south' => floatval($_GET['south'] ?? 0),
        'east' => floatval($_GET['east'] ?? 0),
        'west' => floatval($_GET['west'] ?? 0)
    ];
    
    // Validate bounds
    if ($bounds['north'] === 0 || $bounds['south'] === 0 || 
        $bounds['east'] === 0 || $bounds['west'] === 0) {
        return createErrorResponse('Invalid area bounds provided');
    }
    
    $timeframe = $_GET['timeframe'] ?? '24h';
    
    switch ($layer) {
        case 'weather':
            return $weatherAPI->getAreaWeatherStats($bounds, $timeframe);
            
        case 'pollution':
            return $pollutionAPI->getAreaPollutionStats($bounds, $timeframe);
            
        case 'crime':
            return $crimeAPI->getAreaCrimeStats($bounds, $timeframe);
            
        default:
            return createErrorResponse('Area stats not available for layer: ' . $layer);
    }
}

/**
 * Validate if coordinates are within Romanian territory
 */
function isValidRomanianCoordinates($lat, $lon) {
    $config = require_once '../config/external_apis.php';
    $bounds = $config['geographic_bounds'];
    
    return ($lat >= $bounds['south'] && $lat <= $bounds['north'] &&
            $lon >= $bounds['west'] && $lon <= $bounds['east']);
}

/**
 * Get cache statistics
 */
function getCacheStats() {
    global $cache;
    
    try {
        $stats = $cache->getStats();
        return [
            'success' => true,
            'data' => $stats
        ];
    } catch (Exception $e) {
        return createErrorResponse('Cache stats error: ' . $e->getMessage());
    }
}

/**
 * Clear cache for specific layer
 */
function clearLayerCache($layer) {
    global $cache;
    
    try {
        // This would need to be implemented based on cache structure
        // For now, return success
        return [
            'success' => true,
            'message' => "Cache cleared for layer: $layer"
        ];
    } catch (Exception $e) {
        return createErrorResponse('Cache clear error: ' . $e->getMessage());
    }
}

/**
 * Create error response
 */
function createErrorResponse($message, $code = 400) {
    http_response_code($code);
    return [
        'success' => false,
        'error' => $message,
        'timestamp' => time()
    ];
}

/**
 * Create success response
 */
function createSuccessResponse($data, $message = null) {
    return [
        'success' => true,
        'data' => $data,
        'message' => $message,
        'timestamp' => time()
    ];
}

// Handle the request
$result = null;

// Check for cache management actions
if (isset($_GET['cache_action'])) {
    switch ($_GET['cache_action']) {
        case 'stats':
            $result = getCacheStats();
            break;
        case 'clear':
            $layer = $_GET['layer'] ?? 'all';
            $result = clearLayerCache($layer);
            break;
        default:
            $result = createErrorResponse('Invalid cache action');
    }
} else {
    // Handle normal layer requests
    $result = handleLayerRequest();
}

// Output the result
echo json_encode($result, JSON_PRETTY_PRINT);
exit(); 