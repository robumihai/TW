<?php
/**
 * REMS - Real Estate Management System
 * Authentication API Routes
 * 
 * Handles user authentication, registration, and session management
 */

declare(strict_types=1);

/**
 * Handle authentication endpoints
 */
function handleAuth(string $method, ?string $action, array $input, Security $security): void
{
    logRequest("/auth/$action");
    
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleLogin($input, $security);
            break;
            
        case 'logout':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleLogout($security);
            break;
            
        case 'register':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleRegister($input, $security);
            break;
            
        case 'me':
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            handleGetCurrentUser($security);
            break;
            
        case 'refresh':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleRefreshSession($security);
            break;
            
        case 'csrf':
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            handleGetCSRFToken($security);
            break;
            
        case 'forgot-password':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleForgotPassword($input, $security);
            break;
            
        case 'reset-password':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleResetPassword($input, $security);
            break;
            
        default:
            Response::notFound('Authentication endpoint not found');
    }
}

/**
 * Handle user login
 */
function handleLogin(array $input, Security $security): void
{
    try {
        validateContentType();
        
        // Validate required fields
        Response::validateRequired($input, ['login', 'password']);
        
        $login = $security->sanitizeInput($input['login']);
        $password = $input['password'];
        $rememberMe = $input['remember_me'] ?? false;
        
        // Rate limiting for login attempts
        $clientIP = $security->getClientIP();
        if (!$security->checkRateLimit($clientIP, 'login')) {
            Response::rateLimitExceeded('Too many login attempts. Please try again later.');
        }
        
        // Check login attempts
        if (!$security->checkLoginAttempts($login)) {
            Response::forbidden('Account is temporarily locked due to too many failed login attempts.');
        }
        
        // Get user from database
        $db = Database::getInstance();
        $user = $db->queryOne(
            "SELECT * FROM users WHERE (email = ? OR username = ?) AND status != 'inactive'",
            [$login, $login]
        );
        
        if (!$user) {
            $security->recordFailedLogin($login);
            $security->logSecurityEvent('failed_login_attempt', ['login' => $login, 'reason' => 'user_not_found']);
            Response::unauthorized('Invalid credentials');
        }
        
        // Check if account is locked
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $lockTime = date('H:i', strtotime($user['locked_until']));
            Response::forbidden("Account is locked until $lockTime");
        }
        
        // Verify password
        if (!$security->verifyPassword($password, $user['password_hash'])) {
            $security->recordFailedLogin($login);
            $security->logSecurityEvent('failed_login_attempt', ['login' => $login, 'reason' => 'invalid_password']);
            Response::unauthorized('Invalid credentials');
        }
        
        // Check account status
        if ($user['status'] !== 'active') {
            $security->logSecurityEvent('failed_login_attempt', ['login' => $login, 'reason' => 'account_not_active']);
            Response::forbidden('Account is not active. Please contact support.');
        }
        
        // Reset login attempts on successful login
        $security->resetLoginAttempts($login);
        
        // Update last login
        $db->update('users', [
            'last_login' => date('Y-m-d H:i:s')
        ], ['id' => $user['id']]);
        
        // Create session
        createUserSession($user, $rememberMe);
        
        // Prepare user data for response (remove sensitive info)
        $userData = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role'],
            'status' => $user['status'],
            'email_verified' => (bool) $user['email_verified'],
            'last_login' => $user['last_login'],
        ];
        
        $security->logSecurityEvent('successful_login', ['user_id' => $user['id']]);
        
        Response::success([
            'user' => $userData,
            'csrf_token' => $security->generateCSRFToken()
        ], 'Login successful');
        
    } catch (Exception $e) {
        Response::serverError('Login failed: ' . $e->getMessage());
    }
}

/**
 * Handle user logout
 */
