const express = require('express');
const bodyParser = require('body-parser');
const cors = require('cors');
const path = require('path');
const sqlite3 = require('sqlite3').verbose();

const app = express();
const port = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(bodyParser.json());
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
    const { title, description, price, type, latitude, longitude, contact_info } = req.body;
    const sql = `INSERT INTO properties (title, description, price, type, latitude, longitude, contact_info)
                 VALUES (?, ?, ?, ?, ?, ?, ?)`;
    
    db.run(sql, [title, description, price, type, latitude, longitude, contact_info], function(err) {
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

// Serve the main page
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, '../public/index.html'));
});

app.listen(port, () => {
    console.log(`Server running at http://localhost:${port}`);
}); 