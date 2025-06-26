<?php
/**
 * REMS - Real Estate Management System
 * Authentication Utilities
 * 
 * Helper functions for authentication and authorization
 */

declare(strict_types=1);

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/../models/Database.php';

/**
 * Require authentication - throws exception if user not authenticated
 */
function requireAuth(Security $security): array
{
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new UnauthorizedHttpException('Authentication required');
    }
    
    // Check session timeout
    $maxInactivity = 7200; // 2 hours
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity']) > $maxInactivity) {
        session_destroy();
        throw new UnauthorizedHttpException('Session expired');
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    // Get user from database
    $db = Database::getInstance();
    $user = $db->queryOne(
        "SELECT * FROM users WHERE id = ? AND status = 'active'",
        [$_SESSION['user_id']]
    );
    
    if (!$user) {
        session_destroy();
        throw new UnauthorizedHttpException('User not found or inactive');
    }
    
    return $user;
}

/**
 * Check if user has specific role
 */
function requireRole(Security $security, string $role): array
{
    $user = requireAuth($security);
    
    if ($user['role'] !== $role) {
        throw new ForbiddenHttpException("Role '$role' required");
    }
    
    return $user;
}

/**
 * Check if user has one of the specified roles
 */
function requireAnyRole(Security $security, array $roles): array
{
    $user = requireAuth($security);
    
    if (!in_array($user['role'], $roles)) {
        $rolesList = implode(', ', $roles);
        throw new ForbiddenHttpException("One of these roles required: $rolesList");
    }
    
    return $user;
}

/**
 * Check if user is admin
 */
function requireAdmin(Security $security): array
{
    return requireRole($security, 'admin');
}

/**
 * Check if user is agent or admin
 */
function requireAgent(Security $security): array
{
    return requireAnyRole($security, ['agent', 'admin']);
}

/**
 * Get authenticated user (returns null if not authenticated)
 */
function getAuthUser(): ?array
{
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $db = Database::getInstance();
        $user = $db->queryOne(
            "SELECT * FROM users WHERE id = ? AND status = 'active'",
            [$_SESSION['user_id']]
        );
        
        return $user ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if request has valid content type
 */
function validateContentType(): void
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        if (strpos($contentType, 'application/json') === false && 
            strpos($contentType, 'application/x-www-form-urlencoded') === false &&
            strpos($contentType, 'multipart/form-data') === false) {
            Response::error('Invalid content type. Expected application/json or form data.', 400);
        }
    }
}

/**
 * Check if user owns resource or is admin
 */
function canAccessResource(Security $security, int $resourceUserId): bool
{
    try {
        $user = requireAuth($security);
        return $user['id'] == $resourceUserId || $user['role'] === 'admin';
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if user can manage properties
 */
function canManageProperties(Security $security): bool
{
    try {
        $user = requireAuth($security);
        return in_array($user['role'], ['admin', 'agent']);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Log request for monitoring
 */
function logRequest(string $endpoint): void
{
    $logData = [
        'endpoint' => $endpoint,
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Log to file in development
    if (defined('APP_ENV') && APP_ENV === 'development') {
        error_log("API Request: " . json_encode($logData));
    }
} 