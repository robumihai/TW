<?php
/**
 * Simplified Properties API Endpoint
 * Direct endpoint without complex routing for frontend use
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    require_once __DIR__ . '/api/models/Database.php';
    require_once __DIR__ . '/api/models/Property.php';
    
    $property = new Property();
    
    // Parse query parameters for filters
    $filters = [];
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 12), 50); // Max 50 per page
    
    // Basic filters from GET parameters
    if (!empty($_GET['search'])) {
        $filters['search'] = trim($_GET['search']);
    }
    
    if (!empty($_GET['property_type'])) {
        $filters['property_type'] = $_GET['property_type'];
    }
    
    if (!empty($_GET['transaction_type'])) {
        $filters['transaction_type'] = $_GET['transaction_type'];
    }
    
    if (!empty($_GET['city'])) {
        $filters['city'] = $_GET['city'];
    }
    
    // Price filters
    if (!empty($_GET['min_price'])) {
        $filters['min_price'] = (float)$_GET['min_price'];
    }
    
    if (!empty($_GET['max_price'])) {
        $filters['max_price'] = (float)$_GET['max_price'];
    }
    
    // Surface filters
    if (!empty($_GET['min_surface'])) {
        $filters['min_surface'] = (float)$_GET['min_surface'];
    }
    
    if (!empty($_GET['max_surface'])) {
        $filters['max_surface'] = (float)$_GET['max_surface'];
    }
    
    // Room filters
    if (!empty($_GET['rooms'])) {
        $filters['rooms'] = (int)$_GET['rooms'];
    }
    
    // Sorting
    if (!empty($_GET['sort'])) {
        $filters['sort'] = $_GET['sort'];
    }
    
    // Get endpoint from path
    $endpoint = $_GET['endpoint'] ?? 'search';
    
    switch ($endpoint) {
        case 'search':
        default:
            $result = $property->search($filters, ['page' => $page, 'limit' => $limit]);
            break;
            
        case 'featured':
            $limit = min($limit, 6);
            $result = [
                'data' => $property->getFeatured($limit),
                'pagination' => [
                    'total' => $limit,
                    'page' => 1,
                    'limit' => $limit,
                    'total_pages' => 1
                ]
            ];
            break;
            
        case 'statistics':
            $result = $property->getStatistics();
            break;
            
        case 'cities':
            $result = [
                'data' => $property->getCities(),
                'count' => count($property->getCities())
            ];
            break;
            
        case 'types':
            $types = [
                'apartment' => 'Apartament',
                'house' => 'Casă',
                'studio' => 'Studio',
                'villa' => 'Vilă',
                'duplex' => 'Duplex',
                'penthouse' => 'Penthouse',
                'office' => 'Birou',
                'commercial' => 'Spațiu comercial',
                'industrial' => 'Spațiu industrial',
                'land' => 'Teren',
                'garage' => 'Garaj',
                'warehouse' => 'Depozit'
            ];
            $result = [
                'data' => $types,
                'count' => count($types)
            ];
            break;
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} 