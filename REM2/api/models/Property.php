<?php
/**
 * REMS - Real Estate Management System
 * Property Model - Enhanced for Stage 4
 * 
 * Complete property management with advanced search,
 * image handling, statistics, and business logic
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../utils/Security.php';

class Property
{
    private Database $db;
    private Security $security;
    private array $allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
    private int $maxImageSize = 5 * 1024 * 1024; // 5MB
    private string $uploadPath = '/assets/images/properties/';
    
    // Property types with Romanian translations
    private array $propertyTypes = [
        'apartment' => 'Apartament',
        'house' => 'Casă',
        'studio' => 'Studio',
        'villa' => 'Vilă',
        'duplex' => 'Duplex',
        'penthouse' => 'Penthouse',
        'office' => 'Birou',
        'commercial' => 'Spațiu comercial',
        'industrial' => 'Spațiu industrial',
        'land' => 'Teren',
        'garage' => 'Garaj',
        'warehouse' => 'Depozit'
    ];
    
    // Transaction types
    private array $transactionTypes = [
        'sale' => 'Vânzare',
        'rent' => 'Închiriere'
    ];
    
    // Property conditions
    private array $conditionTypes = [
        'new' => 'Nou',
        'excellent' => 'Excelent',
        'very_good' => 'Foarte bun',
        'good' => 'Bun',
        'renovated' => 'Renovat',
        'needs_renovation' => 'Necesită renovare',
        'undeveloped' => 'Nedezvolt'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->security = Security::getInstance();
    }

    /**
     * Create new property with enhanced validation
     */
    public function create(array $data): int
    {
        try {
            $this->validatePropertyData($data);
            
            // Generate slug
            $data['slug'] = $this->generateSlug($data['title']);
            
            // Set defaults
            $data['status'] = $data['status'] ?? 'draft';
            $data['featured'] = $data['featured'] ?? false;
            $data['view_count'] = 0;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // Handle JSON fields
            $data['utilities'] = isset($data['utilities']) ? json_encode($data['utilities']) : '[]';
            $data['amenities'] = isset($data['amenities']) ? json_encode($data['amenities']) : '[]';
            
            $propertyId = $this->db->insert('properties', $data);
            
            // Log activity
            $this->logActivity('property_created', $propertyId, $data['user_id']);
            
            return $propertyId;
            
        } catch (Exception $e) {
            error_log("Property creation failed: " . $e->getMessage());
            throw new InvalidArgumentException('Failed to create property: ' . $e->getMessage());
        }
    }

    /**
     * Update property with version control
     */
    public function update(int $id, array $data): bool
    {
        try {
            $existingProperty = $this->findById($id);
            if (!$existingProperty) {
                throw new InvalidArgumentException('Property not found');
            }

            // Validate updated data
            $this->validatePropertyData($data, $id);
            
            // Update slug if title changed
            if (isset($data['title']) && $data['title'] !== $existingProperty['title']) {
                $data['slug'] = $this->generateSlug($data['title'], $id);
            }
            
            // Handle JSON fields
            if (isset($data['utilities'])) {
                $data['utilities'] = json_encode($data['utilities']);
            }
            if (isset($data['amenities'])) {
                $data['amenities'] = json_encode($data['amenities']);
            }
            
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            $success = $this->db->update('properties', $data, ['id' => $id]);
            
            if ($success) {
                $this->logActivity('property_updated', $id, $data['user_id'] ?? $existingProperty['user_id']);
            }
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Property update failed: " . $e->getMessage());
            throw new InvalidArgumentException('Failed to update property: ' . $e->getMessage());
        }
    }

    /**
     * Delete property with cascade
     */
    public function delete(int $id): bool
    {
        try {
            $property = $this->findById($id);
            if (!$property) {
                return false;
            }

            $this->db->beginTransaction();

            // Delete related images
            $this->deletePropertyImages($id);
            
            // Delete from favorites
            $this->db->delete('favorites', ['property_id' => $id]);
            
            // Delete views
            $this->db->delete('property_views', ['property_id' => $id]);
            
            // Delete the property
            $success = $this->db->delete('properties', ['id' => $id]);
            
            if ($success) {
                $this->logActivity('property_deleted', $id, $property['user_id']);
                $this->db->commit();
            } else {
                $this->db->rollback();
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Property deletion failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find property by ID with complete data
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT p.*, 
                   u.name as agent_name, u.email as agent_email, u.phone as agent_phone,
                   (SELECT COUNT(*) FROM favorites WHERE property_id = p.id) as favorite_count,
                   (SELECT filename FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as image_count
            FROM properties p 
            LEFT JOIN users u ON p.user_id = u.id 
            WHERE p.id = ?
        ";
        
        $property = $this->db->queryOne($sql, [$id]);
        
        if ($property) {
            // Decode JSON fields
            $property['utilities'] = json_decode($property['utilities'] ?? '[]', true);
            $property['amenities'] = json_decode($property['amenities'] ?? '[]', true);
            
            // Add human-readable fields
            $property['property_type_label'] = $this->propertyTypes[$property['property_type']] ?? $property['property_type'];
            $property['transaction_type_label'] = $this->transactionTypes[$property['transaction_type']] ?? $property['transaction_type'];
            $property['condition_type_label'] = $this->conditionTypes[$property['condition_type']] ?? $property['condition_type'];
            
            // Increment view count
            $this->incrementViewCount($id);
        }
        
        return $property;
    }

    /**
     * Get all properties with filtering, sorting and pagination
     */
    public function getAll(array $options = []): array
    {
        $filters = $options['filters'] ?? [];
        $page = max(1, $options['page'] ?? 1);
        $limit = min(100, max(1, $options['limit'] ?? 12));
        $sort = $options['sort'] ?? 'newest';
        $offset = ($page - 1) * $limit;

        // Build WHERE clause
        $where = ['p.status = ?'];
        $params = ['active'];

        // Apply filters
        if (!empty($filters['search'])) {
            $where[] = "(p.title LIKE ? OR p.description LIKE ? OR p.address LIKE ? OR p.city LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (!empty($filters['property_type'])) {
            $where[] = "p.property_type = ?";
            $params[] = $filters['property_type'];
        }

        if (!empty($filters['transaction_type'])) {
            $where[] = "p.transaction_type = ?";
            $params[] = $filters['transaction_type'];
        }

        if (!empty($filters['city'])) {
            $where[] = "p.city = ?";
            $params[] = $filters['city'];
        }

        if (!empty($filters['min_price'])) {
            $where[] = "p.price >= ?";
            $params[] = (float)$filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $where[] = "p.price <= ?";
            $params[] = (float)$filters['max_price'];
        }

        if (!empty($filters['rooms'])) {
            $where[] = "p.rooms = ?";
            $params[] = (int)$filters['rooms'];
        }

        if (!empty($filters['bedrooms'])) {
            $where[] = "p.bedrooms = ?";
            $params[] = (int)$filters['bedrooms'];
        }

        if (!empty($filters['bathrooms'])) {
            $where[] = "p.bathrooms = ?";
            $params[] = (int)$filters['bathrooms'];
        }

        if (!empty($filters['min_surface'])) {
            $where[] = "p.surface_useful >= ?";
            $params[] = (float)$filters['min_surface'];
        }

        if (!empty($filters['max_surface'])) {
            $where[] = "p.surface_useful <= ?";
            $params[] = (float)$filters['max_surface'];
        }

        if (!empty($filters['construction_year_min'])) {
            $where[] = "p.construction_year >= ?";
            $params[] = (int)$filters['construction_year_min'];
        }

        if (!empty($filters['construction_year_max'])) {
            $where[] = "p.construction_year <= ?";
            $params[] = (int)$filters['construction_year_max'];
        }

        if (!empty($filters['featured'])) {
            $where[] = "p.featured = ?";
            $params[] = $filters['featured'] ? 1 : 0;
        }

        // Build ORDER BY clause
        $orderBy = match($sort) {
            'price_asc' => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'surface_asc' => 'p.surface_useful ASC',
            'surface_desc' => 'p.surface_useful DESC',
            'oldest' => 'p.created_at ASC',
            'views' => 'p.view_count DESC',
            'featured' => 'p.featured DESC, p.created_at DESC',
            default => 'p.created_at DESC'
        };

        // Count total results
        $countSql = "
            SELECT COUNT(*) 
            FROM properties p 
            LEFT JOIN users u ON p.user_id = u.id 
            WHERE " . implode(' AND ', $where);
        
        $total = $this->db->queryScalar($countSql, $params);

        // Get properties
        $sql = "
            SELECT p.*, 
                   u.name as agent_name, u.email as agent_email, u.phone as agent_phone,
                   (SELECT COUNT(*) FROM favorites WHERE property_id = p.id) as favorite_count,
                   (SELECT filename FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as image_count
            FROM properties p 
            LEFT JOIN users u ON p.user_id = u.id 
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";

        $properties = $this->db->query($sql, $params);

        // Process results
        foreach ($properties as &$property) {
            // Decode JSON fields
            $property['utilities'] = json_decode($property['utilities'] ?? '[]', true);
            $property['amenities'] = json_decode($property['amenities'] ?? '[]', true);
            
            // Add human-readable fields
            $property['property_type_label'] = $this->propertyTypes[$property['property_type']] ?? $property['property_type'];
            $property['transaction_type_label'] = $this->transactionTypes[$property['transaction_type']] ?? $property['transaction_type'];
            $property['condition_type_label'] = $this->conditionTypes[$property['condition_type']] ?? $property['condition_type'];
        }

        // Calculate pagination
        $totalPages = ceil($total / $limit);

        return [
            'data' => $properties,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'limit' => $limit,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages
            ]
        ];
    }

    /**
     * Search properties with advanced filters
     */
    public function search(array $filters = [], array $options = []): array
    {
        return $this->getAll(['filters' => $filters] + $options);
    }

    /**
     * Get property statistics
     */
    public function getStatistics(): array
    {
        $stats = [];

        // Total properties
        $stats['total_properties'] = $this->db->queryScalar("SELECT COUNT(*) FROM properties WHERE status = 'active'");

        // Properties by type
        $sql = "SELECT property_type, COUNT(*) as count FROM properties WHERE status = 'active' GROUP BY property_type";
        $typeStats = $this->db->query($sql);
        $stats['by_type'] = [];
        foreach ($typeStats as $stat) {
            $stats['by_type'][$stat['property_type']] = [
                'count' => $stat['count'],
                'label' => $this->propertyTypes[$stat['property_type']] ?? $stat['property_type']
            ];
        }

        // Properties by transaction type
        $sql = "SELECT transaction_type, COUNT(*) as count FROM properties WHERE status = 'active' GROUP BY transaction_type";
        $transactionStats = $this->db->query($sql);
        $stats['by_transaction'] = [];
        foreach ($transactionStats as $stat) {
            $stats['by_transaction'][$stat['transaction_type']] = [
                'count' => $stat['count'],
                'label' => $this->transactionTypes[$stat['transaction_type']] ?? $stat['transaction_type']
            ];
        }

        // Price statistics
        $sql = "
            SELECT 
                MIN(price) as min_price,
                MAX(price) as max_price,
                AVG(price) as avg_price,
                transaction_type
            FROM properties 
            WHERE status = 'active' AND price > 0
            GROUP BY transaction_type
        ";
        $priceStats = $this->db->query($sql);
        $stats['price'] = [];
        foreach ($priceStats as $stat) {
            $stats['price'][$stat['transaction_type']] = [
                'min' => (float)$stat['min_price'],
                'max' => (float)$stat['max_price'],
                'avg' => (float)$stat['avg_price']
            ];
        }

        // Top cities
        $sql = "
            SELECT city, COUNT(*) as count 
            FROM properties 
            WHERE status = 'active' 
            GROUP BY city 
            ORDER BY count DESC 
            LIMIT 10
        ";
        $stats['top_cities'] = $this->db->query($sql);

        // Recent properties
        $sql = "
            SELECT COUNT(*) as count 
            FROM properties 
            WHERE status = 'active' 
            AND created_at >= date('now', '-7 days')
        ";
        $stats['recent_week'] = $this->db->queryScalar($sql);

        return $stats;
    }

    /**
     * Get all unique cities from properties
     */
    public function getCities(): array
    {
        $sql = "
            SELECT DISTINCT city 
            FROM properties 
            WHERE status = 'active' AND city IS NOT NULL AND city != ''
            ORDER BY city ASC
        ";
        
        $results = $this->db->query($sql);
        return array_column($results, 'city');
    }

    /**
     * Get featured properties
     */
    public function getFeatured(int $limit = 6): array
    {
        return $this->getAll([
            'filters' => ['featured' => true],
            'limit' => $limit,
            'sort' => 'featured'
        ])['data'];
    }

    /**
     * Get similar properties
     */
    public function getSimilar(int $propertyId, int $limit = 4): array
    {
        $property = $this->findById($propertyId);
        if (!$property) {
            return [];
        }

        $filters = [
            'property_type' => $property['property_type'],
            'transaction_type' => $property['transaction_type']
        ];

        // Add price range (±30%)
        if ($property['price'] > 0) {
            $filters['min_price'] = $property['price'] * 0.7;
            $filters['max_price'] = $property['price'] * 1.3;
        }

        $result = $this->getAll([
            'filters' => $filters,
            'limit' => $limit + 1, // Get one extra to exclude current property
            'sort' => 'newest'
        ]);

        // Remove current property from results
        $similar = array_filter($result['data'], function($p) use ($propertyId) {
            return $p['id'] != $propertyId;
        });

        return array_slice($similar, 0, $limit);
    }

    // Private helper methods
    private function validatePropertyData(array $data, ?int $existingId = null): void
    {
        $required = ['title', 'property_type', 'transaction_type', 'price', 'address', 'city'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Field '$field' is required");
            }
        }
        
        // Validate property type
        if (!array_key_exists($data['property_type'], $this->propertyTypes)) {
            throw new InvalidArgumentException("Invalid property type");
        }
        
        // Validate transaction type
        if (!array_key_exists($data['transaction_type'], $this->transactionTypes)) {
            throw new InvalidArgumentException("Invalid transaction type");
        }
        
        // Validate price
        if (!is_numeric($data['price']) || $data['price'] < 0) {
            throw new InvalidArgumentException("Invalid price");
        }
    }

    private function generateSlug(string $title, ?int $excludeId = null): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;
        
        while (true) {
            $sql = "SELECT id FROM properties WHERE slug = ?";
            $params = [$slug];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $existing = $this->db->queryOne($sql, $params);
            
            if (!$existing) {
                break;
            }
            
            $slug = $originalSlug . '-' . $counter++;
        }
        
        return $slug;
    }

    private function incrementViewCount(int $propertyId): void
    {
        $this->db->query(
            "UPDATE properties SET view_count = view_count + 1 WHERE id = ?",
            [$propertyId]
        );
    }

    private function deletePropertyImages(int $propertyId): void
    {
        // Delete image files and records
        $images = $this->db->query("SELECT * FROM property_images WHERE property_id = ?", [$propertyId]);
        foreach ($images as $image) {
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . $this->uploadPath . $image['filename'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        $this->db->delete('property_images', ['property_id' => $propertyId]);
    }

    private function logActivity(string $action, int $propertyId, int $userId): void
    {
        $this->db->insert('activity_logs', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => 'property',
            'entity_id' => $propertyId,
            'ip_address' => $this->security->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
