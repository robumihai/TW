<?php
/**
 * REMS - Real Estate Management System
 * Properties API Routes - Enhanced for Stage 4
 * 
 * Complete property management endpoints with advanced features
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Property.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Security.php';

$property = new Property();
$response = new Response();
$security = Security::getInstance();

// Apply security headers and rate limiting
$security->setSecurityHeaders();

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $response->cors();
    exit;
}

// Check rate limit for API calls
if (!$security->checkRateLimit('api', 100, 3600)) {
    $response->error('Rate limit exceeded', 429);
}

// Get HTTP method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$endpoint = $pathParts[2] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGetRequests($endpoint, $pathParts, $property, $response, $security);
            break;
            
        case 'POST':
            handlePostRequests($endpoint, $pathParts, $property, $response, $security);
            break;
            
        case 'PUT':
        case 'PATCH':
            handlePutRequests($endpoint, $pathParts, $property, $response, $security);
            break;
            
        case 'DELETE':
            handleDeleteRequests($endpoint, $pathParts, $property, $response, $security);
            break;
            
        default:
            $response->error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Properties API error: " . $e->getMessage());
    $response->error('Internal server error', 500);
}

/**
 * Handle GET requests
 */
function handleGetRequests(string $endpoint, array $pathParts, Property $property, Response $response, Security $security): void
{
    switch ($endpoint) {
        case 'search':
        case '':
            // GET /api/properties or /api/properties/search
            handlePropertySearch($property, $response);
            break;
            
        case 'featured':
            // GET /api/properties/featured
            handleFeaturedProperties($property, $response);
            break;
            
        case 'statistics':
        case 'stats':
            // GET /api/properties/statistics
            handleStatistics($property, $response);
            break;
            
        case 'export':
            // GET /api/properties/export
            handleExport($property, $response, $security);
            break;
            
        case 'map':
            // GET /api/properties/map
            handleMapProperties($property, $response);
            break;
            
        case 'cities':
            // GET /api/properties/cities
            handleCities($property, $response);
            break;
            
        case 'types':
            // GET /api/properties/types
            handlePropertyTypes($property, $response);
            break;
            
        default:
            // GET /api/properties/{id} or /api/properties/{slug}
            if (is_numeric($endpoint)) {
                handlePropertyById((int)$endpoint, $property, $response);
            } elseif (!empty($endpoint)) {
                handlePropertyBySlug($endpoint, $property, $response);
            } else {
                handlePropertySearch($property, $response);
            }
            break;
    }
}

/**
 * Handle property search with advanced filters
 */
function handlePropertySearch(Property $property, Response $response): void
{
    $filters = [];
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 20), 100); // Max 100 per page
    
    // Basic filters
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
    
    if (!empty($_GET['bedrooms'])) {
        $filters['bedrooms'] = (int)$_GET['bedrooms'];
    }
    
    if (!empty($_GET['bathrooms'])) {
        $filters['bathrooms'] = (int)$_GET['bathrooms'];
    }
    
    // Map bounding box
    if (!empty($_GET['north']) && !empty($_GET['south']) && 
        !empty($_GET['east']) && !empty($_GET['west'])) {
        $filters['north'] = (float)$_GET['north'];
        $filters['south'] = (float)$_GET['south'];
        $filters['east'] = (float)$_GET['east'];
        $filters['west'] = (float)$_GET['west'];
    }
    
    // Sorting
    if (!empty($_GET['sort'])) {
        $filters['sort'] = $_GET['sort'];
    }
    
    $result = $property->search($filters, $page, $limit);
    $response->success($result);
}

/**
 * Handle featured properties
 */
function handleFeaturedProperties(Property $property, Response $response): void
{
    $limit = min((int)($_GET['limit'] ?? 10), 50);
    $properties = $property->getFeatured($limit);
    $response->success(['data' => $properties]);
}

/**
 * Handle statistics
 */
function handleStatistics(Property $property, Response $response): void
{
    $stats = $property->getStatistics();
    $response->success($stats);
}

/**
 * Handle property by ID
 */
function handlePropertyById(int $id, Property $property, Response $response): void
{
    $propertyData = $property->findById($id);
    
    if (!$propertyData) {
        $response->error('Property not found', 404);
    }
    
    // Get property images
    $propertyData['images'] = $property->getImages($id);
    
    // Get similar properties
    $propertyData['similar'] = $property->getSimilar($id, 5);
    
    $response->success($propertyData);
}

