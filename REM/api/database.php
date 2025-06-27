<?php
/**
 * Database configuration and initialization
 * Real Estate Management System
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $dbPath;

    private function __construct() {
        $this->dbPath = __DIR__ . '/../data/rem.db';
        $this->initializeDatabase();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeDatabase() {
        try {
            // Create data directory if it doesn't exist
            $dataDir = dirname($this->dbPath);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }

            // Create PDO connection
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Enable foreign key constraints
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
            // Create tables
            $this->createTables();
            
            // Insert sample data if database is empty
            $this->insertSampleData();
            
        } catch (PDOException $e) {
            error_log('Database initialization error: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }

    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS properties (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            type TEXT NOT NULL CHECK (type IN ('apartment', 'house', 'commercial', 'land')),
            transaction_type TEXT NOT NULL CHECK (transaction_type IN ('sale', 'rent')),
            price REAL NOT NULL,
            area REAL NOT NULL,
            rooms INTEGER,
            address TEXT NOT NULL,
            latitude REAL,
            longitude REAL,
            contact_info TEXT,
            building_condition TEXT CHECK (building_condition IN ('new', 'good', 'renovated', 'needs_renovation', 'poor')),
            facilities TEXT,
            risks TEXT,
            images TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT DEFAULT 'user' CHECK (role IN ('admin', 'user')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_properties_type ON properties(type);
        CREATE INDEX IF NOT EXISTS idx_properties_transaction ON properties(transaction_type);
        CREATE INDEX IF NOT EXISTS idx_properties_price ON properties(price);
        CREATE INDEX IF NOT EXISTS idx_properties_area ON properties(area);
        CREATE INDEX IF NOT EXISTS idx_properties_location ON properties(latitude, longitude);
        ";

        $this->pdo->exec($sql);
    }

    private function insertSampleData() {
        // Check if data already exists
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM properties');
        if ($stmt->fetchColumn() > 0) {
            return; // Data already exists
        }

        // Insert sample properties
        $sampleProperties = [
            [
                'title' => 'Modern Apartment in City Center',
                'description' => 'Beautiful modern apartment with great city views. Recently renovated with high-quality finishes.',
                'type' => 'apartment',
                'transaction_type' => 'sale',
                'price' => 250000,
                'area' => 85,
                'rooms' => 3,
                'address' => 'Calea Victoriei 123, Bucharest',
                'latitude' => 44.4268,
                'longitude' => 26.1025,
                'contact_info' => 'Phone: +40 123 456 789, Email: contact@realestate.ro',
                'building_condition' => 'renovated',
                'facilities' => 'Air conditioning, Parking, Balcony, Central heating',
                'risks' => 'None reported'
            ],
            [
                'title' => 'Spacious Family House',
                'description' => 'Large family house with garden, perfect for families with children.',
                'type' => 'house',
                'transaction_type' => 'sale',
                'price' => 450000,
                'area' => 180,
                'rooms' => 5,
                'address' => 'Strada Florilor 45, Bucharest',
                'latitude' => 44.4100,
                'longitude' => 26.0900,
                'contact_info' => 'Phone: +40 987 654 321, Email: houses@realestate.ro',
                'building_condition' => 'good',
                'facilities' => 'Garden, Garage, Fireplace, Terrace',
                'risks' => 'Minor foundation settling observed'
            ],
            [
                'title' => 'Luxury Apartment for Rent',
                'description' => 'Fully furnished luxury apartment in premium location.',
                'type' => 'apartment',
                'transaction_type' => 'rent',
                'price' => 1200,
                'area' => 95,
                'rooms' => 2,
                'address' => 'Bulevardul Magheru 78, Bucharest',
                'latitude' => 44.4400,
                'longitude' => 26.0950,
                'contact_info' => 'Phone: +40 555 123 456, Email: rentals@realestate.ro',
                'building_condition' => 'new',
                'facilities' => 'Fully furnished, Air conditioning, Elevator, Security',
                'risks' => 'None'
            ],
            [
                'title' => 'Commercial Space Downtown',
                'description' => 'Prime commercial location suitable for retail or office use.',
                'type' => 'commercial',
                'transaction_type' => 'rent',
                'price' => 2500,
                'area' => 120,
                'rooms' => null,
                'address' => 'Piața Universității 12, Bucharest',
                'latitude' => 44.4350,
                'longitude' => 26.1000,
                'contact_info' => 'Phone: +40 777 888 999, Email: commercial@realestate.ro',
                'building_condition' => 'good',
                'facilities' => 'Street access, Display windows, Storage room',
                'risks' => 'High foot traffic area - noise consideration'
            ],
            [
                'title' => 'Building Plot for Sale',
                'description' => 'Excellent building plot in developing residential area.',
                'type' => 'land',
                'transaction_type' => 'sale',
                'price' => 180000,
                'area' => 800,
                'rooms' => null,
                'address' => 'Strada Constructorilor, Voluntari',
                'latitude' => 44.4800,
                'longitude' => 26.1300,
                'contact_info' => 'Phone: +40 333 444 555, Email: land@realestate.ro',
                'building_condition' => null,
                'facilities' => 'Utilities available, Road access',
                'risks' => 'Flood risk assessment recommended'
            ]
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO properties (title, description, type, transaction_type, price, area, 
                                  rooms, address, latitude, longitude, contact_info, 
                                  building_condition, facilities, risks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($sampleProperties as $property) {
            $stmt->execute([
                $property['title'],
                $property['description'],
                $property['type'],
                $property['transaction_type'],
                $property['price'],
                $property['area'],
                $property['rooms'],
                $property['address'],
                $property['latitude'],
                $property['longitude'],
                $property['contact_info'],
                $property['building_condition'],
                $property['facilities'],
                $property['risks']
            ]);
        }

        // Insert default admin user
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, email, password_hash, role)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute(['admin', 'admin@realestate.ro', $adminPassword, 'admin']);
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }

    public function exec($sql) {
        return $this->pdo->exec($sql);
    }

    public function query($sql) {
        return $this->pdo->query($sql);
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    // Security helper functions
    public static function sanitizeInput($input) {
        if (is_string($input)) {
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
        return $input;
    }

    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
}

// Initialize database on include
try {
    Database::getInstance();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database initialization failed']);
    exit;
}
?> 