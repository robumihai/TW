document.addEventListener('DOMContentLoaded', function() {
    // Get the current page
    const currentPage = window.location.pathname;
    
    // Handle login form submission
    if (currentPage === '/login') {
        const loginForm = document.getElementById('loginForm');
        const loginMessage = document.getElementById('loginMessage');
        
        loginForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            // Validate input
            if (!username || !password) {
                showMessage(loginMessage, 'Please fill in all fields', 'error');
                return;
            }
            
            // Send login request to API
            fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showMessage(loginMessage, data.error, 'error');
                } else {
                    // Save token in localStorage
                    localStorage.setItem('token', data.token);
                    localStorage.setItem('user', JSON.stringify(data.user));
                    
                    // Show success message
                    showMessage(loginMessage, 'Login successful! Redirecting...', 'success');
                    
                    // Redirect after 1 second
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 1000);
                }
            })
            .catch(error => {
                showMessage(loginMessage, 'An error occurred. Please try again.', 'error');
                console.error('Error:', error);
            });
        });
    }
    
    // Handle register form submission
    if (currentPage === '/register') {
        const registerForm = document.getElementById('registerForm');
        const registerMessage = document.getElementById('registerMessage');
        
        registerForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const name = document.getElementById('name').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // Validate input
            if (!username || !email || !name || !password || !confirmPassword) {
                showMessage(registerMessage, 'Please fill in all fields', 'error');
                return;
            }
            
            // Check if passwords match
            if (password !== confirmPassword) {
                showMessage(registerMessage, 'Passwords do not match', 'error');
                return;
            }
            
            // Check password strength (simple check)
            if (password.length < 6) {
                showMessage(registerMessage, 'Password must be at least 6 characters long', 'error');
                return;
            }
            
            // Send register request to API
            fetch('/api/auth/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ username, email, name, password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showMessage(registerMessage, data.error, 'error');
                } else {
                    // Nu salva tokenul și nu redirecționa automat
                    showMessage(registerMessage, 'Registration successful! You can now <a href="/login">log in</a>.', 'success');
                }
            })
            .catch(error => {
                showMessage(registerMessage, 'An error occurred. Please try again.', 'error');
                console.error('Error:', error);
            });
        });
    }
    
    /**
     * Show message in form
     */
    function showMessage(element, message, type) {
        element.textContent = message;
        element.className = 'form-message ' + type;
        element.style.display = 'block';
    }
});
