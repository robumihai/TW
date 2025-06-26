<?php
/**
 * REMS - Real Estate Management System
 * Security Utilities
 * 
 * Provides security features including input validation, CSRF protection,
 * rate limiting, and sanitization functions
 */

declare(strict_types=1);

require_once __DIR__ . '/../models/Database.php';

class Security
{
    private static ?Security $instance = null;
    private Database $db;
    private array $config;
    
    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->config = [
            'csrf_token_length' => 32,
            'session_regenerate_interval' => 300, // 5 minutes
            'max_login_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
            'rate_limit_window' => 3600, // 1 hour
            'rate_limit_max_requests' => 100,
            'password_min_length' => 8,
            'session_cookie_secure' => isset($_SERVER['HTTPS']),
            'session_cookie_httponly' => true,
            'session_cookie_samesite' => 'Strict',
        ];
    }
    
    public static function getInstance(): Security
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize security headers and session configuration
     */
    public function initialize(): void
    {
        $this->setSecurityHeaders();
        $this->configureSession();
        $this->preventClickjacking();
    }
    
    /**
     * Set security headers
     */
    public function setSecurityHeaders(): void
    {
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Content Security Policy
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https: blob:",
            "connect-src 'self' https://api.openstreetmap.org https://nominatim.openstreetmap.org",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests"
        ]);
        header("Content-Security-Policy: $csp");
        
        // Strict Transport Security (HTTPS only)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Remove server signature
        header_remove('X-Powered-By');
        header_remove('Server');
    }
    
    /**
     * Configure secure session settings
     */
    private function configureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Set session cookie parameters
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $this->config['session_cookie_secure'],
                'httponly' => $this->config['session_cookie_httponly'],
                'samesite' => $this->config['session_cookie_samesite']
            ]);
            
            // Configure session
            ini_set('session.gc_maxlifetime', (string) $this->config['session_regenerate_interval']);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_secure', $this->config['session_cookie_secure'] ? '1' : '0');
            
            session_start();
            
            // Regenerate session ID periodically
            $this->regenerateSessionIfNeeded();
        }
    }
    
    /**
     * Prevent clickjacking attacks
     */
    private function preventClickjacking(): void
    {
        if (!headers_sent()) {
            header('X-Frame-Options: DENY');
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || $this->isCSRFTokenExpired()) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes($this->config['csrf_token_length']));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || $this->isCSRFTokenExpired()) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check if CSRF token is expired
     */
    private function isCSRFTokenExpired(): bool
    {
        if (!isset($_SESSION['csrf_token_time'])) {
            return true;
        }
        
        return (time() - $_SESSION['csrf_token_time']) > 3600; // 1 hour
    }
    
    /**
     * Sanitize input data to prevent XSS
     */
    public function sanitizeInput($data, string $type = 'string')
    {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $data);
        }
        
        if (!is_string($data)) {
            return $data;
        }
        
        switch ($type) {
            case 'html':
                // Allow safe HTML tags
                return strip_tags($data, '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6>');
                
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
                
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
                
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'filename':
                // Remove dangerous characters from filenames
                return preg_replace('/[^a-zA-Z0-9._-]/', '', $data);
                
            case 'alphanumeric':
                return preg_replace('/[^a-zA-Z0-9]/', '', $data);
                
            case 'slug':
                return preg_replace('/[^a-zA-Z0-9-_]/', '', $data);
                
            case 'string':
            default:
                return htmlspecialchars(trim($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    
    /**
     * Validate input data
     */
    public function validateInput($data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $fieldErrors = [];
            
            // Check required
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $fieldErrors[] = "$field is required";
                continue;
            }
            
            // Skip other validations if field is empty and not required
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                continue;
            }
            
            // Type validation
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $fieldErrors[] = "$field must be a valid email";
                        }
                        break;
                        
                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $fieldErrors[] = "$field must be a valid URL";
                        }
                        break;
                        
                    case 'int':
                        if (!filter_var($value, FILTER_VALIDATE_INT)) {
                            $fieldErrors[] = "$field must be an integer";
                        }
                        break;
                        
                    case 'float':
                        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                            $fieldErrors[] = "$field must be a number";
                        }
                        break;
                        
                    case 'phone':
                        if (!$this->validatePhone($value)) {
                            $fieldErrors[] = "$field must be a valid phone number";
                        }
                        break;
                }
            }
            
            // Length validation
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $fieldErrors[] = "$field must be at least {$rule['min_length']} characters";
            }
            
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $fieldErrors[] = "$field must not exceed {$rule['max_length']} characters";
            }
            
            // Range validation
            if (isset($rule['min']) && is_numeric($value) && $value < $rule['min']) {
                $fieldErrors[] = "$field must be at least {$rule['min']}";
            }
            
            if (isset($rule['max']) && is_numeric($value) && $value > $rule['max']) {
                $fieldErrors[] = "$field must not exceed {$rule['max']}";
            }
            
            // Pattern validation
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $fieldErrors[] = "$field format is invalid";
            }
            
            // Enum validation
            if (isset($rule['enum']) && !in_array($value, $rule['enum'])) {
                $fieldErrors[] = "$field must be one of: " . implode(', ', $rule['enum']);
            }
            
            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate Romanian phone number
     */
    private function validatePhone(string $phone): bool
    {
        // Remove spaces and other characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Romanian phone number patterns
        $patterns = [
            '/^(\+40|0040)[0-9]{9}$/',  // International format
            '/^0[0-9]{9}$/',            // National format
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Rate limiting check
     */
    public function checkRateLimit(string $identifier, string $endpoint = 'general'): bool
    {
        $now = date('Y-m-d H:i:s');
        $windowStart = date('Y-m-d H:i:s', time() - $this->config['rate_limit_window']);
        
        // Clean up expired rate limit entries
        $this->db->execute(
            "DELETE FROM rate_limits WHERE reset_time < ?",
            [$now]
        );
        
        // Get current rate limit info
        $rateLimitInfo = $this->db->queryOne(
            "SELECT * FROM rate_limits WHERE identifier = ? AND endpoint = ?",
            [$identifier, $endpoint]
        );
        
        if (!$rateLimitInfo) {
            // Create new rate limit entry
            $this->db->insert('rate_limits', [
                'identifier' => $identifier,
                'endpoint' => $endpoint,
                'attempts' => 1,
                'reset_time' => date('Y-m-d H:i:s', time() + $this->config['rate_limit_window'])
            ]);
            return true;
        }
        
        // Check if window has expired
        if ($rateLimitInfo['reset_time'] < $now) {
            // Reset the window
            $this->db->update('rate_limits', [
                'attempts' => 1,
                'reset_time' => date('Y-m-d H:i:s', time() + $this->config['rate_limit_window'])
            ], [
                'identifier' => $identifier,
                'endpoint' => $endpoint
            ]);
            return true;
        }
        
        // Check if limit exceeded
        if ($rateLimitInfo['attempts'] >= $this->config['rate_limit_max_requests']) {
            return false;
        }
        
        // Increment request count
        $this->db->update('rate_limits', [
            'attempts' => $rateLimitInfo['attempts'] + 1
        ], [
            'identifier' => $identifier,
            'endpoint' => $endpoint
        ]);
        
        return true;
    }
    
    /**
     * Check and handle login attempts
     */
    public function checkLoginAttempts(string $identifier): bool
    {
        $attempts = $this->getLoginAttempts($identifier);
        
        if ($attempts >= $this->config['max_login_attempts']) {
            $this->lockAccount($identifier);
            return false;
        }
        
        return true;
    }
    
    /**
     * Record failed login attempt
     */
    public function recordFailedLogin(string $identifier): void
    {
        $this->db->execute(
            "UPDATE users SET login_attempts = login_attempts + 1 WHERE email = ? OR username = ?",
            [$identifier, $identifier]
        );
    }
    
    /**
     * Reset login attempts on successful login
     */
    public function resetLoginAttempts(string $identifier): void
    {
        $this->db->execute(
            "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ? OR username = ?",
            [$identifier, $identifier]
        );
    }
    
    /**
     * Get login attempts count
     */
    private function getLoginAttempts(string $identifier): int
    {
        $user = $this->db->queryOne(
            "SELECT login_attempts, locked_until FROM users WHERE email = ? OR username = ?",
            [$identifier, $identifier]
        );
        
        if (!$user) {
            return 0;
        }
        
        // Check if account is still locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return $this->config['max_login_attempts'];
        }
        
        return (int) $user['login_attempts'];
    }
    
    /**
     * Lock account after too many failed attempts
     */
    private function lockAccount(string $identifier): void
    {
        $lockUntil = date('Y-m-d H:i:s', time() + $this->config['lockout_duration']);
        
        $this->db->execute(
            "UPDATE users SET locked_until = ? WHERE email = ? OR username = ?",
            [$lockUntil, $identifier, $identifier]
        );
    }
    
    /**
     * Hash password securely
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }
    
    /**
     * Verify password against hash
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Validate password strength
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < $this->config['password_min_length']) {
            $errors[] = "Password must be at least {$this->config['password_min_length']} characters long";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return $errors;
    }
    
    /**
     * Generate secure random token
     */
    public function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Regenerate session ID if needed
     */
    private function regenerateSessionIfNeeded(): void
    {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        } elseif (time() - $_SESSION['last_regeneration'] > $this->config['session_regenerate_interval']) {
            $_SESSION['last_regeneration'] = time();
            session_regenerate_id(true);
        }
    }
    
    /**
     * Get client IP address (considering proxies)
     */
    public function getClientIP(): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent(string $event, array $details = []): void
    {
        $logData = [
            'user_id' => $_SESSION['user_id'] ?? null,
            'action' => $event,
            'resource_type' => 'security',
            'resource_id' => null,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'details' => json_encode($details),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $this->db->insert('activity_logs', $logData);
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }
}