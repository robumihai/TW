<?php
/**
 * REMS - Real Estate Management System
 * API Entry Point
 * 
 * Main router and middleware handler for the REST API
 */

declare(strict_types=1);

// Error reporting configuration
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors to users
ini_set('log_errors', '1');

// Include required files
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Security.php';

// Global exception handler
set_exception_handler([Response::class, 'handleException']);

// Global error handler
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Initialize security
    $security = Security::getInstance();
    $security->initialize();
    
    // Handle CORS preflight requests
    Response::handleOptions();
    
    // Set response headers
    Response::setHeaders();
    
    // Get request information
    $method = Response::getMethod();
    $uri = Response::getUri();
    $input = Response::getInput();
    
    // Remove API prefix from URI
    $uri = preg_replace('#^/api#', '', $uri);
    $uri = $uri ?: '/';
    
    // Rate limiting
    $clientIP = $security->getClientIP();
    if (!$security->checkRateLimit($clientIP, 'api')) {
        Response::rateLimitExceeded('Too many requests. Please try again later.');
    }
    
    // Basic request validation
    if (!Response::isAjax() && $method !== 'GET') {
        // Allow non-AJAX requests for development/testing
        $devMode = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
        if (!$devMode) {
            Response::forbidden('Only AJAX requests are allowed');
        }
    }
    
    // Route the request
    route($method, $uri, $input, $security);
    
} catch (Throwable $e) {
    Response::handleException($e);
}

/**
 * Simple router function
 */
function route(string $method, string $uri, array $input, Security $security): void
{
    // Split URI into segments
    $segments = array_filter(explode('/', trim($uri, '/')));
    $resource = $segments[0] ?? '';
    $id = $segments[1] ?? null;
    $action = $segments[2] ?? null;
    
    // Route to appropriate handler
    switch ($resource) {
        case '':
        case 'status':
            handleStatus();
            break;
            
        case 'properties':
            requireFile(__DIR__ . '/routes/properties.php');
            handleProperties($method, $id, $action, $input, $security);
            break;
            
        case 'auth':
            requireFile(__DIR__ . '/routes/auth.php');
            handleAuth($method, $id, $input, $security);
            break;
            
        case 'search':
            requireFile(__DIR__ . '/routes/properties.php');
            handleSearch($method, $input, $security);
            break;
            
        case 'config':
            handleConfig($method);
            break;
            
        case 'stats':
            requireFile(__DIR__ . '/routes/properties.php');
            handleStats($method, $security);
            break;
            
        default:
            Response::notFound('API endpoint not found');
    }
}

/**
 * Safely require a file
 */
function requireFile(string $filePath): void
{
    if (!file_exists($filePath)) {
        Response::serverError('Route handler not found');
    }
    require_once $filePath;
}

/**
 * Handle API status endpoint
 */
function handleStatus(): void
{
    if (Response::getMethod() !== 'GET') {
        Response::methodNotAllowed();
    }
    
    try {
        // Check database connection
        $db = Database::getInstance();
        $dbSize = $db->getDatabaseSize();
        
        $status = [
            'api' => 'REMS API v1.0',
            'status' => 'healthy',
            'timestamp' => date('c'),
            'server' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
            ],
            'database' => [
                'connection' => 'OK',
                'size' => $dbSize,
                'size_formatted' => formatBytes($dbSize),
            ],
        ];
        
        Response::success($status, 'API is healthy');
        
    } catch (Exception $e) {
        $status = [
            'api' => 'REMS API v1.0',
            'status' => 'error',
            'timestamp' => date('c'),
            'error' => $e->getMessage(),
        ];
        
        Response::error('API health check failed', Response::HTTP_SERVICE_UNAVAILABLE, $status);
    }
}

/**
 * Handle configuration endpoint (public configurations only)
 */
function handleConfig(string $method): void
{
    if ($method !== 'GET') {
        Response::methodNotAllowed();
    }
    
    try {
        $db = Database::getInstance();
        $publicConfigs = $db->query(
            "SELECT config_key, config_value, config_type FROM site_config WHERE is_public = 1"
        );
        
        $config = [];
        foreach ($publicConfigs as $configItem) {
            $value = $configItem['config_value'];
            
            // Convert value based on type
            switch ($configItem['config_type']) {
                case 'integer':
                    $value = (int) $value;
                    break;
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $config[$configItem['config_key']] = $value;
        }
        
        Response::success($config, 'Configuration retrieved successfully');
        
    } catch (Exception $e) {
        Response::serverError('Failed to retrieve configuration');
    }
}

/**
 * Format bytes to human readable format
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Check if user is authenticated (middleware)
 */
function requireAuth(Security $security): array
{
    if (!isset($_SESSION['user_id'])) {
        Response::unauthorized('Authentication required');
    }
    
    // Get user info from database
    $db = Database::getInstance();
    $user = $db->queryOne(
        "SELECT id, username, email, first_name, last_name, role, status FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );
    
    if (!$user) {
        // Invalid session - user doesn't exist
        session_destroy();
        Response::unauthorized('Invalid session');
    }
    
    if ($user['status'] !== 'active') {
        Response::forbidden('Account is not active');
    }
    
    return $user;
}

/**
 * Check if user has required role (middleware)
 */
function requireRole(string $role, Security $security): array
{
    $user = requireAuth($security);
    
    $roleHierarchy = [
        'user' => 1,
        'agent' => 2,
        'admin' => 3,
    ];
    
    $userLevel = $roleHierarchy[$user['role']] ?? 0;
    $requiredLevel = $roleHierarchy[$role] ?? 999;
    
    if ($userLevel < $requiredLevel) {
        Response::forbidden('Insufficient permissions');
    }
    
    return $user;
}

/**
 * Validate CSRF token (middleware)
 */
function validateCSRF(Security $security): void
{
    $method = Response::getMethod();
    
    // Only validate CSRF for state-changing methods
    if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        return;
    }
    
    $token = Response::getHeader('X-CSRF-Token') ?? '';
    
    if (!$security->validateCSRFToken($token)) {
        Response::forbidden('Invalid CSRF token');
    }
}

/**
 * Validate request content type for JSON endpoints
 */
function validateContentType(): void
{
    $method = Response::getMethod();
    
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') === false && 
            strpos($contentType, 'multipart/form-data') === false &&
            strpos($contentType, 'application/x-www-form-urlencoded') === false) {
            
            Response::error(
                'Content-Type must be application/json, multipart/form-data, or application/x-www-form-urlencoded',
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}

/**
 * Log API request for analytics
 */
function logRequest(string $endpoint, ?array $user = null): void
{
    try {
        $db = Database::getInstance();
        
        $logData = [
            'user_id' => $user['id'] ?? null,
            'action' => 'api_request',
            'resource_type' => 'api',
            'resource_id' => null,
            'ip_address' => Security::getInstance()->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => json_encode([
                'endpoint' => $endpoint,
                'method' => Response::getMethod(),
                'timestamp' => date('c'),
            ]),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        $db->insert('activity_logs', $logData);
        
    } catch (Exception $e) {
        // Don't fail the request if logging fails
        error_log("Failed to log API request: " . $e->getMessage());
    }
} 