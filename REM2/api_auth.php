<?php
/**
 * Simplified Authentication API Endpoint
 * Direct endpoint without complex routing for frontend use
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    require_once __DIR__ . '/api/models/Database.php';
    require_once __DIR__ . '/api/utils/Security.php';
    require_once __DIR__ . '/api/utils/Response.php';
    require_once __DIR__ . '/api/utils/Auth.php';
    
    $security = Security::getInstance();
    $method = $_SERVER['REQUEST_METHOD'];
    $endpoint = $_GET['endpoint'] ?? '';
    
    // Initialize session
    session_start();
    
    switch ($endpoint) {
        case 'login':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleLoginSimple($security);
            break;
            
        case 'register':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleRegisterSimple($security);
            break;
            
        case 'logout':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleLogoutSimple($security);
            break;
            
        case 'me':
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            handleGetUserSimple($security);
            break;
            
        case 'csrf':
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            handleGetCSRFSimple($security);
            break;
            
        default:
            Response::notFound('Authentication endpoint not found');
    }
    
} catch (Exception $e) {
    Response::serverError('Authentication error: ' . $e->getMessage());
}

/**
 * Handle login
 */
function handleLoginSimple(Security $security): void
{
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        if (empty($input['login']) || empty($input['password'])) {
            Response::error('Email/username și parola sunt obligatorii', 400);
        }
        
        $login = $security->sanitizeInput($input['login']);
        $password = $input['password'];
        
        // Rate limiting
        $clientIP = $security->getClientIP();
        if (!$security->checkRateLimit($clientIP, 'login')) {
            Response::error('Prea multe încercări de autentificare. Încercați din nou mai târziu.', 429);
        }
        
        $db = Database::getInstance();
        $user = $db->queryOne(
            "SELECT * FROM users WHERE (email = ? OR username = ?) AND status != 'inactive'",
            [$login, $login]
        );
        
        if (!$user || !$security->verifyPassword($password, $user['password_hash'])) {
            Response::error('Date de autentificare invalide', 401);
        }
        
        if ($user['status'] !== 'active') {
            Response::error('Contul nu este activ. Contactați suportul.', 403);
        }
        
        // Update last login
        $db->update('users', [
            'last_login' => date('Y-m-d H:i:s')
        ], ['id' => $user['id']]);
        
        // Create session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Prepare user data
        $userData = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role'],
            'status' => $user['status']
        ];
        
        Response::success([
            'user' => $userData,
            'csrf_token' => $security->generateCSRFToken()
        ], 'Autentificare reușită');
        
    } catch (Exception $e) {
        Response::serverError('Eroare la autentificare: ' . $e->getMessage());
    }
}

/**
 * Handle registration
 */
function handleRegisterSimple(Security $security): void
{
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $required = ['username', 'email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                Response::error("Câmpul '$field' este obligatoriu", 400);
            }
        }
        
        $data = [
            'username' => $security->sanitizeInput($input['username']),
            'email' => $security->sanitizeInput($input['email'], 'email'),
            'password' => $input['password'],
            'first_name' => $security->sanitizeInput($input['first_name']),
            'last_name' => $security->sanitizeInput($input['last_name']),
            'phone' => $security->sanitizeInput($input['phone'] ?? '')
        ];
        
        // Validate password strength
        $passwordErrors = $security->validatePasswordStrength($data['password']);
        if (!empty($passwordErrors)) {
            Response::error('Parola nu îndeplinește cerințele: ' . implode(', ', $passwordErrors), 400);
        }
        
        $db = Database::getInstance();
        
        // Check if username exists
        if ($db->queryOne("SELECT id FROM users WHERE username = ?", [$data['username']])) {
            Response::error('Numele de utilizator există deja', 409);
        }
        
        // Check if email exists
        if ($db->queryOne("SELECT id FROM users WHERE email = ?", [$data['email']])) {
            Response::error('Email-ul este deja folosit', 409);
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
            'status' => 'active', // For now, activate immediately
            'email_verified' => true, // Skip email verification for now
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $userId = $db->insert('users', $userData);
        
        Response::success([
            'id' => $userId,
            'username' => $data['username'],
            'email' => $data['email'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name']
        ], 'Înregistrare reușită! Vă puteți autentifica acum.');
        
    } catch (Exception $e) {
        Response::serverError('Eroare la înregistrare: ' . $e->getMessage());
    }
}

/**
 * Handle logout
 */
function handleLogoutSimple(Security $security): void
{
    try {
        session_unset();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        
        Response::success(null, 'Deconectare reușită');
        
    } catch (Exception $e) {
        Response::serverError('Eroare la deconectare: ' . $e->getMessage());
    }
}

/**
 * Get current user
 */
function handleGetUserSimple(Security $security): void
{
    try {
        if (!isset($_SESSION['user_id'])) {
            Response::unauthorized('Nu sunteți autentificat');
        }
        
        $db = Database::getInstance();
        $user = $db->queryOne(
            "SELECT id, username, email, first_name, last_name, role, status, created_at, last_login FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
        
        if (!$user) {
            session_destroy();
            Response::unauthorized('Utilizatorul nu a fost găsit');
        }
        
        Response::success($user, 'Datele utilizatorului au fost încărcate');
        
    } catch (Exception $e) {
        Response::serverError('Eroare la încărcarea datelor: ' . $e->getMessage());
    }
}

/**
 * Get CSRF token
 */
function handleGetCSRFSimple(Security $security): void
{
    try {
        $token = $security->generateCSRFToken();
        Response::success(['csrf_token' => $token], 'Token CSRF generat');
    } catch (Exception $e) {
        Response::serverError('Eroare la generarea token-ului: ' . $e->getMessage());
    }
} 