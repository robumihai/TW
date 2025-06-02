const jwt = require('jsonwebtoken');

// Secret key for JWT verification - in a real app, this should be in an env variable
const JWT_SECRET = 'rem_secret_key_2025';

/**
 * Authentication middleware
 * Verifies the JWT token if present
 * Sets req.user if authentication is successful
 * Does not block request if no token is present (for public routes)
 */
function authenticate(req, res, next) {
    // Get token from header
    const authHeader = req.headers['authorization'];
    const token = authHeader && authHeader.split(' ')[1]; // Format: "Bearer TOKEN"
    
    // If no token, continue to the next middleware (route will handle auth check if needed)
    if (!token) {
        return next();
    }
    
    // Verify token
    jwt.verify(token, JWT_SECRET, (err, user) => {
        if (err) {
            // Don't return error, just continue without setting user
            return next();
        }
        
        // Set user in request
        req.user = user;
        next();
    });
}

/**
 * Admin middleware
 * Verifies the user is authenticated and is an admin
 */
function requireAdmin(req, res, next) {
    if (!req.user) {
        return res.status(401).json({ error: 'Authentication required' });
    }
    
    if (!req.user.is_admin) {
        return res.status(403).json({ error: 'Admin rights required' });
    }
    
    next();
}

module.exports = {
    authenticate,
    requireAdmin
};
