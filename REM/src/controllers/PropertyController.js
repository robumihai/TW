const sqlite3 = require('sqlite3').verbose();
const { Parser } = require('json2csv');
const db = new sqlite3.Database('./database/rem.db');

class PropertyController {
    /**
     * Get all properties
     */
    getAllProperties(req, res) {
        db.all('SELECT * FROM properties', [], (err, rows) => {
            if (err) {
                res.status(500).json({ error: err.message });
                return;
            }
            res.json(rows);
        });
    }

    /**
     * Get a property by ID
     */
    getPropertyById(req, res) {
        const { id } = req.params;
        db.get('SELECT * FROM properties WHERE id = ?', [id], (err, row) => {
            if (err) {
                res.status(500).json({ error: err.message });
                return;
            }
            if (!row) {
                return res.status(404).json({ error: 'Property not found' });
            }
            res.json(row);
        });
    }

    /**
     * Add a new property
     * Requires authentication
     */
    addProperty(req, res) {
        // Debug: log headers and user
        console.log('Authorization header:', req.headers['authorization']);
        console.log('req.user:', req.user);
        // User must be logged in to add property
        if (!req.user) {
            return res.status(401).json({ error: 'Authentication required' });
        }

        const { 
            title, 
            description, 
            price, 
            type, 
            property_type,
            area,
            building_condition,
            facilities,
            risks,
            latitude, 
            longitude, 
            contact_info 
        } = req.body;
        
        const sql = `INSERT INTO properties (
            title, description, price, type, property_type, area,
            building_condition, facilities, risks, latitude, longitude, 
            contact_info, owner_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`;
        
        db.run(sql, [
            title, description, price, type, property_type, area,
            building_condition, facilities, risks, latitude, longitude, 
            contact_info, req.user.id
        ], function(err) {
            if (err) {
                res.status(500).json({ error: err.message });
                return;
            }
            res.json({ id: this.lastID });
        });
    }

    /**
     * Delete a property
     * Requires authentication and ownership or admin rights
     */
    deleteProperty(req, res) {
        // User must be logged in to delete property
        if (!req.user) {
            return res.status(401).json({ error: 'Authentication required' });
        }

        const { id } = req.params;
        
        // Check if property belongs to the user or if user is admin
        db.get('SELECT owner_id FROM properties WHERE id = ?', [id], (err, row) => {
            if (err) {
                return res.status(500).json({ error: err.message });
            }
            
            if (!row) {
                return res.status(404).json({ error: 'Property not found' });
            }
            
            // Allow deletion if user is owner or admin
            if (req.user.id === row.owner_id || req.user.is_admin) {
                db.run('DELETE FROM properties WHERE id = ?', [id], function(err) {
                    if (err) {
                        return res.status(500).json({ error: err.message });
                    }
                    res.json({ deleted: this.changes });
                });
            } else {
                res.status(403).json({ error: 'You do not have permission to delete this property' });
            }
        });
    }

    /**
     * Filter properties based on various criteria
     */
    filterProperties(req, res) {
        const {
            type,
            property_type,
            min_price,
            max_price,
            min_area,
            max_area,
            facilities,
            center_lat,
            center_lng,
            radius_km
        } = req.query;

        let conditions = [];
        let params = [];

        if (type) {
            conditions.push('type = ?');
            params.push(type);
        }

        if (property_type) {
            conditions.push('property_type = ?');
            params.push(property_type);
        }

        if (min_price) {
            conditions.push('price >= ?');
            params.push(min_price);
        }

        if (max_price) {
            conditions.push('price <= ?');
            params.push(max_price);
        }

        if (min_area) {
            conditions.push('area >= ?');
            params.push(min_area);
        }

        if (max_area) {
            conditions.push('area <= ?');
            params.push(max_area);
        }

        if (facilities) {
            conditions.push('facilities LIKE ?');
            params.push(`%${facilities}%`);
        }

        let sql = 'SELECT * FROM properties';
        if (conditions.length > 0) {
            sql += ' WHERE ' + conditions.join(' AND ');
        }

        db.all(sql, params, (err, rows) => {
            if (err) {
                res.status(500).json({ error: err.message });
                return;
            }
            // Dacă avem filtrare pe județ (center_lat, center_lng, radius_km), filtrăm și după distanță
            if (center_lat && center_lng && radius_km) {
                const centerLat = parseFloat(center_lat);
                const centerLng = parseFloat(center_lng);
                const radius = parseFloat(radius_km);
                // Funcție Haversine
                function getDistanceKm(lat1, lon1, lat2, lon2) {
                    const R = 6371;
                    const dLat = (lat2 - lat1) * Math.PI / 180;
                    const dLon = (lon2 - lon1) * Math.PI / 180;
                    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                        Math.sin(dLon/2) * Math.sin(dLon/2);
                    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                    return R * c;
                }
                const filtered = rows.filter(row => {
                    return getDistanceKm(centerLat, centerLng, row.latitude, row.longitude) <= radius;
                });
                res.json(filtered);
            } else {
                res.json(rows);
            }
        });
    }

