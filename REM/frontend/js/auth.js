// Authentication helper functions
const API_URL = 'http://localhost:8000/api';

// Toast notification function
function showToast(message, type = 'info') {
    // Create toast if it doesn't exist
    let toast = document.querySelector('.toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast';
        document.body.appendChild(toast);
    }

    // Set message and type
    toast.textContent = message;
    toast.className = `toast ${type}`;
    toast.style.display = 'block';

    // Hide after 3 seconds
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

// Check if user is authenticated
async function checkAuth() {
    try {
        const response = await fetch(`${API_URL}/auth.php?action=check-session`, {
            credentials: 'include'
        });
        
        if (!response.ok) {
            throw new Error('Not authenticated');
        }

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Auth check failed:', error);
        return { authenticated: false };
    }
}

// Protect routes that require authentication
async function protectRoute(adminOnly = false) {
    const auth = await checkAuth();
    
    if (!auth.authenticated) {
        window.location.href = '/login.html';
        return false;
    }

    if (adminOnly && auth.user.role !== 'admin') {
        showToast('Access denied. Admin privileges required.', 'warning');
        setTimeout(() => {
            window.location.href = '/';
        }, 2000);
        return false;
    }

    return auth.user;
}

// Handle logout
async function logout() {
    try {
        await fetch(`${API_URL}/auth.php?action=logout`, {
            credentials: 'include'
        });
        localStorage.removeItem('user');
        window.location.href = '/login.html';
    } catch (error) {
        console.error('Logout failed:', error);
        showToast('Logout failed. Please try again.', 'error');
    }
}

// Handle API responses
function handleApiResponse(response, successMessage = '') {
    if (response.ok || response.status === 201) {
        if (successMessage) {
            showToast(successMessage, 'success');
        }
        return true;
    }
    throw new Error(`HTTP Error: ${response.status}`);
}

// Add logout functionality to logout buttons
document.addEventListener('DOMContentLoaded', () => {
    const logoutButtons = document.querySelectorAll('.logout-btn');
    logoutButtons.forEach(button => {
        button.addEventListener('click', logout);
    });
}); 