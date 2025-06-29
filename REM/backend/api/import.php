<?php
/**
 * Import API endpoint
 * Handles data import from CSV and JSON formats
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'database.php';

class ImportAPI {
    private $db;
    private $maxFileSize = 5 * 1024 * 1024; // 5MB limit
    private $allowedTypes = ['text/csv', 'application/json', 'text/plain'];

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function handleRequest() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->sendError('Method not allowed', 405);
                return;
            }

            // Check if file was uploaded
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $this->sendError('No file uploaded or upload error', 400);
                return;
            }

            $file = $_FILES['file'];
            
            // Validate file size
            if ($file['size'] > $this->maxFileSize) {
                $this->sendError('File size exceeds maximum limit (5MB)', 400);
                return;
            }

            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $this->allowedTypes)) {
                $this->sendError('Invalid file type. Only CSV and JSON files are allowed', 400);
                return;
            }

            // Determine format based on file extension
            $format = $this->getFileFormat($file['name']);
            
            if ($format === 'csv') {
                $this->importCSV($file['tmp_name']);
            } elseif ($format === 'json') {
                $this->importJSON($file['tmp_name']);
            } else {
                $this->sendError('Unsupported file format', 400);
            }

        } catch (Exception $e) {
            error_log('Import API Error: ' . $e->getMessage());
            $this->sendError('Internal server error: ' . $e->getMessage(), 500);
        }
    }

    private function getFileFormat($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $extension === 'csv' ? 'csv' : ($extension === 'json' ? 'json' : null);
    }

    private function importCSV($filePath) {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->sendError('Unable to read file', 500);
            return;
        }

        $imported = 0;
        $errors = [];
        $lineNumber = 0;

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            $this->sendError('Invalid CSV format - no headers found', 400);
            return;
        }

        // Map CSV headers to database fields
        $fieldMap = $this->mapCSVFields($headers);
        
        // Process data rows
        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            try {
                $data = $this->mapRowData($row, $fieldMap);
                
                if ($this->validatePropertyData($data)) {
                    if ($this->insertProperty($data)) {
                        $imported++;
                    } else {
                        $errors[] = "Line $lineNumber: Failed to insert property";
                    }
                } else {
                    $errors[] = "Line $lineNumber: Invalid data format";
                }
            } catch (Exception $e) {
                $errors[] = "Line $lineNumber: " . $e->getMessage();
            }
        }

        fclose($handle);

        $this->sendSuccess([
            'message' => 'Import completed',
            'imported' => $imported,
            'errors' => $errors,
            'total_processed' => $lineNumber
        ]);
    }

    private function importJSON($filePath) {
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError('Invalid JSON format', 400);
            return;
        }

        // Handle different JSON structures
        $properties = [];
        if (isset($data['data']) && is_array($data['data'])) {
            // Exported JSON format
            $properties = $data['data'];
        } elseif (is_array($data) && !empty($data)) {
            // Simple array of properties
            $properties = $data;
        } else {
            $this->sendError('Invalid JSON structure', 400);
            return;
        }

        $imported = 0;
        $errors = [];

        foreach ($properties as $index => $propertyData) {
            try {
                if ($this->validatePropertyData($propertyData)) {
                    if ($this->insertProperty($propertyData)) {
                        $imported++;
                    } else {
                        $errors[] = "Property $index: Failed to insert";
                    }
                } else {
                    $errors[] = "Property $index: Invalid data format";
                }
            } catch (Exception $e) {
                $errors[] = "Property $index: " . $e->getMessage();
            }
        }

        $this->sendSuccess([
            'message' => 'Import completed',
            'imported' => $imported,
            'errors' => $errors,
            'total_processed' => count($properties)
        ]);
    }

    private function mapCSVFields($headers) {
        $fieldMap = [];
        $dbFields = [
            'id' => ['id', 'ID'],
            'title' => ['title', 'Title', 'name', 'Name'],
            'description' => ['description', 'Description', 'desc'],
            'type' => ['type', 'Type', 'property_type', 'Property Type'],
            'transaction_type' => ['transaction_type', 'Transaction Type', 'transaction', 'Transaction'],
            'price' => ['price', 'Price'],
            'area' => ['area', 'Area', 'area_m2', 'Area (m²)', 'size'],
            'rooms' => ['rooms', 'Rooms', 'room_count'],
            'address' => ['address', 'Address', 'location', 'Location'],
            'latitude' => ['latitude', 'Latitude', 'lat'],
            'longitude' => ['longitude', 'Longitude', 'lng', 'lon'],
            'contact_info' => ['contact_info', 'Contact Info', 'contact', 'Contact'],
            'building_condition' => ['building_condition', 'Building Condition', 'condition'],
            'facilities' => ['facilities', 'Facilities', 'amenities'],
            'risks' => ['risks', 'Risks'],
            'images' => ['images', 'Images', 'photos']
        ];

        foreach ($headers as $index => $header) {
            foreach ($dbFields as $dbField => $variations) {
                if (in_array(trim($header), $variations)) {
                    $fieldMap[$dbField] = $index;
                    break;
                }
            }
        }

        return $fieldMap;
    }

    private function mapRowData($row, $fieldMap) {
        $data = [];
        
        foreach ($fieldMap as $dbField => $csvIndex) {
            if (isset($row[$csvIndex])) {
                $value = trim($row[$csvIndex]);
                $data[$dbField] = $value !== '' ? $value : null;
            }
        }

        return $data;
    }

    private function validatePropertyData($data) {
        // Required fields
        $requiredFields = ['title', 'type', 'transaction_type', 'price', 'area', 'address'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }

        // Validate property type
        if (!in_array($data['type'], ['apartment', 'house', 'commercial', 'land'])) {
            return false;
        }

        // Validate transaction type
        if (!in_array($data['transaction_type'], ['sale', 'rent'])) {
            return false;
        }

        // Validate numeric fields
        if (!is_numeric($data['price']) || $data['price'] <= 0) {
            return false;
        }

        if (!is_numeric($data['area']) || $data['area'] <= 0) {
            return false;
        }

        return true;
    }

    private function insertProperty($data) {
        // Skip if ID is provided and property already exists
        if (isset($data['id']) && !empty($data['id'])) {
            $stmt = $this->db->prepare("SELECT id FROM properties WHERE id = ?");
            $stmt->execute([$data['id']]);
            if ($stmt->fetch()) {
                return false; // Property already exists
            }
        }

        // Sanitize data
        $cleanData = [
            'title' => Database::sanitizeInput($data['title']),
            'description' => isset($data['description']) ? Database::sanitizeInput($data['description']) : null,
            'type' => Database::sanitizeInput($data['type']),
            'transaction_type' => Database::sanitizeInput($data['transaction_type']),
            'price' => (float)$data['price'],
            'area' => (float)$data['area'],
            'rooms' => isset($data['rooms']) && $data['rooms'] !== '' ? (int)$data['rooms'] : null,
            'address' => Database::sanitizeInput($data['address']),
            'latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? (float)$data['latitude'] : null,
            'longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? (float)$data['longitude'] : null,
            'contact_info' => isset($data['contact_info']) ? Database::sanitizeInput($data['contact_info']) : null,
            'building_condition' => isset($data['building_condition']) ? Database::sanitizeInput($data['building_condition']) : null,
            'facilities' => isset($data['facilities']) ? Database::sanitizeInput($data['facilities']) : null,
            'risks' => isset($data['risks']) ? Database::sanitizeInput($data['risks']) : null,
            'images' => isset($data['images']) ? Database::sanitizeInput($data['images']) : null
        ];

        $sql = "INSERT INTO properties (title, description, type, transaction_type, price, area, 
                                      rooms, address, latitude, longitude, contact_info, 
                                      building_condition, facilities, risks, images)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $cleanData['title'], $cleanData['description'], $cleanData['type'], 
            $cleanData['transaction_type'], $cleanData['price'], $cleanData['area'], 
            $cleanData['rooms'], $cleanData['address'], $cleanData['latitude'], 
            $cleanData['longitude'], $cleanData['contact_info'], $cleanData['building_condition'], 
            $cleanData['facilities'], $cleanData['risks'], $cleanData['images']
        ]);
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
$api = new ImportAPI();
$api->handleRequest();
?> 