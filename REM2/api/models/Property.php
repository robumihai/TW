<?php
/**
 * REMS - Real Estate Management System
 * Property Model
 * 
 * Handles all property-related database operations with validation and security
 */

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

class Property
{
    private Database $db;
    
    // Property type constants
    public const PROPERTY_TYPES = [
        'apartament', 'casa', 'teren', 'comercial', 'birou', 'garsoniera'
    ];
    
    // Transaction type constants
    public const TRANSACTION_TYPES = [
        'vanzare', 'inchiriere'
    ];
    
    // Status constants
    public const STATUSES = [
        'draft', 'active', 'inactive', 'sold', 'rented', 'expired'
    ];
    
    // Condition constants
    public const CONDITIONS = [
        'nou', 'foarte_bun', 'bun', 'satisfacator', 'renovare'
    ];
    
    // Heating type constants
    public const HEATING_TYPES = [
        'centrala_proprie', 'centrala_bloc', 'termoficare', 'soba', 'aer_conditionat', 'altele'
    ];
    
    // Energy class constants
    public const ENERGY_CLASSES = [
        'A++', 'A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G'
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create a new property
     */
    public function create(array $data): int
    {
        // Validate input data
        $this->validatePropertyData($data);
        
        // Prepare data for insertion
        $propertyData = $this->preparePropertyData($data);
        
        // Add timestamps
        $propertyData['created_at'] = date('Y-m-d H:i:s');
        $propertyData['updated_at'] = date('Y-m-d H:i:s');
        
        // Generate slug if not provided
        if (empty($propertyData['slug'])) {
            $propertyData['slug'] = $this->generateSlug($propertyData['title']);
        }
        
        return $this->db->transaction(function($db) use ($propertyData) {
            $propertyId = $db->insert('properties', $propertyData);
            
            // Log activity
            $this->logActivity('property_created', $propertyId, $propertyData['user_id']);
            
            return $propertyId;
        });
    }
    
    /**
     * Update existing property
     */
    public function update(int $id, array $data): bool
    {
        // Validate input data
        $this->validatePropertyData($data, $id);
        
        // Check if property exists and user has permission
        $existing = $this->findById($id);
        if (!$existing) {
            throw new InvalidArgumentException("Property not found");
        }
        
        // Prepare data for update
        $propertyData = $this->preparePropertyData($data);
        $propertyData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->transaction(function($db) use ($id, $propertyData) {
            $result = $db->update('properties', $propertyData, ['id' => $id]);
            
            // Log activity
            $this->logActivity('property_updated', $id, $propertyData['user_id'] ?? null);
            
            return $result > 0;
        });
    }
    
    /**
     * Find property by ID
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT p.*, 
                   u.username, u.first_name, u.last_name, u.email as user_email,
                   (SELECT filename FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as images_count
            FROM properties p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ";
        
        $result = $this->db->queryOne($sql, [$id]);
        
        if ($result) {
            // Decode JSON fields
            $result['utilities'] = json_decode($result['utilities'] ?? '[]', true);
            $result['amenities'] = json_decode($result['amenities'] ?? '[]', true);
            
            // Increment view count (async for performance)
            $this->incrementViewCount($id);
        }
        
        return $result;
    }
    
    /**
     * Find property by slug
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = "
            SELECT p.*, 
                   u.username, u.first_name, u.last_name, u.email as user_email,
                   (SELECT filename FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as images_count
            FROM properties p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.slug = ? AND p.status = 'active'
        ";
        
        $result = $this->db->queryOne($sql, [$slug]);
        
        if ($result) {
            // Decode JSON fields
            $result['utilities'] = json_decode($result['utilities'] ?? '[]', true);
            $result['amenities'] = json_decode($result['amenities'] ?? '[]', true);
            
            // Increment view count
            $this->incrementViewCount($result['id']);
        }
        
        return $result;
    }
    
    /**
     * Search properties with filters
     */
    public function search(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $whereParts = ["p.status = 'active'"];
        $params = [];
        
        // Build WHERE clause from filters
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $this->db->escapeLike($filters['search']) . '%';
            $whereParts[] = "(p.title LIKE ? OR p.description LIKE ? OR p.address LIKE ? OR p.city LIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        if (!empty($filters['property_type'])) {
            $whereParts[] = "p.property_type = ?";
            $params[] = $filters['property_type'];
        }
        
        if (!empty($filters['transaction_type'])) {
            $whereParts[] = "p.transaction_type = ?";
            $params[] = $filters['transaction_type'];
        }
        
        if (!empty($filters['city'])) {
            $whereParts[] = "p.city = ?";
            $params[] = $filters['city'];
        }
        
        if (!empty($filters['min_price'])) {
            $whereParts[] = "p.price >= ?";
            $params[] = (float) $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $whereParts[] = "p.price <= ?";
            $params[] = (float) $filters['max_price'];
        }
        
        if (!empty($filters['min_surface'])) {
            $whereParts[] = "p.surface_useful >= ?";
            $params[] = (float) $filters['min_surface'];
        }
        
        if (!empty($filters['max_surface'])) {
            $whereParts[] = "p.surface_useful <= ?";
            $params[] = (float) $filters['max_surface'];
        }
        
        if (!empty($filters['rooms'])) {
            $whereParts[] = "p.rooms = ?";
            $params[] = (int) $filters['rooms'];
        }
        
        if (!empty($filters['condition_type'])) {
            $whereParts[] = "p.condition_type = ?";
            $params[] = $filters['condition_type'];
        }
        
        if (!empty($filters['parking'])) {
            $whereParts[] = "p.parking = 1";
        }
        
        if (!empty($filters['featured'])) {
            $whereParts[] = "p.featured = 1";
        }
        
        // Location-based search
        if (!empty($filters['latitude']) && !empty($filters['longitude']) && !empty($filters['radius'])) {
            $whereParts[] = $this->getDistanceClause();
            $params = array_merge($params, [
                (float) $filters['latitude'],
                (float) $filters['longitude'],
                (float) $filters['radius']
            ]);
        }
        
        $whereClause = implode(' AND ', $whereParts);
        
        // Build ORDER BY clause
        $orderBy = $this->buildOrderClause($filters['sort'] ?? 'newest');
        
        // Get total count
        $countSql = "
            SELECT COUNT(*) 
            FROM properties p 
            LEFT JOIN users u ON p.user_id = u.id 
            WHERE $whereClause
        ";
        $totalCount = (int) $this->db->queryScalar($countSql, $params);
        
        // Get properties
        $sql = "
            SELECT p.*, 
                   u.username, u.first_name, u.last_name,
                   (SELECT filename FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as images_count
            FROM properties p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE $whereClause
            ORDER BY $orderBy
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $properties = $this->db->query($sql, $params);
        
        // Decode JSON fields for each property
        foreach ($properties as &$property) {
            $property['utilities'] = json_decode($property['utilities'] ?? '[]', true);
            $property['amenities'] = json_decode($property['amenities'] ?? '[]', true);
        }
        
        return [
            'data' => $properties,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit)
            ]
        ];
    }
    
    /**
     * Get properties by user ID
     */
    public function getByUserId(int $userId, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $totalCount = (int) $this->db->queryScalar(
            "SELECT COUNT(*) FROM properties WHERE user_id = ?",
            [$userId]
        );
        
        // Get properties
        $sql = "
            SELECT p.*,
                   (SELECT filename FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as images_count
            FROM properties p
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $properties = $this->db->query($sql, [$userId, $limit, $offset]);
        
        // Decode JSON fields
        foreach ($properties as &$property) {
            $property['utilities'] = json_decode($property['utilities'] ?? '[]', true);
            $property['amenities'] = json_decode($property['amenities'] ?? '[]', true);
        }
        
        return [
            'data' => $properties,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit)
            ]
        ];
    }
    
    /**
     * Delete property
     */
    public function delete(int $id): bool
    {
        return $this->db->transaction(function($db) use ($id) {
            // Get property info for logging
            $property = $this->findById($id);
            if (!$property) {
                throw new InvalidArgumentException("Property not found");
            }
            
            // Delete property (cascades to images, favorites, etc.)
            $result = $db->delete('properties', ['id' => $id]);
            
            // Log activity
            $this->logActivity('property_deleted', $id, $property['user_id']);
            
            return $result > 0;
        });
    }
    
    /**
     * Get featured properties
     */
    public function getFeatured(int $limit = 10): array
    {
        $sql = "
            SELECT p.*, 
                   u.username, u.first_name, u.last_name,
                   (SELECT filename FROM property_images WHERE property_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
                   (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as images_count
            FROM properties p
            LEFT JOIN users u ON p.user_id = u.id
            WHERE p.status = 'active' AND p.featured = 1
            ORDER BY p.created_at DESC
            LIMIT ?
        ";
        
        $properties = $this->db->query($sql, [$limit]);
        
        // Decode JSON fields
        foreach ($properties as &$property) {
            $property['utilities'] = json_decode($property['utilities'] ?? '[]', true);
            $property['amenities'] = json_decode($property['amenities'] ?? '[]', true);
        }
        
        return $properties;
    }
    
    /**
     * Get property statistics
     */
    public function getStatistics(): array
    {
        $stats = [];
        
        // Total properties
        $stats['total_properties'] = (int) $this->db->queryScalar(
            "SELECT COUNT(*) FROM properties WHERE status = 'active'"
        );
        
        // Properties by type
        $stats['by_type'] = $this->db->query(
            "SELECT property_type, COUNT(*) as count FROM properties WHERE status = 'active' GROUP BY property_type"
        );
        
        // Properties by transaction type
        $stats['by_transaction'] = $this->db->query(
            "SELECT transaction_type, COUNT(*) as count FROM properties WHERE status = 'active' GROUP BY transaction_type"
        );
        
        // Average price by city
        $stats['avg_price_by_city'] = $this->db->query(
            "SELECT city, AVG(price) as avg_price, COUNT(*) as count 
             FROM properties 
             WHERE status = 'active' 
             GROUP BY city 
             ORDER BY count DESC 
             LIMIT 10"
        );
        
        // Recent activity
        $stats['recent_activity'] = $this->db->query(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM properties 
             WHERE created_at >= DATE('now', '-30 days')
             GROUP BY DATE(created_at) 
             ORDER BY date DESC"
        );
        
        return $stats;
    }
    
    /**
     * Validate property data
     */
    private function validatePropertyData(array $data, ?int $propertyId = null): void
    {
        $errors = [];
        
        // Required fields
        $requiredFields = ['title', 'property_type', 'transaction_type', 'price', 'address', 'city'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = "Field '$field' is required";
            }
        }
        
        // Validate property type
        if (!empty($data['property_type']) && !in_array($data['property_type'], self::PROPERTY_TYPES)) {
            $errors[] = "Invalid property type";
        }
        
        // Validate transaction type
        if (!empty($data['transaction_type']) && !in_array($data['transaction_type'], self::TRANSACTION_TYPES)) {
            $errors[] = "Invalid transaction type";
        }
        
        // Validate status
        if (!empty($data['status']) && !in_array($data['status'], self::STATUSES)) {
            $errors[] = "Invalid status";
        }
        
        // Validate price
        if (!empty($data['price'])) {
            $price = filter_var($data['price'], FILTER_VALIDATE_FLOAT);
            if ($price === false || $price <= 0) {
                $errors[] = "Price must be a positive number";
            }
        }
        
        // Validate coordinates
        if (!empty($data['latitude'])) {
            $lat = filter_var($data['latitude'], FILTER_VALIDATE_FLOAT);
            if ($lat === false || $lat < -90 || $lat > 90) {
                $errors[] = "Invalid latitude";
            }
        }
        
        if (!empty($data['longitude'])) {
            $lng = filter_var($data['longitude'], FILTER_VALIDATE_FLOAT);
            if ($lng === false || $lng < -180 || $lng > 180) {
                $errors[] = "Invalid longitude";
            }
        }
        
        // Validate slug uniqueness
        if (!empty($data['slug'])) {
            $existingSlug = $this->db->queryOne(
                "SELECT id FROM properties WHERE slug = ? AND id != ?",
                [$data['slug'], $propertyId ?? 0]
            );
            if ($existingSlug) {
                $errors[] = "Slug already exists";
            }
        }
        
        // Validate email format if provided
        if (!empty($data['contact_email'])) {
            if (!filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid contact email format";
            }
        }
        
        if (!empty($errors)) {
            throw new InvalidArgumentException("Validation failed: " . implode(', ', $errors));
        }
    }
    
    /**
     * Prepare property data for database insertion/update
     */
    private function preparePropertyData(array $data): array
    {
        $prepared = [];
        
        // String fields
        $stringFields = [
            'title', 'description', 'property_type', 'transaction_type', 'currency',
            'address', 'city', 'county', 'postal_code', 'condition_type', 'heating_type',
            'energy_class', 'contact_name', 'contact_phone', 'contact_email', 'status',
            'slug', 'meta_title', 'meta_description'
        ];
        
        foreach ($stringFields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = trim(htmlspecialchars($data[$field], ENT_QUOTES, 'UTF-8'));
            }
        }
        
        // Numeric fields
        $numericFields = [
            'user_id', 'price', 'latitude', 'longitude', 'surface_total', 'surface_useful',
            'rooms', 'bedrooms', 'bathrooms', 'floor', 'total_floors', 'construction_year'
        ];
        
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                if (in_array($field, ['latitude', 'longitude', 'price', 'surface_total', 'surface_useful'])) {
                    $prepared[$field] = (float) $data[$field];
                } else {
                    $prepared[$field] = (int) $data[$field];
                }
            }
        }
        
        // Boolean fields
        $booleanFields = [
            'parking', 'balcony', 'terrace', 'garden', 'basement', 'attic', 'featured'
        ];
        
        foreach ($booleanFields as $field) {
            if (isset($data[$field])) {
                $prepared[$field] = (bool) $data[$field];
            }
        }
        
        // JSON fields
        if (isset($data['utilities'])) {
            $prepared['utilities'] = json_encode(array_values((array) $data['utilities']));
        }
        
        if (isset($data['amenities'])) {
            $prepared['amenities'] = json_encode(array_values((array) $data['amenities']));
        }
        
        // Date fields
        if (isset($data['published_at'])) {
            $prepared['published_at'] = date('Y-m-d H:i:s', strtotime($data['published_at']));
        }
        
        if (isset($data['expires_at'])) {
            $prepared['expires_at'] = date('Y-m-d H:i:s', strtotime($data['expires_at']));
        }
        
        return $prepared;
    }
    
    /**
     * Generate unique slug from title
     */
    private function generateSlug(string $title): string
    {
        // Convert to lowercase and replace spaces with hyphens
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->db->queryOne("SELECT id FROM properties WHERE slug = ?", [$slug])) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Increment view count for property
     */
    private function incrementViewCount(int $propertyId): void
    {
        // This could be optimized to use a queue/cache for high traffic
        $this->db->execute(
            "UPDATE properties SET views_count = views_count + 1 WHERE id = ?",
            [$propertyId]
        );
        
        // Log view (optional, for analytics)
        $this->logView($propertyId);
    }
    
    /**
     * Log property view for analytics
     */
    private function logView(int $propertyId): void
    {
        $viewData = [
            'property_id' => $propertyId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'viewed_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $this->db->insert('property_views', $viewData);
        } catch (Exception $e) {
            // Don't fail the request if view logging fails
            error_log("Failed to log property view: " . $e->getMessage());
        }
    }
    
    /**
     * Log activity
     */
    private function logActivity(string $action, int $resourceId, ?int $userId = null): void
    {
        $logData = [
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => 'property',
            'resource_id' => $resourceId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $this->db->insert('activity_logs', $logData);
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
    
    /**
     * Get distance calculation SQL clause
     */
    private function getDistanceClause(): string
    {
        // Haversine formula for distance calculation
        return "(
            6371 * acos(
                cos(radians(?)) * 
                cos(radians(p.latitude)) * 
                cos(radians(p.longitude) - radians(?)) + 
                sin(radians(?)) * 
                sin(radians(p.latitude))
            )
        ) <= ?";
    }
    
    /**
     * Build ORDER BY clause from sort parameter
     */
    private function buildOrderClause(string $sort): string
    {
        switch ($sort) {
            case 'price_asc':
                return 'p.price ASC';
            case 'price_desc':
                return 'p.price DESC';
            case 'surface_asc':
                return 'p.surface_useful ASC';
            case 'surface_desc':
                return 'p.surface_useful DESC';
            case 'oldest':
                return 'p.created_at ASC';
            case 'views':
                return 'p.views_count DESC';
            case 'featured':
                return 'p.featured DESC, p.created_at DESC';
            case 'newest':
            default:
                return 'p.created_at DESC';
        }
    }
}