    /**
     * Find nearby properties
     */
    findNearbyProperties(req, res) {
        const { latitude, longitude, radius = 5 } = req.query;
        
        if (!latitude || !longitude) {
            return res.status(400).json({ error: 'Latitude and longitude are required' });
        }
        
        // Simple distance calculation (not perfect but works for small distances)
        const sql = `
            SELECT *, (
                (latitude - ?)*(latitude - ?) + 
                (longitude - ?)*(longitude - ?)
            ) AS distance 
            FROM properties 
            ORDER BY distance ASC
            LIMIT 10
        `;
        
        db.all(sql, [latitude, latitude, longitude, longitude], (err, rows) => {
            if (err) {
                res.status(500).json({ error: err.message });
                return;
            }
            res.json(rows);
        });
    }

    /**
     * Export data as JSON
     */
    exportAsJson(req, res) {
        db.all('SELECT * FROM properties', [], (err, rows) => {
            if (err) {
                res.status(500).json({ error: err.message });
                return;
            }
            
            res.setHeader('Content-Type', 'application/json');
            res.setHeader('Content-Disposition', 'attachment; filename=properties.json');
            res.json(rows);
        });
    }

    /**
     * Export data as CSV
     */
    exportAsCsv(req, res) {
        db.all('SELECT * FROM properties', [], (err, rows) => {
            if (err) {
                res.status(500).json({ error: err.message });
                return;
            }
            
            try {
                const fields = [
                    'id', 'title', 'description', 'price', 'type', 'property_type', 
                    'area', 'building_condition', 'facilities', 'risks', 
                    'latitude', 'longitude', 'contact_info', 'created_at', 'owner_id'
                ];
                const parser = new Parser({ fields });
                const csv = parser.parse(rows);
                
                res.setHeader('Content-Type', 'text/csv');
                res.setHeader('Content-Disposition', 'attachment; filename=properties.csv');
                res.send(csv);
            } catch (err) {
                res.status(500).json({ error: err.message });
            }
        });
    }

    /**
     * Import data from JSON
     * Requires admin rights
     */
    importFromJson(req, res) {
        // Only admins can import data
        if (!req.user || !req.user.is_admin) {
            return res.status(403).json({ error: 'Admin rights required' });
        }

        const properties = req.body;
        
        if (!Array.isArray(properties)) {
            return res.status(400).json({ error: 'Invalid JSON format. Expected array of properties.' });
        }
        
        let successCount = 0;
        let errorCount = 0;
        
        const insertProperty = (property) => {
            return new Promise((resolve, reject) => {
                const sql = `INSERT INTO properties (
                    title, description, price, type, property_type, area,
                    building_condition, facilities, risks, latitude, longitude, 
                    contact_info, owner_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`;
                
                db.run(sql, [
                    property.title, property.description, property.price, property.type, 
                    property.property_type, property.area, property.building_condition, 
                    property.facilities, property.risks, property.latitude, 
                    property.longitude, property.contact_info, property.owner_id || req.user.id
                ], function(err) {
                    if (err) {
                        reject(err);
                    } else {
                        resolve(this.lastID);
                    }
                });
            });
        };
        
        const promises = properties.map(property => {
            return insertProperty(property)
                .then(() => { successCount++; })
                .catch(() => { errorCount++; });
        });
        
        Promise.all(promises)
            .then(() => {
                res.json({ 
                    message: 'Import completed', 
                    success: successCount, 
                    errors: errorCount 
                });
            })
            .catch(err => {
                res.status(500).json({ error: err.message });
            });
    }
}

module.exports = new PropertyController();
