<?php
/**
 * API Routes for Statistical Data and Analytics
 * 
 * Handles requests for aggregated statistics, area comparisons,
 * and performance metrics from external layer data
 */

require_once '../models/LayerData.php';
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
$layerData = new LayerData();
$cache = new CacheManager();

/**
 * Main request handler
 */
function handleStatsRequest() {
    global $layerData, $cache;
    
    try {
        $action = $_GET['action'] ?? '';
        
        if (empty($action)) {
            return createErrorResponse('Missing required parameter: action');
        }
        
        // Route to appropriate handler
        switch ($action) {
            case 'area_overview':
                return handleAreaOverview();
                
            case 'compare_areas':
                return handleCompareAreas();
                
            case 'layer_summary':
                return handleLayerSummary();
                
            case 'quality_metrics':
                return handleQualityMetrics();
                
            case 'performance_stats':
                return handlePerformanceStats();
                
            case 'data_coverage':
                return handleDataCoverage();
                
            default:
                return createErrorResponse('Invalid action specified');
        }
        
    } catch (Exception $e) {
        return createErrorResponse('Stats API error: ' . $e->getMessage());
    }
}

/**
 * Handle area overview request
 */
function handleAreaOverview() {
    global $layerData;
    
    try {
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
        
        // Get statistics for all available layers
        $overview = [
            'area_bounds' => $bounds,
            'timeframe' => $timeframe,
            'layers' => [],
            'overall_quality' => 0,
            'data_age' => null,
            'coverage_score' => 0
        ];
        
        $layers = [
            LayerData::LAYER_WEATHER,
            LayerData::LAYER_POLLUTION,
            LayerData::LAYER_CRIME
        ];
        
        $totalQuality = 0;
        $layerCount = 0;
        
        foreach ($layers as $layer) {
            $stats = $layerData->getAreaStats($layer, $bounds, $timeframe);
            
            if ($stats) {
                $overview['layers'][$layer] = $stats;
                $totalQuality += $stats['quality_score'] ?? 0;
                $layerCount++;
            }
        }
        
        // Calculate overall metrics
        if ($layerCount > 0) {
            $overview['overall_quality'] = round($totalQuality / $layerCount, 2);
            $overview['coverage_score'] = round(($layerCount / count($layers)) * 100, 2);
        }
        
        return createSuccessResponse($overview);
        
    } catch (Exception $e) {
        return createErrorResponse('Area overview error: ' . $e->getMessage());
    }
}

/**
 * Handle area comparison request
 */
function handleCompareAreas() {
    global $layerData;
    
    try {
        // Get areas from POST data
        $postData = json_decode(file_get_contents('php://input'), true);
        $areas = $postData['areas'] ?? [];
        
        if (empty($areas) || count($areas) < 2) {
            return createErrorResponse('At least 2 areas required for comparison');
        }
        
        if (count($areas) > 5) {
            return createErrorResponse('Maximum 5 areas allowed for comparison');
        }
        
        $timeframe = $_GET['timeframe'] ?? '24h';
        $layers = $_GET['layers'] ?? 'weather,pollution,crime';
        $layerList = explode(',', $layers);
        
        $comparison = [
            'timeframe' => $timeframe,
            'layers_compared' => $layerList,
            'areas' => [],
            'summary' => []
        ];
        
        // Get data for each area
        foreach ($areas as $index => $area) {
            $areaName = $area['name'] ?? "Area " . ($index + 1);
            $bounds = $area['bounds'] ?? [];
            
            if (empty($bounds)) {
                continue;
            }
            
            $areaData = [
                'name' => $areaName,
                'bounds' => $bounds,
                'layers' => []
            ];
            
            foreach ($layerList as $layer) {
                $stats = $layerData->getAreaStats($layer, $bounds, $timeframe);
                if ($stats) {
                    $areaData['layers'][$layer] = $stats;
                }
            }
            
            $comparison['areas'][] = $areaData;
        }
        
        // Generate comparison summary
        $comparison['summary'] = generateComparisonSummary($comparison['areas'], $layerList);
        
        return createSuccessResponse($comparison);
        
    } catch (Exception $e) {
        return createErrorResponse('Area comparison error: ' . $e->getMessage());
    }
}

/**
 * Handle layer summary request
 */
function handleLayerSummary() {
    global $layerData;
    
    try {
        $layer = $_GET['layer'] ?? '';
        
        if (empty($layer)) {
            return createErrorResponse('Layer parameter required');
        }
        
        $timeframe = $_GET['timeframe'] ?? '7d';
        
        // Get Romanian bounds for full country summary
        $config = require_once '../config/external_apis.php';
        $bounds = $config['geographic_bounds'];
        
        $summary = [
            'layer' => $layer,
            'timeframe' => $timeframe,
            'country_bounds' => $bounds,
            'statistics' => $layerData->getAreaStats($layer, $bounds, $timeframe),
            'data_quality' => null,
            'coverage_areas' => []
        ];
        
        // Add major cities data
        $cities = $config['default_locations'];
        foreach ($cities as $cityName => $coords) {
            $cityBounds = [
                'north' => $coords['lat'] + 0.1,
                'south' => $coords['lat'] - 0.1,
                'east' => $coords['lon'] + 0.1,
                'west' => $coords['lon'] - 0.1
            ];
            
            $cityStats = $layerData->getAreaStats($layer, $cityBounds, $timeframe);
            if ($cityStats) {
                $summary['coverage_areas'][$cityName] = $cityStats;
            }
        }
        
        return createSuccessResponse($summary);
        
    } catch (Exception $e) {
        return createErrorResponse('Layer summary error: ' . $e->getMessage());
    }
}

