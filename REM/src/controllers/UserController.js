const sqlite3 = require('sqlite3').verbose();
const bcrypt = require('bcrypt');
const jwt = require('jsonwebtoken');
const db = new sqlite3.Database('./database/rem.db');

// Secret key for JWT signing - in a real app, this should be in an env variable
const JWT_SECRET = 'rem_secret_key_2025';

class UserController {
    /**
     * Register a new user
     */
    register(req, res) {
        const { username, password, email, name } = req.body;
        
        // Validate input
        if (!username || !password || !email) {
            return res.status(400).json({ error: 'Username, password, and email are required' });
        }
        
        // Check if username already exists
        db.get('SELECT id FROM users WHERE username = ?', [username], (err, row) => {
            if (err) {
                return res.status(500).json({ error: err.message });
            }
            
            if (row) {
                return res.status(400).json({ error: 'Username already exists' });
            }
            
            // Check if email already exists
            db.get('SELECT id FROM users WHERE email = ?', [email], (err, row) => {
                if (err) {
                    return res.status(500).json({ error: err.message });
                }
                
                if (row) {
                    return res.status(400).json({ error: 'Email already exists' });
                }
                
                // Hash password
                bcrypt.hash(password, 10, (err, hashedPassword) => {
                    if (err) {
                        return res.status(500).json({ error: 'Error hashing password' });
                    }
                    
                    // Create user
                    const sql = 'INSERT INTO users (username, password, email, name, is_admin) VALUES (?, ?, ?, ?, ?)';
                    db.run(sql, [username, hashedPassword, email, name, 0], function(err) {
                        if (err) {
                            return res.status(500).json({ error: err.message });
                        }
                        
                        // Generate JWT token
                        const token = jwt.sign(
                            { id: this.lastID, username, is_admin: 0 },
                            JWT_SECRET,
                            { expiresIn: '24h' }
                        );
                        
                        res.json({
                            message: 'User registered successfully',
                            token
                        });
                    });
                });
            });
        });
    }
    
    /**
     * Login user
     */
    login(req, res) {
        const { username, password } = req.body;
        
        // Validate input
        if (!username || !password) {
            return res.status(400).json({ error: 'Username and password are required' });
        }
        
        // Check if user exists
        db.get('SELECT * FROM users WHERE username = ?', [username], (err, user) => {
            if (err) {
                return res.status(500).json({ error: err.message });
            }
            
            if (!user) {
                return res.status(401).json({ error: 'Authentication failed' });
            }
            
            // Compare password
            bcrypt.compare(password, user.password, (err, result) => {
                if (err) {
                    return res.status(500).json({ error: 'Error comparing passwords' });
                }
                
                if (!result) {
                    return res.status(401).json({ error: 'Authentication failed' });
                }
                
                // Generate JWT token
                const token = jwt.sign(
                    { id: user.id, username: user.username, is_admin: user.is_admin },
                    JWT_SECRET,
                    { expiresIn: '24h' }
                );
                
                res.json({
                    message: 'Login successful',
                    token,
                    user: {
                        id: user.id,
                        username: user.username,
                        email: user.email,
                        name: user.name,
                        is_admin: user.is_admin
                    }
                });
            });
        });
    }
    
    /**
     * Get current user profile
     */
    getProfile(req, res) {
        if (!req.user) {
            return res.status(401).json({ error: 'Authentication required' });
        }
        
        db.get('SELECT id, username, email, name, is_admin FROM users WHERE id = ?', [req.user.id], (err, user) => {
            if (err) {
                return res.status(500).json({ error: err.message });
            }
            
            if (!user) {
                return res.status(404).json({ error: 'User not found' });
            }
            
            res.json(user);
        });
    }
}

module.exports = new UserController();
