<?php
/**
 * Properties API endpoint
 * Handles CRUD operations for properties
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'database.php';

class PropertiesAPI {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function handleRequest() {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            
            switch ($method) {
                case 'GET':
                    $this->getProperties();
                    break;
                case 'POST':
                    $this->createProperty();
                    break;
                case 'PUT':
                    $this->updateProperty();
                    break;
                case 'DELETE':
                    $this->deleteProperty();
                    break;
                default:
                    $this->sendError('Method not allowed', 405);
            }
        } catch (Exception $e) {
            error_log('Properties API Error: ' . $e->getMessage());
            $this->sendError('Internal server error', 500);
        }
    }

    private function getProperties() {
        $filters = $this->getFilters();
        $sql = $this->buildQuery($filters);
        
        $stmt = $this->db->prepare($sql['query']);
        $stmt->execute($sql['params']);
        
        $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert numeric strings to proper types
        foreach ($properties as &$property) {
            $property['id'] = (int)$property['id'];
            $property['price'] = (float)$property['price'];
            $property['area'] = (float)$property['area'];
            $property['latitude'] = $property['latitude'] ? (float)$property['latitude'] : null;
            $property['longitude'] = $property['longitude'] ? (float)$property['longitude'] : null;
            $property['rooms'] = $property['rooms'] ? (int)$property['rooms'] : null;
        }
        
        $this->sendSuccess(['data' => $properties]);
    }

    private function getFilters() {
        return [
            'id' => isset($_GET['id']) ? (int)$_GET['id'] : null,
            'type' => isset($_GET['type']) ? Database::sanitizeInput($_GET['type']) : null,
            'transaction_type' => isset($_GET['transaction_type']) ? Database::sanitizeInput($_GET['transaction_type']) : null,
            'min_price' => isset($_GET['min_price']) ? (float)$_GET['min_price'] : null,
            'max_price' => isset($_GET['max_price']) ? (float)$_GET['max_price'] : null,
            'min_area' => isset($_GET['min_area']) ? (float)$_GET['min_area'] : null,
            'max_area' => isset($_GET['max_area']) ? (float)$_GET['max_area'] : null,
            'rooms' => isset($_GET['rooms']) ? (int)$_GET['rooms'] : null,
            'location_lat' => isset($_GET['lat']) ? (float)$_GET['lat'] : null,
            'location_lng' => isset($_GET['lng']) ? (float)$_GET['lng'] : null,
            'location_radius' => isset($_GET['radius']) ? (float)$_GET['radius'] : null,
            'search' => isset($_GET['search']) ? Database::sanitizeInput($_GET['search']) : null
        ];
    }

    private function buildQuery($filters) {
        $query = "SELECT * FROM properties WHERE 1=1";
        $params = [];

        if ($filters['id']) {
            $query .= " AND id = ?";
            $params[] = $filters['id'];
        }

        if ($filters['type']) {
            $query .= " AND type = ?";
            $params[] = $filters['type'];
        }

        if ($filters['transaction_type']) {
            $query .= " AND transaction_type = ?";
            $params[] = $filters['transaction_type'];
        }

        if ($filters['min_price']) {
            $query .= " AND price >= ?";
            $params[] = $filters['min_price'];
        }

        if ($filters['max_price']) {
            $query .= " AND price <= ?";
            $params[] = $filters['max_price'];
        }

        if ($filters['min_area']) {
            $query .= " AND area >= ?";
            $params[] = $filters['min_area'];
        }

        if ($filters['max_area']) {
            $query .= " AND area <= ?";
            $params[] = $filters['max_area'];
        }

        if ($filters['rooms']) {
            $query .= " AND rooms = ?";
            $params[] = $filters['rooms'];
        }

        if ($filters['search']) {
            $query .= " AND (title LIKE ? OR description LIKE ? OR address LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Location-based filtering (simple bounding box for performance)
        if ($filters['location_lat'] && $filters['location_lng'] && $filters['location_radius']) {
            $latDelta = $filters['location_radius'] / 111; // Rough km to degrees
            $lngDelta = $filters['location_radius'] / (111 * cos(deg2rad($filters['location_lat'])));
            
            $query .= " AND latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?";
            $params[] = $filters['location_lat'] - $latDelta;
            $params[] = $filters['location_lat'] + $latDelta;
            $params[] = $filters['location_lng'] - $lngDelta;
            $params[] = $filters['location_lng'] + $lngDelta;
        }

        $query .= " ORDER BY created_at DESC";

        return ['query' => $query, 'params' => $params];
    }

    private function createProperty() {
        $input = $this->getJsonInput();
        
        // Validate required fields
        $requiredFields = ['title', 'type', 'transaction_type', 'price', 'area', 'address'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                $this->sendError("Missing required field: $field", 400);
                return;
            }
        }

        // Validate field values
        if (!$this->validatePropertyType($input['type'])) {
            $this->sendError('Invalid property type', 400);
            return;
        }

        if (!$this->validateTransactionType($input['transaction_type'])) {
            $this->sendError('Invalid transaction type', 400);
            return;
        }

        if (!is_numeric($input['price']) || $input['price'] <= 0) {
            $this->sendError('Invalid price', 400);
            return;
        }

        if (!is_numeric($input['area']) || $input['area'] <= 0) {
            $this->sendError('Invalid area', 400);
            return;
        }

        // Sanitize inputs
        $data = [
            'title' => Database::sanitizeInput($input['title']),
            'description' => isset($input['description']) ? Database::sanitizeInput($input['description']) : null,
            'type' => Database::sanitizeInput($input['type']),
            'transaction_type' => Database::sanitizeInput($input['transaction_type']),
            'price' => (float)$input['price'],
            'area' => (float)$input['area'],
            'rooms' => isset($input['rooms']) ? (int)$input['rooms'] : null,
            'address' => Database::sanitizeInput($input['address']),
            'latitude' => isset($input['latitude']) ? (float)$input['latitude'] : null,
            'longitude' => isset($input['longitude']) ? (float)$input['longitude'] : null,
            'contact_info' => isset($input['contact_info']) ? Database::sanitizeInput($input['contact_info']) : null,
            'building_condition' => isset($input['building_condition']) ? Database::sanitizeInput($input['building_condition']) : null,
            'facilities' => isset($input['facilities']) ? Database::sanitizeInput($input['facilities']) : null,
            'risks' => isset($input['risks']) ? Database::sanitizeInput($input['risks']) : null,
            'images' => isset($input['images']) ? Database::sanitizeInput($input['images']) : null
        ];

        $sql = "INSERT INTO properties (title, description, type, transaction_type, price, area, 
                                      rooms, address, latitude, longitude, contact_info, 
                                      building_condition, facilities, risks, images)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            $data['title'], $data['description'], $data['type'], $data['transaction_type'],
            $data['price'], $data['area'], $data['rooms'], $data['address'],
            $data['latitude'], $data['longitude'], $data['contact_info'],
            $data['building_condition'], $data['facilities'], $data['risks'], $data['images']
        ]);

        if ($success) {
            $propertyId = $this->db->lastInsertId();
            $this->sendSuccess(['message' => 'Property created successfully', 'id' => $propertyId], 201);
        } else {
            $this->sendError('Failed to create property', 500);
        }
    }

    private function updateProperty() {
        $input = $this->getJsonInput();
        
        if (!isset($input['id'])) {
            $this->sendError('Property ID is required', 400);
            return;
        }

        $propertyId = (int)$input['id'];
        
        // Check if property exists
        $stmt = $this->db->prepare("SELECT id FROM properties WHERE id = ?");
        $stmt->execute([$propertyId]);
        if (!$stmt->fetch()) {
            $this->sendError('Property not found', 404);
            return;
        }

        // Build update query dynamically
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['title', 'description', 'type', 'transaction_type', 'price', 'area',
                         'rooms', 'address', 'latitude', 'longitude', 'contact_info',
                         'building_condition', 'facilities', 'risks', 'images'];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                if (in_array($field, ['price', 'area', 'latitude', 'longitude'])) {
                    $params[] = (float)$input[$field];
                } elseif ($field === 'rooms') {
                    $params[] = $input[$field] ? (int)$input[$field] : null;
                } else {
                    $params[] = Database::sanitizeInput($input[$field]);
                }
            }
        }

        if (empty($updateFields)) {
            $this->sendError('No fields to update', 400);
            return;
        }

        $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $propertyId;

        $sql = "UPDATE properties SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($params);

        if ($success) {
            $this->sendSuccess(['message' => 'Property updated successfully']);
        } else {
            $this->sendError('Failed to update property', 500);
        }
    }

    private function deleteProperty() {
        $input = $this->getJsonInput();
        
        if (!isset($input['id'])) {
            $this->sendError('Property ID is required', 400);
            return;
        }

        $propertyId = (int)$input['id'];
        
        $stmt = $this->db->prepare("DELETE FROM properties WHERE id = ?");
        $success = $stmt->execute([$propertyId]);

        if ($success && $stmt->rowCount() > 0) {
            $this->sendSuccess(['message' => 'Property deleted successfully']);
        } else {
            $this->sendError('Property not found or already deleted', 404);
        }
    }

    private function getJsonInput() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError('Invalid JSON input', 400);
            exit;
        }
        return $input;
    }

    private function validatePropertyType($type) {
        return in_array($type, ['apartment', 'house', 'commercial', 'land']);
    }

    private function validateTransactionType($type) {
        return in_array($type, ['sale', 'rent']);
    }

    private function sendSuccess($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode(array_merge(['success' => true], $data));
    }

    private function sendError($message, $statusCode = 400) {
        http_response_code($statusCode);
        echo json_encode(['success' => false, 'error' => $message]);
    }
}

// Handle the request
$api = new PropertiesAPI();
$api->handleRequest();
?> 