function handleLogout(Security $security): void
{
    try {
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($userId) {
            $security->logSecurityEvent('logout', ['user_id' => $userId]);
        }
        
        // Destroy session
        session_unset();
        session_destroy();
        
        // Clear session cookie
        setcookie(session_name(), '', time() - 3600, '/');
        
        Response::success(null, 'Logout successful');
        
    } catch (Exception $e) {
        Response::serverError('Logout failed: ' . $e->getMessage());
    }
}

/**
 * Handle user registration
 */
function handleRegister(array $input, Security $security): void
{
    try {
        validateContentType();
        
        // Validate required fields
        Response::validateRequired($input, [
            'username', 'email', 'password', 'first_name', 'last_name'
        ]);
        
        // Sanitize input
        $data = [
            'username' => $security->sanitizeInput($input['username'], 'alphanumeric'),
            'email' => $security->sanitizeInput($input['email'], 'email'),
            'password' => $input['password'],
            'first_name' => $security->sanitizeInput($input['first_name']),
            'last_name' => $security->sanitizeInput($input['last_name']),
            'phone' => $security->sanitizeInput($input['phone'] ?? ''),
        ];
        
        // Validate input data
        $validationRules = [
            'username' => [
                'required' => true,
                'min_length' => 3,
                'max_length' => 50,
                'pattern' => '/^[a-zA-Z0-9_]+$/'
            ],
            'email' => [
                'required' => true,
                'type' => 'email',
                'max_length' => 255
            ],
            'password' => [
                'required' => true,
                'min_length' => 8
            ],
            'first_name' => [
                'required' => true,
                'max_length' => 100
            ],
            'last_name' => [
                'required' => true,
                'max_length' => 100
            ],
            'phone' => [
                'type' => 'phone'
            ]
        ];
        
        $validationErrors = $security->validateInput($data, $validationRules);
        if (!empty($validationErrors)) {
            Response::validationError($validationErrors);
        }
        
        // Validate password strength
        $passwordErrors = $security->validatePasswordStrength($data['password']);
        if (!empty($passwordErrors)) {
            Response::validationError(['password' => $passwordErrors]);
        }
        
        $db = Database::getInstance();
        
        // Check if username exists
        $existingUser = $db->queryOne(
            "SELECT id FROM users WHERE username = ?",
            [$data['username']]
        );
        if ($existingUser) {
            Response::validationError(['username' => ['Username already exists']]);
        }
        
        // Check if email exists
        $existingEmail = $db->queryOne(
            "SELECT id FROM users WHERE email = ?",
            [$data['email']]
        );
        if ($existingEmail) {
            Response::validationError(['email' => ['Email already exists']]);
        }
        
        // Create user
        $userData = [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $security->hashPassword($data['password']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?: null,
            'role' => 'user',
            'status' => 'pending', // Require email verification
            'email_verified' => false,
            'email_verification_token' => $security->generateToken(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        $userId = $db->insert('users', $userData);
        
        // Log registration
        $security->logSecurityEvent('user_registered', ['user_id' => $userId, 'email' => $data['email']]);
        
        // TODO: Send verification email (implement in later stage)
        
        // Prepare response data
        $responseData = [
            'id' => $userId,
            'username' => $data['username'],
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'status' => 'pending',
        ];
        
        Response::created($responseData, 'Registration successful. Please check your email for verification.');
        
    } catch (Exception $e) {
        Response::serverError('Registration failed: ' . $e->getMessage());
    }
}

/**
 * Get current authenticated user
 */
function handleGetCurrentUser(Security $security): void
{
    try {
        $user = requireAuth($security);
        
        // Remove sensitive information
        unset($user['password_hash'], $user['email_verification_token'], $user['password_reset_token']);
        
        Response::success($user, 'User data retrieved successfully');
        
    } catch (Exception $e) {
        Response::serverError('Failed to get user data: ' . $e->getMessage());
    }
}

/**
 * Refresh user session
 */
function handleRefreshSession(Security $security): void
{
    try {
        $user = requireAuth($security);
        
        // Generate new CSRF token
        $csrfToken = $security->generateCSRFToken();
        
        Response::success([
            'csrf_token' => $csrfToken,
            'user' => $user
        ], 'Session refreshed successfully');
        
    } catch (Exception $e) {
        Response::serverError('Failed to refresh session: ' . $e->getMessage());
    }
}

/**
 * Get CSRF token
 */
function handleGetCSRFToken(Security $security): void
{
    try {
        $token = $security->generateCSRFToken();
        
        Response::success([
            'csrf_token' => $token
        ], 'CSRF token generated successfully');
        
    } catch (Exception $e) {
        Response::serverError('Failed to generate CSRF token: ' . $e->getMessage());
    }
}

/**
 * Handle forgot password request
 */
function handleForgotPassword(array $input, Security $security): void
{
    try {
        validateContentType();
        
        Response::validateRequired($input, ['email']);
        
        $email = $security->sanitizeInput($input['email'], 'email');
        
        // Rate limiting
        $clientIP = $security->getClientIP();
        if (!$security->checkRateLimit($clientIP, 'forgot_password')) {
            Response::rateLimitExceeded('Too many password reset requests. Please try again later.');
        }
        
        $db = Database::getInstance();
        
        // Check if user exists
        $user = $db->queryOne(
            "SELECT id, email, username FROM users WHERE email = ? AND status = 'active'",
            [$email]
        );
        
        // Always return success to prevent email enumeration
        if ($user) {
            // Generate reset token
            $resetToken = $security->generateToken();
            $resetExpires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            
            // Update user with reset token
            $db->update('users', [
                'password_reset_token' => $resetToken,
                'password_reset_expires' => $resetExpires
            ], ['id' => $user['id']]);
            
            // Log password reset request
            $security->logSecurityEvent('password_reset_requested', ['user_id' => $user['id']]);
            
            // TODO: Send reset email (implement in later stage)
        }
        
        Response::success(null, 'If an account with that email exists, a password reset link has been sent.');
        
    } catch (Exception $e) {
        Response::serverError('Failed to process password reset request: ' . $e->getMessage());
    }
}

/**
 * Handle password reset
 */
function handleResetPassword(array $input, Security $security): void
{
    try {
        validateContentType();
        
        Response::validateRequired($input, ['token', 'password']);
        
        $token = $security->sanitizeInput($input['token']);
        $newPassword = $input['password'];
        
        // Validate password strength
        $passwordErrors = $security->validatePasswordStrength($newPassword);
        if (!empty($passwordErrors)) {
            Response::validationError(['password' => $passwordErrors]);
        }
        
        $db = Database::getInstance();
        
        // Find user by reset token
        $user = $db->queryOne(
            "SELECT id, email FROM users WHERE password_reset_token = ? AND password_reset_expires > ?",
            [$token, date('Y-m-d H:i:s')]
        );
        
        if (!$user) {
            Response::unauthorized('Invalid or expired reset token');
        }
        
        // Hash new password
        $passwordHash = $security->hashPassword($newPassword);
        
        // Update user password and clear reset token
        $db->update('users', [
            'password_hash' => $passwordHash,
            'password_reset_token' => null,
            'password_reset_expires' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $user['id']]);
        
        // Log password reset
        $security->logSecurityEvent('password_reset_completed', ['user_id' => $user['id']]);
        
        Response::success(null, 'Password reset successfully');
        
    } catch (Exception $e) {
        Response::serverError('Failed to reset password: ' . $e->getMessage());
    }
}

/**
 * Create user session
 */
function createUserSession(array $user, bool $rememberMe = false): void
{
    // Set session lifetime
    $lifetime = $rememberMe ? (30 * 24 * 3600) : 0; // 30 days or session
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Store user data in session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    // Set session cookie lifetime if remember me is checked
    if ($rememberMe) {
        setcookie(session_name(), session_id(), time() + $lifetime, '/', '', 
                 isset($_SERVER['HTTPS']), true);
    }
}