/**
 * Authentication utility functions for client-side
 */

// Check if user is logged in
function isLoggedIn() {
    return localStorage.getItem('token') !== null;
}

// Get current user
function getCurrentUser() {
    const userStr = localStorage.getItem('user');
    if (!userStr) return null;
    
    try {
        return JSON.parse(userStr);
    } catch (e) {
        return null;
    }
}

// Check if user is admin
function isAdmin() {
    const user = getCurrentUser();
    return user && user.is_admin === 1;
}

// Get auth header
function getAuthHeader() {
    const token = localStorage.getItem('token');
    return token ? { 'Authorization': `Bearer ${token}` } : {};
}

// Logout user
function logoutUser() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = '/';
}

// API request with authentication
async function authenticatedFetch(url, options = {}) {
    const headers = {
        ...options.headers,
        ...getAuthHeader(),
        'Content-Type': 'application/json'
    };
    
    const response = await fetch(url, {
        ...options,
        headers
    });
    
    // If unauthorized, redirect to login
    if (response.status === 401) {
        // Clear any existing auth data
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        
        // Redirect to login page
        window.location.href = '/login';
        return null;
    }
    
    return response;
}

// Update UI based on authentication state
function updateAuthUI() {
    const authLinks = document.getElementById('authLinks');
    
    if (!authLinks) return;
    
    if (isLoggedIn()) {
        const user = getCurrentUser();
        authLinks.innerHTML = `
            <span class="user-greeting">Hello, ${user.name || user.username}</span>
            <button id="logoutBtn" class="btn">Logout</button>
        `;
        
        // Add event listener to logout button
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', logoutUser);
        }
    } else {
        authLinks.innerHTML = `
            <a href="/login" class="btn">Login</a>
            <a href="/register" class="btn">Register</a>
        `;
    }
    
    // Show/hide admin link based on permissions
    const adminLink = document.querySelector('.admin-link');
    if (adminLink) {
        adminLink.style.display = isAdmin() ? 'inline-block' : 'none';
    }
    
    // Show/hide add property button
    const addPropertyBtn = document.getElementById('addPropertyBtn');
    if (addPropertyBtn) {
        addPropertyBtn.style.display = isLoggedIn() ? 'inline-block' : 'none';
    }
}

// Check if user can edit a property
function canEditProperty(propertyOwnerId) {
    const user = getCurrentUser();
    
    // Not logged in
    if (!user) return false;
    
    // Admin can edit any property
    if (user.is_admin === 1) return true;
    
    // User can edit own property
    return user.id === propertyOwnerId;
}
