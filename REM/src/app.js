const express = require('express');
const cors = require('cors');
const path = require('path');
const sqlite3 = require('sqlite3').verbose();
const fs = require('fs');
const { Parser } = require('json2csv');

const app = express();
const port = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, '../public')));

// Database setup
const db = new sqlite3.Database('./database/rem.db', (err) => {
    if (err) {
        console.error('Error opening database:', err);
    } else {
        console.log('Connected to SQLite database');
        createTables();
    }
});

// Create tables
function createTables() {
    db.serialize(() => {
        db.run(`CREATE TABLE IF NOT EXISTS properties (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            price REAL NOT NULL,
            type TEXT NOT NULL,
            property_type TEXT NOT NULL,
            area REAL NOT NULL,
            building_condition TEXT,
            facilities TEXT,
            risks TEXT,
            latitude REAL NOT NULL,
            longitude REAL NOT NULL,
            contact_info TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )`);
    });
}

// Routes
app.get('/api/properties', (req, res) => {
    db.all('SELECT * FROM properties', [], (err, rows) => {
        if (err) {
            res.status(500).json({ error: err.message });
            return;
        }
        res.json(rows);
    });
});

app.post('/api/properties', (req, res) => {
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
        building_condition, facilities, risks, latitude, longitude, contact_info
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`;
    
    db.run(sql, [
        title, description, price, type, property_type, area,
        building_condition, facilities, risks, latitude, longitude, contact_info
    ], function(err) {
        if (err) {
            res.status(500).json({ error: err.message });
            return;
        }
        res.json({ id: this.lastID });
    });
});

app.delete('/api/properties/:id', (req, res) => {
    const { id } = req.params;
    db.run('DELETE FROM properties WHERE id = ?', [id], function(err) {
        if (err) {
            res.status(500).json({ error: err.message });
            return;
        }
        res.json({ deleted: this.changes });
    });
});

// Filtering endpoint
app.get('/api/properties/filter', (req, res) => {
    const {
        type,
        property_type,
        min_price,
        max_price,
        min_area,
        max_area,
        facilities
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
        res.json(rows);
    });
});

// Nearby properties endpoint using coordinates
app.get('/api/properties/nearby', (req, res) => {
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
});

// Export data as JSON
app.get('/api/export/json', (req, res) => {
    db.all('SELECT * FROM properties', [], (err, rows) => {
        if (err) {
            res.status(500).json({ error: err.message });
            return;
        }
        
        res.setHeader('Content-Type', 'application/json');
        res.setHeader('Content-Disposition', 'attachment; filename=properties.json');
        res.json(rows);
    });
});

// Export data as CSV
app.get('/api/export/csv', (req, res) => {
    db.all('SELECT * FROM properties', [], (err, rows) => {
        if (err) {
            res.status(500).json({ error: err.message });
            return;
        }
        
        try {
            const fields = [
                'id', 'title', 'description', 'price', 'type', 'property_type', 
                'area', 'building_condition', 'facilities', 'risks', 
                'latitude', 'longitude', 'contact_info', 'created_at'
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
});

// Import data from JSON
app.post('/api/import/json', (req, res) => {
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
                building_condition, facilities, risks, latitude, longitude, contact_info
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`;
            
            db.run(sql, [
                property.title, property.description, property.price, property.type, 
                property.property_type, property.area, property.building_condition, 
                property.facilities, property.risks, property.latitude, 
                property.longitude, property.contact_info
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
});

// Serve the main page
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/index.html'));
});

// Serve the admin page
app.get('/admin', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/admin.html'));
});

app.listen(port, () => {
    console.log(`Server running at http://localhost:${port}`);
}); 