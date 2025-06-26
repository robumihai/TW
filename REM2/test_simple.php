<?php
// Simple test to debug API issues

try {
    echo "Testing database connection...\n";
    
    $db_path = __DIR__ . '/database/rems.db';
    echo "Database path: $db_path\n";
    echo "Database exists: " . (file_exists($db_path) ? 'yes' : 'no') . "\n";
    echo "Database readable: " . (is_readable($db_path) ? 'yes' : 'no') . "\n";
    
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Database connection: OK\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM properties");
    $count = $stmt->fetchColumn();
    echo "Properties count: $count\n";
    
    $stmt = $pdo->query("SELECT id, title, price FROM properties LIMIT 3");
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Sample properties:\n";
    foreach ($properties as $property) {
        echo "- ID: {$property['id']}, Title: {$property['title']}, Price: {$property['price']}\n";
    }
    
    // Test the Property model
    echo "\nTesting Property model...\n";
    require_once __DIR__ . '/api/models/Database.php';
    require_once __DIR__ . '/api/models/Property.php';
    
    $database = Database::getInstance();
    echo "Database singleton: OK\n";
    
    $propertyModel = new Property($database);
    echo "Property model: OK\n";
    
    $result = $propertyModel->getAll(['limit' => 3]);
    echo "getAll() result: " . (is_array($result) ? count($result) . ' properties' : 'error') . "\n";
    
    echo "\nTest completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
} 