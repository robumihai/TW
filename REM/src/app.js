const express = require('express');
const cors = require('cors');
const path = require('path');
const sqlite3 = require('sqlite3').verbose();
const fs = require('fs');
const { Parser } = require('json2csv');

const app = express();
const port = process.env.PORT || 3000;

// Import controllers
const PropertyController = require('./controllers/PropertyController');
const UserController = require('./controllers/UserController');

// Import middleware
const { authenticate, requireAdmin } = require('./middleware/auth');

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, '../public')));

// Apply authentication middleware to all routes
app.use(authenticate);

// Database setup
const db = new sqlite3.Database('./database/rem.db', (err) => {
    if (err) {
        console.error('Error opening database:', err);
    } else {
        console.log('Connected to SQLite database');
        createTables();
    }
});

// Check and add owner_id column if it does not exist
function ensureOwnerIdColumn() {
    db.get("PRAGMA table_info(properties)", (err, row) => {
        if (err) return;
        db.all("PRAGMA table_info(properties)", (err, columns) => {
            if (err) return;
            const hasOwnerId = columns.some(col => col.name === 'owner_id');
            if (!hasOwnerId) {
                db.run('ALTER TABLE properties ADD COLUMN owner_id INTEGER', (err) => {
                    if (err) {
                        console.error('Failed to add owner_id column:', err.message);
                    } else {
                        console.log('Added owner_id column to properties table.');
                    }
                });
            }
        });
    });
}

// Create tables
function createTables() {
    db.serialize(() => {
        // Properties table with owner_id field
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
            owner_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )`, [], (err) => {
            if (!err) ensureOwnerIdColumn();
        });
        
        // Users table
        db.run(`CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            name TEXT,
            is_admin INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )`, [], (err) => {
            if (err) {
                console.error('Error creating users table:', err);
            } else {
                // Create default admin user if none exists
                db.get('SELECT COUNT(*) as count FROM users WHERE is_admin = 1', [], (err, row) => {
                    if (err) {
                        console.error('Error checking admin users:', err);
                        return;
                    }
                    
                    if (row.count === 0) {
                        const bcrypt = require('bcrypt');
                        bcrypt.hash('admin123', 10, (err, hashedPassword) => {
                            if (err) {
                                console.error('Error hashing password:', err);
                                return;
                            }
                            
                            db.run('INSERT INTO users (username, password, email, name, is_admin) VALUES (?, ?, ?, ?, ?)',
                                ['admin', hashedPassword, 'admin@rem.com', 'Administrator', 1],
                                (err) => {
                                    if (err) {
                                        console.error('Error creating default admin:', err);
                                    } else {
                                        console.log('Default admin user created. Username: admin, Password: admin123');
                                    }
                                });
                        });
                    }
                });
            }
        });
    });
}

// Authentication routes
app.post('/api/auth/register', UserController.register);
app.post('/api/auth/login', UserController.login);
app.get('/api/auth/profile', UserController.getProfile);

// Properties routes
app.get('/api/properties', PropertyController.getAllProperties);
app.get('/api/properties/:id', PropertyController.getPropertyById);
app.post('/api/properties', PropertyController.addProperty);
app.delete('/api/properties/:id', PropertyController.deleteProperty);

app.get('/api/properties/filter', PropertyController.filterProperties);
app.get('/api/properties/nearby', PropertyController.findNearbyProperties);

// Export/Import routes
app.get('/api/export/json', PropertyController.exportAsJson);
app.get('/api/export/csv', PropertyController.exportAsCsv);
app.post('/api/import/json', requireAdmin, PropertyController.importFromJson);

// HTML routes
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/index.html'));
});

app.get('/admin', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/admin.html'));
});

app.get('/login', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/login.html'));
});

app.get('/register', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/register.html'));
});

app.listen(port, () => {
    console.log(`Server running at http://localhost:${port}`);
});