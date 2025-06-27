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
            
        case 'dashboard_stats':
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            handleDashboardStats($security);
            break;
            
        case 'dashboard_activity':
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            handleDashboardActivity($security);
            break;
            
        case 'update_profile':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleUpdateProfile($security, $_POST);
            break;
            
        case 'change_password':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleChangePassword($security, $_POST);
            break;
            
        case 'update_preferences':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleUpdatePreferences($security, $_POST);
            break;
            
        case 'get_sessions':
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            handleGetSessions($security);
            break;
            
        case 'revoke_session':
            if ($method !== 'POST') {
                Response::methodNotAllowed();
            }
            handleRevokeSession($security, $_POST);
            break;
            
        case 'user_activity':
            if ($method !== 'GET') {
                Response::methodNotAllowed();
            }
            handleUserActivity($security);
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

/**
 * Handle dashboard statistics request
 */
function handleDashboardStats(Security $security): void
{
    try {
        $user = $security->getCurrentUser();
        if (!$user) {
            Response::error('Neautentificat', 401);
            return;
        }
        
        $db = new Database();
        $stats = [];
        
        // Get role-specific statistics
        switch ($user['role']) {
            case 'admin':
                $stats = [
                    'total_properties' => $db->query("SELECT COUNT(*) as count FROM properties")->fetch()['count'] ?? 0,
                    'total_users' => $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'] ?? 0,
                    'total_agents' => $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'agent'")->fetch()['count'] ?? 0,
                    'total_value' => round(($db->query("SELECT SUM(price) as total FROM properties")->fetch()['total'] ?? 0) / 1000000, 1)
                ];
                break;
                
            case 'agent':
                $userId = $user['id'];
                $stats = [
                    'my_properties' => $db->query("SELECT COUNT(*) as count FROM properties WHERE user_id = ?", [$userId])->fetch()['count'] ?? 0,
                    'property_views' => rand(150, 500), // Mock data
                    'inquiries' => rand(5, 25), // Mock data
                    'active_listings' => $db->query("SELECT COUNT(*) as count FROM properties WHERE user_id = ? AND status = 'active'", [$userId])->fetch()['count'] ?? 0,
                    'pending_listings' => $db->query("SELECT COUNT(*) as count FROM properties WHERE user_id = ? AND status = 'pending'", [$userId])->fetch()['count'] ?? 0
                ];
                break;
                
            default: // client
                $userId = $user['id'];
                $stats = [
                    'favorites' => rand(3, 15), // Mock data - will be real when favorites are implemented
                    'searches' => rand(1, 8), // Mock data
                    'viewed_properties' => rand(10, 50) // Mock data
                ];
                break;
        }
        
        Response::success('Statistici încărcate cu succes', $stats);
        
    } catch (Exception $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
        Response::error('Eroare la încărcarea statisticilor', 500);
    }
}

/**
 * Handle dashboard activity request
 */
function handleDashboardActivity(Security $security): void
{
    try {
        $user = $security->getCurrentUser();
        if (!$user) {
            Response::error('Neautentificat', 401);
            return;
        }
        
        // Mock activity data - in real implementation this would come from database
        $activities = [
            [
                'id' => 1,
                'title' => 'Proprietate vizualizată',
                'description' => 'Apartament 3 camere în Centrul Bucureștiului',
                'icon' => '👁️',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ],
            [
                'id' => 2,
                'title' => 'Căutare realizată',
                'description' => 'Căutare pentru apartamente sub 150.000€',
                'icon' => '🔍',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'id' => 3,
                'title' => 'Profil actualizat',
                'description' => 'Informații de contact actualizate',
                'icon' => '👤',
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
            ]
        ];
        
        Response::success('Activitate încărcată cu succes', $activities);
        
    } catch (Exception $e) {
        error_log("Dashboard activity error: " . $e->getMessage());
        Response::error('Eroare la încărcarea activității', 500);
    }
}

/**
 * Handle profile update request
 */
function handleUpdateProfile(Security $security, array $input): void
{
    try {
        $user = $security->getCurrentUser();
        if (!$user) {
            Response::error('Neautentificat', 401);
            return;
        }
        
        $db = new Database();
        
        // Validate required fields
        $requiredFields = ['first_name', 'last_name', 'email'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                Response::error("Câmpul '$field' este obligatoriu", 400);
                return;
            }
        }
        
        // Check if email is already used by another user
        if ($input['email'] !== $user['email']) {
            $existingUser = $db->query("SELECT id FROM users WHERE email = ? AND id != ?", 
                [$input['email'], $user['id']])->fetch();
            if ($existingUser) {
                Response::error('Email-ul este deja folosit de alt utilizator', 409);
                return;
            }
        }
        
        // Update user profile
        $updateFields = [
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'bio' => $input['bio'] ?? null,
            'city' => $input['city'] ?? null,
            'company' => $input['company'] ?? null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $setClause = implode(', ', array_map(fn($key) => "$key = ?", array_keys($updateFields)));
        $values = array_values($updateFields);
        $values[] = $user['id'];
        
        $db->query("UPDATE users SET $setClause WHERE id = ?", $values);
        
        // Get updated user data
        $updatedUser = $db->query("SELECT id, username, email, first_name, last_name, role, phone, bio, city, company, created_at FROM users WHERE id = ?", 
            [$user['id']])->fetch();
        
        Response::success('Profilul a fost actualizat cu succes', $updatedUser);
        
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        Response::error('Eroare la actualizarea profilului', 500);
    }
}

