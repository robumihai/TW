<?php
/**
 * REMS - Real Estate Management System
 * API Response Handler
 * 
 * Standardizes API responses with proper headers, status codes, and error handling
 */

declare(strict_types=1);

class Response
{
    // HTTP status codes
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_CONFLICT = 409;
    public const HTTP_UNPROCESSABLE_ENTITY = 422;
    public const HTTP_TOO_MANY_REQUESTS = 429;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;
    public const HTTP_SERVICE_UNAVAILABLE = 503;
    
    private static bool $headersSet = false;
    
    /**
     * Set CORS and security headers
     */
    public static function setHeaders(): void
    {
        if (self::$headersSet) {
            return;
        }
        
        // CORS Headers
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:8000',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8000',
            // Add your production domain here
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, $allowedOrigins) || self::isDevelopment()) {
            header("Access-Control-Allow-Origin: $origin");
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24 hours
        
        // Content type
        header('Content-Type: application/json; charset=utf-8');
        
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // Remove server information
        header_remove('X-Powered-By');
        header_remove('Server');
        
        self::$headersSet = true;
    }
    
    /**
     * Handle OPTIONS request for CORS preflight
     */
    public static function handleOptions(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            self::setHeaders();
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Send successful response
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = self::HTTP_OK): void
    {
        self::setHeaders();
        http_response_code($statusCode);
        
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c'),
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }
    
    /**
     * Send error response
     */
    public static function error(string $message = 'Error', int $statusCode = self::HTTP_BAD_REQUEST, $errors = null): void
    {
        self::setHeaders();
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'error' => true,
            'message' => $message,
            'status_code' => $statusCode,
            'timestamp' => date('c'),
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        // Log error for debugging (except validation errors)
        if ($statusCode >= 500) {
            error_log("API Error ($statusCode): $message");
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }
    
    /**
     * Send validation error response
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::error($message, self::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }
    
    /**
     * Send unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, self::HTTP_UNAUTHORIZED);
    }
    
    /**
     * Send forbidden response
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, self::HTTP_FORBIDDEN);
    }
    
    /**
     * Send not found response
     */
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, self::HTTP_NOT_FOUND);
    }
    
    /**
     * Send method not allowed response
     */
    public static function methodNotAllowed(string $message = 'Method not allowed'): void
    {
        self::error($message, self::HTTP_METHOD_NOT_ALLOWED);
    }
    
    /**
     * Send rate limit exceeded response
     */
    public static function rateLimitExceeded(string $message = 'Rate limit exceeded'): void
    {
        self::error($message, self::HTTP_TOO_MANY_REQUESTS);
    }
    
    /**
     * Send internal server error response
     */
    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, self::HTTP_INTERNAL_SERVER_ERROR);
    }
    
    /**
     * Send service unavailable response
     */
    public static function serviceUnavailable(string $message = 'Service temporarily unavailable'): void
    {
        self::error($message, self::HTTP_SERVICE_UNAVAILABLE);
    }
    
    /**
     * Send paginated response
     */
    public static function paginated(array $data, array $pagination, string $message = 'Success'): void
    {
        self::setHeaders();
        http_response_code(self::HTTP_OK);
        
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'current_page' => (int) $pagination['current_page'],
                'per_page' => (int) $pagination['per_page'],
                'total' => (int) $pagination['total'],
                'total_pages' => (int) $pagination['total_pages'],
                'has_next_page' => $pagination['current_page'] < $pagination['total_pages'],
                'has_prev_page' => $pagination['current_page'] > 1,
            ],
            'timestamp' => date('c'),
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }
    
    /**
     * Send created resource response
     */
    public static function created($data, string $message = 'Resource created successfully'): void
    {
        self::success($data, $message, self::HTTP_CREATED);
    }
    
    /**
     * Send deleted resource response
     */
    public static function deleted(string $message = 'Resource deleted successfully'): void
    {
        self::setHeaders();
        http_response_code(self::HTTP_NO_CONTENT);
        
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c'),
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }
    
    /**
     * Send file download response
     */
    public static function download(string $filePath, string $filename = null, string $mimeType = null): void
    {
        if (!file_exists($filePath)) {
            self::notFound('File not found');
            return;
        }
        
        $filename = $filename ?: basename($filePath);
        $mimeType = $mimeType ?: self::getMimeType($filePath);
        
        // Security check - ensure file is in allowed directory
        $realPath = realpath($filePath);
        $allowedPaths = [
            realpath(__DIR__ . '/../../uploads/'),
            realpath(__DIR__ . '/../../exports/'),
        ];
        
        $isAllowed = false;
        foreach ($allowedPaths as $allowedPath) {
            if ($allowedPath && strpos($realPath, $allowedPath) === 0) {
                $isAllowed = true;
                break;
            }
        }
        
        if (!$isAllowed) {
            self::forbidden('Access to file is forbidden');
            return;
        }
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for file download
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output file
        readfile($filePath);
        exit;
    }
    
    /**
     * Get MIME type for file
     */
    private static function getMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Get request input data
     */
    public static function getInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            return $data ?: [];
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return $_GET;
        }
        
        return $_POST;
    }
    
    /**
     * Get request method
     */
    public static function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
    
    /**
     * Get request URI without query string
     */
    public static function getUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return strtok($uri, '?');
    }
    
    /**
     * Get query parameters
     */
    public static function getQuery(): array
    {
        return $_GET;
    }
    
    /**
     * Get specific header
     */
    public static function getHeader(string $name): ?string
    {
        $headerName = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$headerName] ?? null;
    }
    
    /**
     * Check if request is AJAX
     */
    public static function isAjax(): bool
    {
        return self::getHeader('X-Requested-With') === 'XMLHttpRequest';
    }
    
    /**
     * Check if in development mode
     */
    private static function isDevelopment(): bool
    {
        $devHosts = ['localhost', '127.0.0.1', '::1'];
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        
        return in_array($host, $devHosts) || 
               strpos($host, 'localhost') !== false ||
               strpos($host, '.local') !== false ||
               strpos($host, '.dev') !== false;
    }
    
    /**
     * Handle exceptions and send appropriate error response
     */
    public static function handleException(Throwable $e): void
    {
        // Log the exception
        error_log("Exception: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        
        // Determine appropriate status code based on exception type
        $statusCode = self::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'An unexpected error occurred';
        
        if ($e instanceof InvalidArgumentException) {
            $statusCode = self::HTTP_BAD_REQUEST;
            $message = $e->getMessage();
        } elseif ($e instanceof UnauthorizedHttpException) {
            $statusCode = self::HTTP_UNAUTHORIZED;
            $message = $e->getMessage();
        } elseif ($e instanceof ForbiddenHttpException) {
            $statusCode = self::HTTP_FORBIDDEN;
            $message = $e->getMessage();
        } elseif ($e instanceof NotFoundHttpException) {
            $statusCode = self::HTTP_NOT_FOUND;
            $message = $e->getMessage();
        } elseif ($e instanceof ValidationException) {
            $statusCode = self::HTTP_UNPROCESSABLE_ENTITY;
            $message = $e->getMessage();
        }
        
        // In development, include more details
        if (self::isDevelopment()) {
            $errorDetails = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
            
            if ($statusCode >= 500) {
                $errorDetails['trace'] = $e->getTrace();
            }
            
            self::error($message, $statusCode, $errorDetails);
        } else {
            // In production, don't expose sensitive error details
            if ($statusCode >= 500) {
                $message = 'Internal server error';
            }
            
            self::error($message, $statusCode);
        }
    }
    
    /**
     * Validate required fields in input data
     */
    public static function validateRequired(array $data, array $required): void
    {
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            self::validationError(
                ['required' => $missing],
                'Missing required fields: ' . implode(', ', $missing)
            );
        }
    }
}

// Define custom exception classes
class UnauthorizedHttpException extends Exception {}
class ForbiddenHttpException extends Exception {}
class NotFoundHttpException extends Exception {}
class ValidationException extends Exception {} 