/**
 * Handle property by slug
 */
function handlePropertyBySlug(string $slug, Property $property, Response $response): void
{
    $propertyData = $property->findBySlug($slug);
    
    if (!$propertyData) {
        $response->error('Property not found', 404);
    }
    
    // Get property images
    $propertyData['images'] = $property->getImages($propertyData['id']);
    
    // Get similar properties
    $propertyData['similar'] = $property->getSimilar($propertyData['id'], 5);
    
    $response->success($propertyData);
}

/**
 * Handle map properties (optimized for map display)
 */
function handleMapProperties(Property $property, Response $response): void
{
    $filters = [];
    
    // Map-specific filters
    if (!empty($_GET['north']) && !empty($_GET['south']) && 
        !empty($_GET['east']) && !empty($_GET['west'])) {
        $filters['north'] = (float)$_GET['north'];
        $filters['south'] = (float)$_GET['south'];
        $filters['east'] = (float)$_GET['east'];
        $filters['west'] = (float)$_GET['west'];
    }
    
    // Basic filters
    if (!empty($_GET['property_type'])) {
        $filters['property_type'] = $_GET['property_type'];
    }
    
    if (!empty($_GET['transaction_type'])) {
        $filters['transaction_type'] = $_GET['transaction_type'];
    }
    
    if (!empty($_GET['min_price'])) {
        $filters['min_price'] = (float)$_GET['min_price'];
    }
    
    if (!empty($_GET['max_price'])) {
        $filters['max_price'] = (float)$_GET['max_price'];
    }
    
    // Get properties for map (larger limit, optimized data)
    $result = $property->search($filters, 1, 500);
    
    // Optimize for map display - only essential data
    $mapProperties = [];
    foreach ($result['data'] as $prop) {
        if (!empty($prop['latitude']) && !empty($prop['longitude'])) {
            $mapProperties[] = [
                'id' => $prop['id'],
                'title' => $prop['title'],
                'property_type' => $prop['property_type'],
                'transaction_type' => $prop['transaction_type'],
                'price' => $prop['price'],
                'currency' => $prop['currency'],
                'city' => $prop['city'],
                'latitude' => (float)$prop['latitude'],
                'longitude' => (float)$prop['longitude'],
                'featured' => (bool)$prop['featured'],
                'primary_image' => $prop['primary_image'],
                'slug' => $prop['slug']
            ];
        }
    }
    
    $response->success(['data' => $mapProperties]);
}

/**
 * Handle get cities
 */
function handleCities(Property $property, Response $response): void
{
    $cities = [
        'București', 'Cluj-Napoca', 'Constanța', 'Iași', 'Timișoara', 
        'Craiova', 'Brașov', 'Galați', 'Ploiești', 'Oradea'
    ];
    
    $response->success(['data' => $cities]);
}

/**
 * Handle get property types
 */
function handlePropertyTypes(Property $property, Response $response): void
{
    $types = [
        ['value' => 'apartment', 'label' => 'Apartament'],
        ['value' => 'house', 'label' => 'Casă'],
        ['value' => 'studio', 'label' => 'Studio'],
        ['value' => 'villa', 'label' => 'Vilă'],
        ['value' => 'duplex', 'label' => 'Duplex'],
        ['value' => 'penthouse', 'label' => 'Penthouse'],
        ['value' => 'office', 'label' => 'Birou'],
        ['value' => 'commercial', 'label' => 'Spațiu comercial'],
        ['value' => 'industrial', 'label' => 'Spațiu industrial'],
        ['value' => 'land', 'label' => 'Teren'],
        ['value' => 'garage', 'label' => 'Garaj'],
        ['value' => 'warehouse', 'label' => 'Depozit']
    ];
    
    $response->success(['data' => $types]);
}

// Placeholder functions for POST requests (for future auth implementation)
function handlePostRequests($endpoint, $pathParts, $property, $response, $security) {
    $response->error('POST operations require authentication (coming in Stage 6)', 501);
}

function handlePutRequests($endpoint, $pathParts, $property, $response, $security) {
    $response->error('PUT operations require authentication (coming in Stage 6)', 501);
}

function handleDeleteRequests($endpoint, $pathParts, $property, $response, $security) {
    $response->error('DELETE operations require authentication (coming in Stage 6)', 501);
}

function handleExport($property, $response, $security) {
    $response->error('Export functionality requires authentication (coming in Stage 6)', 501);
}