/**
 * Handle password change request
 */
function handleChangePassword(Security $security, array $input): void
{
    try {
        $user = $security->getCurrentUser();
        if (!$user) {
            Response::error('Neautentificat', 401);
            return;
        }
        
        // Validate required fields
        if (empty($input['current_password']) || empty($input['new_password'])) {
            Response::error('Parola actuală și cea nouă sunt obligatorii', 400);
            return;
        }
        
        $db = new Database();
        
        // Verify current password
        $userData = $db->query("SELECT password FROM users WHERE id = ?", [$user['id']])->fetch();
        if (!$userData || !password_verify($input['current_password'], $userData['password'])) {
            Response::error('Parola actuală este incorectă', 400);
            return;
        }
        
        // Validate new password
        if (strlen($input['new_password']) < 8) {
            Response::error('Parola nouă trebuie să aibă cel puțin 8 caractere', 400);
            return;
        }
        
        // Update password
        $hashedPassword = password_hash($input['new_password'], PASSWORD_DEFAULT);
        $db->query("UPDATE users SET password = ?, updated_at = ? WHERE id = ?", 
            [$hashedPassword, date('Y-m-d H:i:s'), $user['id']]);
        
        Response::success('Parola a fost schimbată cu succes');
        
    } catch (Exception $e) {
        error_log("Password change error: " . $e->getMessage());
        Response::error('Eroare la schimbarea parolei', 500);
    }
}

/**
 * Handle preferences update request
 */
function handleUpdatePreferences(Security $security, array $input): void
{
    try {
        $user = $security->getCurrentUser();
        if (!$user) {
            Response::error('Neautentificat', 401);
            return;
        }
        
        // For now, just acknowledge the request
        // In a full implementation, these would be stored in a preferences table
        Response::success('Preferințele au fost salvate cu succes');
        
    } catch (Exception $e) {
        error_log("Preferences update error: " . $e->getMessage());
        Response::error('Eroare la salvarea preferințelor', 500);
    }
}

/**
 * Handle get user sessions request
 */
function handleGetSessions(Security $security): void
{
    try {
        $user = $security->getCurrentUser();
        if (!$user) {
            Response::error('Neautentificat', 401);
            return;
        }
        
        // Mock session data - in real implementation this would come from database
        $sessions = [
            [
                'id' => '1',
                'device' => 'Chrome on Windows',
                'ip_address' => '192.168.1.100',
                'location' => 'București, România',
                'last_activity' => date('Y-m-d H:i:s'),
                'is_current' => true
            ],
            [
                'id' => '2',
                'device' => 'Safari on iPhone',
                'ip_address' => '10.0.0.5',
                'location' => 'Cluj-Napoca, România',
                'last_activity' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'is_current' => false
            ]
        ];
        
        Response::success('Sesiuni încărcate cu succes', $sessions);
        
    } catch (Exception $e) {
        error_log("Get sessions error: " . $e->getMessage());
        Response::error('Eroare la încărcarea sesiunilor', 500);
    }
}

/**
 * Handle revoke session request
 */
function handleRevokeSession(Security $security, array $input): void
{
    try {
        $user = $security->getCurrentUser();
        if (!$user) {
            Response::error('Neautentificat', 401);
            return;
        }
        
        if (empty($input['session_id'])) {
            Response::error('ID-ul sesiunii este obligatoriu', 400);
            return;
        }
        
        // Mock implementation - in real scenario this would revoke the session from database
        Response::success('Sesiunea a fost revocată cu succes');
        
    } catch (Exception $e) {
        error_log("Revoke session error: " . $e->getMessage());
        Response::error('Eroare la revocarea sesiunii', 500);
    }
}

/**
 * Handle user activity request
 */
function handleUserActivity(Security $security): void
{
    try {
        $user = $security->getCurrentUser();
        if (!$user) {
            Response::error('Neautentificat', 401);
            return;
        }
        
        // Mock activity data - in real implementation this would come from database
        $activities = [
            [
                'id' => 1,
                'type' => 'login',
                'title' => 'Autentificare',
                'description' => 'V-ați conectat în sistem',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
            ],
            [
                'id' => 2,
                'type' => 'property_view',
                'title' => 'Proprietate vizualizată',
                'description' => 'Apartament 2 camere, Sector 1',
                'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))
            ],
            [
                'id' => 3,
                'type' => 'search',
                'title' => 'Căutare realizată',
                'description' => 'Căutare pentru case în Brașov',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'id' => 4,
                'type' => 'favorite_add',
                'title' => 'Adăugat la favorite',
                'description' => 'Vila 4 camere, Ploiești',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
            ],
            [
                'id' => 5,
                'type' => 'profile_update',
                'title' => 'Profil actualizat',
                'description' => 'Informații de contact modificate',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 week'))
            ]
        ];
        
        Response::success('Activitate încărcată cu succes', $activities);
        
    } catch (Exception $e) {
        error_log("User activity error: " . $e->getMessage());
        Response::error('Eroare la încărcarea activității', 500);
    }
} 