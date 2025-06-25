<?php
/**
 * REMS - Real Estate Management System
 * Main API Router - Enhanced for Stage 4
 * 
 * Central API entry point with comprehensive routing,
 * security, and error handling
 */

declare(strict_types=1);

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors to users

// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');

// Include autoloader and dependencies
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Security.php';
require_once __DIR__ . '/models/Database.php';

// Initialize core components
$response = new Response();
$security = Security::getInstance();

try {
    // Apply global security headers
    $security->setSecurityHeaders();
    
    // Handle CORS for all requests
    $response->cors();
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    // Parse the request path
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    
    // Remove 'api' from path if present
    if (isset($pathParts[0]) && $pathParts[0] === 'api') {
        array_shift($pathParts);
    }
    
    // Get the main resource/endpoint
    $resource = $pathParts[0] ?? '';
    
    // Global rate limiting
    if (!$security->checkRateLimit('global_api', 200, 3600)) {
        $response->error('Global rate limit exceeded. Please try again later.', 429);
    }
    
    // Log API request for monitoring
    logApiRequest($_SERVER['REQUEST_METHOD'], $path, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    // Route to appropriate handler
    switch ($resource) {
        case 'properties':
            require_once __DIR__ . '/routes/properties.php';
            break;
            
        case 'auth':
            require_once __DIR__ . '/routes/auth.php';
            break;
            
        case 'health':
            handleHealthCheck($response);
            break;
            
        case 'info':
            handleApiInfo($response);
            break;
            
        case 'test':
            handleTestEndpoint($response, $security);
            break;
            
        case '':
            // Root API endpoint - return API information
            handleApiRoot($response);
            break;
            
        default:
            $response->error("Resource '$resource' not found", 404);
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Return generic error to user
    $response->error('Internal server error occurred', 500);
}

/**
 * Handle health check endpoint
 */
function handleHealthCheck(Response $response): void
{
    try {
        // Check database connection
        $db = Database::getInstance();
        $db->queryScalar("SELECT 1");
        
        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'version' => '1.0.0',
            'stage' => 4,
            'services' => [
                'database' => 'healthy',
                'api' => 'healthy'
            ]
        ];
        
        $response->success($health);
        
    } catch (Exception $e) {
        $response->error('Service unhealthy: ' . $e->getMessage(), 503);
    }
}

/**
 * Handle API info endpoint
 */
function handleApiInfo(Response $response): void
{
    $info = [
        'name' => 'REMS API',
        'description' => 'Real Estate Management System API',
        'version' => '1.0.0',
        'stage' => 4,
        'endpoints' => [
            'properties' => [
                'GET /api/properties' => 'Search properties with filters',
                'GET /api/properties/featured' => 'Get featured properties',
                'GET /api/properties/statistics' => 'Get property statistics',
                'GET /api/properties/map' => 'Get properties for map display',
                'GET /api/properties/cities' => 'Get available cities',
                'GET /api/properties/types' => 'Get property types',
                'GET /api/properties/{id}' => 'Get property by ID',
                'GET /api/properties/{slug}' => 'Get property by slug'
            ],
            'auth' => [
                'POST /api/auth/login' => 'User login (Stage 6)',
                'POST /api/auth/register' => 'User registration (Stage 6)',
                'POST /api/auth/logout' => 'User logout (Stage 6)'
            ],
            'utility' => [
                'GET /api/health' => 'Health check',
                'GET /api/info' => 'API information',
                'GET /api/test' => 'Test endpoint'
            ]
        ],
        'features' => [
            'Advanced property search',
            'Geographic filtering',
            'Map integration',
            'Property statistics',
            'Image management (Stage 6)',
            'User authentication (Stage 6)',
            'Favorites system (Stage 6)',
            'Export functionality (Stage 6)'
        ]
    ];
    
    $response->success($info);
}

/**
 * Handle API root endpoint
 */
function handleApiRoot(Response $response): void
{
    $welcome = [
        'message' => 'Welcome to REMS API',
        'version' => '1.0.0',
        'stage' => 4,
        'documentation' => '/api/info',
        'health' => '/api/health',
        'endpoints' => [
            'properties' => '/api/properties',
            'auth' => '/api/auth'
        ]
    ];
    
    $response->success($welcome);
}

/**
 * Handle test endpoint for development
 */
function handleTestEndpoint(Response $response, Security $security): void
{
    $tests = [
        'timestamp' => date('c'),
        'method' => $_SERVER['REQUEST_METHOD'],
        'headers' => [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'accept' => $_SERVER['HTTP_ACCEPT'] ?? 'unknown'
        ],
        'security' => [
            'csrf_token_generated' => $security->generateCsrfToken(),
            'client_ip' => $security->getClientIp(),
            'rate_limit_remaining' => 100 // Placeholder
        ],
        'database' => 'not_tested'
    ];
    
    // Test database connection
    try {
        $db = Database::getInstance();
        $propertiesCount = $db->queryScalar("SELECT COUNT(*) FROM properties");
        $tests['database'] = [
            'status' => 'connected',
            'properties_count' => (int)$propertiesCount
        ];
    } catch (Exception $e) {
        $tests['database'] = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
    
    $response->success($tests);
}

/**
 * Log API requests for monitoring
 */
function logApiRequest(string $method, string $path, string $ip): void
{
    $logEntry = sprintf(
        "[%s] %s %s from %s\n",
        date('Y-m-d H:i:s'),
        $method,
        $path,
        $ip
    );
    
    $logFile = __DIR__ . '/../logs/api.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Log the request
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Log errors with context
 */
function logError(string $message, array $context = []): void
{
    $logEntry = sprintf(
        "[%s] ERROR: %s\nContext: %s\n\n",
        date('Y-m-d H:i:s'),
        $message,
        json_encode($context, JSON_PRETTY_PRINT)
    );
    
    $logFile = __DIR__ . '/../logs/errors.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Log the error
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
} 