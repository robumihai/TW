<?php
/**
 * Export API endpoint
 * Handles data export in CSV and JSON formats
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'database.php';

class ExportAPI {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function handleRequest() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                $this->sendError('Method not allowed', 405);
                return;
            }

            $format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'json';
            
            if (!in_array($format, ['json', 'csv'])) {
                $this->sendError('Invalid format. Supported formats: json, csv', 400);
                return;
            }

            $this->exportData($format);
            
        } catch (Exception $e) {
            error_log('Export API Error: ' . $e->getMessage());
            $this->sendError('Internal server error', 500);
        }
    }

    private function exportData($format) {
        // Get all properties from database
        $stmt = $this->db->query("SELECT * FROM properties ORDER BY created_at DESC");
        $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Clean and format data
        foreach ($properties as &$property) {
            $property['id'] = (int)$property['id'];
            $property['price'] = (float)$property['price'];
            $property['area'] = (float)$property['area'];
            $property['latitude'] = $property['latitude'] ? (float)$property['latitude'] : null;
            $property['longitude'] = $property['longitude'] ? (float)$property['longitude'] : null;
            $property['rooms'] = $property['rooms'] ? (int)$property['rooms'] : null;
            
            // Remove null values to clean up export
            $property = array_filter($property, function($value) {
                return $value !== null && $value !== '';
            });
        }

        if ($format === 'json') {
            $this->exportJSON($properties);
        } else {
            $this->exportCSV($properties);
        }
    }

    private function exportJSON($properties) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="properties_' . date('Y-m-d_H-i-s') . '.json"');
        
        echo json_encode([
            'export_date' => date('Y-m-d H:i:s'),
            'total_properties' => count($properties),
            'data' => $properties
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function exportCSV($properties) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="properties_' . date('Y-m-d_H-i-s') . '.csv"');
        
        // Output BOM for proper UTF-8 encoding in Excel
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        if (empty($properties)) {
            fwrite($output, "No properties found\n");
            fclose($output);
            return;
        }

        // Define CSV headers
        $headers = [
            'ID',
            'Title',
            'Description',
            'Type',
            'Transaction Type',
            'Price',
            'Area (m²)',
            'Rooms',
            'Address',
            'Latitude',
            'Longitude',
            'Contact Info',
            'Building Condition',
            'Facilities',
            'Risks',
            'Images',
            'Created At',
            'Updated At'
        ];

        fputcsv($output, $headers);

        // Output data rows
        foreach ($properties as $property) {
            $row = [
                $property['id'] ?? '',
                $property['title'] ?? '',
                $property['description'] ?? '',
                $property['type'] ?? '',
                $property['transaction_type'] ?? '',
                $property['price'] ?? '',
                $property['area'] ?? '',
                $property['rooms'] ?? '',
                $property['address'] ?? '',
                $property['latitude'] ?? '',
                $property['longitude'] ?? '',
                $property['contact_info'] ?? '',
                $property['building_condition'] ?? '',
                $property['facilities'] ?? '',
                $property['risks'] ?? '',
                $property['images'] ?? '',
                $property['created_at'] ?? '',
                $property['updated_at'] ?? ''
            ];
            
            fputcsv($output, $row);
        }
        
        fclose($output);
    }

    private function sendError($message, $statusCode = 400) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
    }
}

// Handle the request
$api = new ExportAPI();
$api->handleRequest();
?> 