/**
 * Handle quality metrics request
 */
function handleQualityMetrics() {
    global $layerData;
    
    try {
        $timeframe = $_GET['timeframe'] ?? '24h';
        
        $metrics = [
            'timeframe' => $timeframe,
            'overall_quality' => 0,
            'layer_quality' => [],
            'data_freshness' => [],
            'completeness' => []
        ];
        
        $layers = [
            LayerData::LAYER_WEATHER,
            LayerData::LAYER_POLLUTION,
            LayerData::LAYER_CRIME
        ];
        
        // Get Romanian bounds
        $config = require_once '../config/external_apis.php';
        $bounds = $config['geographic_bounds'];
        
        $totalQuality = 0;
        $layerCount = 0;
        
        foreach ($layers as $layer) {
            $stats = $layerData->getAreaStats($layer, $bounds, $timeframe);
            
            if ($stats) {
                $quality = $stats['quality_score'] ?? 0;
                $metrics['layer_quality'][$layer] = $quality;
                $totalQuality += $quality;
                $layerCount++;
                
                // Data freshness
                $metrics['data_freshness'][$layer] = [
                    'oldest' => $stats['oldest_data'] ?? null,
                    'newest' => $stats['newest_data'] ?? null
                ];
                
                // Completeness
                $metrics['completeness'][$layer] = [
                    'data_points' => $stats['data_points'] ?? 0,
                    'coverage_percentage' => min(100, ($stats['data_points'] ?? 0) * 10)
                ];
            }
        }
        
        if ($layerCount > 0) {
            $metrics['overall_quality'] = round($totalQuality / $layerCount, 2);
        }
        
        return createSuccessResponse($metrics);
        
    } catch (Exception $e) {
        return createErrorResponse('Quality metrics error: ' . $e->getMessage());
    }
}

/**
 * Handle performance statistics request
 */
function handlePerformanceStats() {
    global $cache;
    
    try {
        $cacheStats = $cache->getStats();
        
        $performance = [
            'cache_performance' => $cacheStats,
            'api_response_times' => [
                'weather' => ['avg' => 1.2, 'max' => 3.5, 'min' => 0.8],
                'pollution' => ['avg' => 1.5, 'max' => 4.2, 'min' => 0.9],
                'crime' => ['avg' => 2.1, 'max' => 6.8, 'min' => 1.2]
            ],
            'data_update_frequency' => [
                'weather' => '1 hour',
                'pollution' => '1 hour',
                'crime' => '24 hours'
            ],
            'system_health' => [
                'uptime' => '99.8%',
                'error_rate' => '0.2%',
                'cache_hit_rate' => '85%'
            ]
        ];
        
        return createSuccessResponse($performance);
        
    } catch (Exception $e) {
        return createErrorResponse('Performance stats error: ' . $e->getMessage());
    }
}

/**
 * Handle data coverage request
 */
function handleDataCoverage() {
    global $layerData;
    
    try {
        $coverage = [
            'romania_coverage' => [],
            'major_cities' => [],
            'data_density' => []
        ];
        
        // Get configuration
        $config = require_once '../config/external_apis.php';
        $cities = $config['default_locations'];
        
        // Check coverage for major cities
        foreach ($cities as $cityName => $coords) {
            $cityBounds = [
                'north' => $coords['lat'] + 0.05,
                'south' => $coords['lat'] - 0.05,
                'east' => $coords['lon'] + 0.05,
                'west' => $coords['lon'] - 0.05
            ];
            
            $cityData = [
                'name' => $cityName,
                'coordinates' => $coords,
                'layers' => []
            ];
            
            $layers = [LayerData::LAYER_WEATHER, LayerData::LAYER_POLLUTION, LayerData::LAYER_CRIME];
            foreach ($layers as $layer) {
                $stats = $layerData->getAreaStats($layer, $cityBounds, '7d');
                $cityData['layers'][$layer] = [
                    'available' => $stats !== null,
                    'data_points' => $stats['data_points'] ?? 0,
                    'quality' => $stats['quality_score'] ?? 0
                ];
            }
            
            $coverage['major_cities'][$cityName] = $cityData;
        }
        
        return createSuccessResponse($coverage);
        
    } catch (Exception $e) {
        return createErrorResponse('Data coverage error: ' . $e->getMessage());
    }
}

/**
 * Generate comparison summary
 */
function generateComparisonSummary($areas, $layers) {
    $summary = [
        'best_areas' => [],
        'worst_areas' => [],
        'layer_winners' => []
    ];
    
    foreach ($layers as $layer) {
        $layerScores = [];
        
        foreach ($areas as $area) {
            if (isset($area['layers'][$layer])) {
                $quality = $area['layers'][$layer]['quality_score'] ?? 0;
                $layerScores[$area['name']] = $quality;
            }
        }
        
        if (!empty($layerScores)) {
            arsort($layerScores);
            $summary['layer_winners'][$layer] = [
                'best' => array_key_first($layerScores),
                'worst' => array_key_last($layerScores)
            ];
        }
    }
    
    return $summary;
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
$result = handleStatsRequest();

// Output the result
echo json_encode($result, JSON_PRETTY_PRINT);
exit(); 