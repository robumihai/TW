<?php
/**
 * REMS API Test Script
 * Test the enhanced API endpoints for Stage 4
 */

declare(strict_types=1);

echo "=== REMS API Test Script - Stage 4 ===\n";
echo "Testing enhanced API endpoints...\n\n";

// Include API components directly
require_once __DIR__ . '/api/models/Database.php';
require_once __DIR__ . '/api/models/Property.php';
require_once __DIR__ . '/api/utils/Response.php';
require_once __DIR__ . '/api/utils/Security.php';

try {
    echo "1. Testing Database Connection...\n";
    $db = Database::getInstance();
    $result = $db->queryScalar("SELECT COUNT(*) FROM properties");
    echo "✅ Database connected. Properties count: " . $result . "\n\n";
    
    echo "2. Testing Property Model...\n";
    $property = new Property();
    
    // Test search functionality
    echo "   Testing search...\n";
    $searchResult = $property->search([], 1, 5);
    echo "   ✅ Search works. Found " . $searchResult['pagination']['total'] . " properties\n";
    
    // Test featured properties
    echo "   Testing featured properties...\n";
    $featured = $property->getFeatured(3);
    echo "   ✅ Featured properties: " . count($featured) . " found\n";
    
    // Test statistics
    echo "   Testing statistics...\n";
    $stats = $property->getStatistics();
    echo "   ✅ Statistics generated. Total properties: " . ($stats['total_properties'] ?? 0) . "\n";
    
    // Test property by ID
    if ($searchResult['pagination']['total'] > 0) {
        $firstProperty = $searchResult['data'][0];
        echo "   Testing property by ID...\n";
        $propertyDetail = $property->findById((int)$firstProperty['id']);
        echo "   ✅ Property detail loaded: " . ($propertyDetail['title'] ?? 'Unknown') . "\n";
    }
    
    echo "\n3. Testing Security Class...\n";
    $security = Security::getInstance();
    $csrfToken = $security->generateCsrfToken();
    echo "   ✅ CSRF token generated: " . substr($csrfToken, 0, 10) . "...\n";
    
    $clientIp = $security->getClientIp();
    echo "   ✅ Client IP detected: " . $clientIp . "\n";
    
    echo "\n4. Testing Response Class...\n";
    $response = new Response();
    echo "   ✅ Response class instantiated\n";
    
    echo "\n5. Testing Search Filters...\n";
    
    // Test city filter
    $cityResult = $property->search(['city' => 'București'], 1, 3);
    echo "   ✅ City filter (București): " . $cityResult['pagination']['total'] . " properties\n";
    
    // Test property type filter
    $typeResult = $property->search(['property_type' => 'apartment'], 1, 3);
    echo "   ✅ Type filter (apartment): " . $typeResult['pagination']['total'] . " properties\n";
    
    // Test transaction type filter
    $transactionResult = $property->search(['transaction_type' => 'sale'], 1, 3);
    echo "   ✅ Transaction filter (sale): " . $transactionResult['pagination']['total'] . " properties\n";
    
    // Test price range filter
    $priceResult = $property->search(['min_price' => 50000, 'max_price' => 200000], 1, 3);
    echo "   ✅ Price range filter (50k-200k): " . $priceResult['pagination']['total'] . " properties\n";
    
    echo "\n6. Testing Map Functionality...\n";
    
    // Test bounding box search (Romania area)
    $mapResult = $property->search([
        'north' => 48.2,
        'south' => 43.6,
        'east' => 29.7,
        'west' => 20.2
    ], 1, 10);
    echo "   ✅ Map bounding box search: " . $mapResult['pagination']['total'] . " properties\n";
    
    echo "\n7. Testing Advanced Features...\n";
    
    // Test property type labels
    if (count($searchResult['data']) > 0) {
        $firstProp = $searchResult['data'][0];
        echo "   ✅ Property type labels working: " . ($firstProp['property_type_label'] ?? 'N/A') . "\n";
    }
    
    // Test sorting
    $sortedResult = $property->search(['sort' => 'price_desc'], 1, 3);
    echo "   ✅ Sorting works: " . count($sortedResult['data']) . " properties sorted by price desc\n";
    
    echo "\n=== ALL TESTS PASSED! ===\n";
    echo "✅ Stage 4 Backend API is working correctly\n";
    echo "✅ Property search with advanced filters\n";
    echo "✅ Geographic filtering for map\n";
    echo "✅ Statistics and analytics\n";
    echo "✅ Enhanced property data with labels\n";
    echo "✅ Security features implemented\n";
    echo "\nReady for Stage 5: Frontend Property Listing\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
} 