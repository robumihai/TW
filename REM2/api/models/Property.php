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
