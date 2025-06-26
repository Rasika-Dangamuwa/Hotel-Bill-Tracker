<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Bill Tracking System - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .system-title {
            font-size: 22px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .company-name {
            font-size: 14px;
            color: #718096;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #718096;
            cursor: pointer;
            font-size: 14px;
            padding: 4px;
        }

        .toggle-password:hover {
            color: #4a5568;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }

        .loading {
            display: none;
            text-align: center;
            margin-top: 10px;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            .system-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo">HT</div>
            <div class="system-title">Hotel Bill Tracker</div>
            <div class="company-name">Nestle Lanka Limited</div>
        </div>

        <div id="alert" class="alert"></div>

        <form id="loginForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <span id="toggleText">Show</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">
                Sign In
            </button>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <span>Signing in...</span>
            </div>
        </form>

        <div class="footer">
            <div>&copy; 2025 Nestle Lanka Limited</div>
            <div>Hotel Bill Tracking System v1.0</div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleText = document.getElementById('toggleText');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleText.textContent = 'Hide';
            } else {
                passwordInput.type = 'password';
                toggleText.textContent = 'Show';
            }
        }

        // Show alert message
        function showAlert(message, type = 'error') {
            const alert = document.getElementById('alert');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alert.style.display = 'block';
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        // Show/hide loading state
        function setLoading(isLoading) {
            const loginBtn = document.getElementById('loginBtn');
            const loading = document.getElementById('loading');
            
            if (isLoading) {
                loginBtn.disabled = true;
                loginBtn.style.display = 'none';
                loading.style.display = 'block';
            } else {
                loginBtn.disabled = false;
                loginBtn.style.display = 'block';
                loading.style.display = 'none';
            }
        }

        // Handle form submission
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            // Basic validation
            if (!email || !password) {
                showAlert('Please fill in all fields');
                return;
            }
            
            if (!email.includes('@')) {
                showAlert('Please enter a valid email address');
                return;
            }
            
            setLoading(true);
            
            try {
                // Simulate API call (replace with actual PHP endpoint)
                const response = await fetch('login_process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    showAlert(result.message || 'Invalid email or password');
                }
            } catch (error) {
                showAlert('Connection error. Please try again.');
                console.error('Login error:', error);
            } finally {
                setLoading(false);
            }
        });

        // Auto-focus email field
        document.getElementById('email').focus();

        // Handle Enter key in password field
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').dispatchEvent(new Event('submit'));
            }
        });

        // Demo functionality - remove in production
        function fillDemoCredentials() {
            document.getElementById('email').value = 'accounts@nestle.lk';
            document.getElementById('password').value = 'password';
        }

        // Add demo button for testing (remove in production)
        const demoBtn = document.createElement('button');
        demoBtn.textContent = 'Fill Demo Credentials';
        demoBtn.type = 'button';
        demoBtn.onclick = fillDemoCredentials;
        demoBtn.style.cssText = `
            width: 100%;
            padding: 8px;
            background: #e2e8f0;
            color: #4a5568;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            margin-top: 15px;
        `;
        document.querySelector('.login-container form').appendChild(demoBtn);
    </script>
</body>
</html>