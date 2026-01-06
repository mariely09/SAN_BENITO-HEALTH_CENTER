<?php
require_once 'config/database.php';
require_once 'config/session.php';

// FORCE LOGOUT: Clear any existing session when user visits login page (GET request only)
// This ensures users MUST login again regardless of previous login status
// But don't clear session during POST (login form submission)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION) && !empty($_SESSION)) {
    // Preserve success message if it exists
    $success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
    
    // Clear all session variables
    $_SESSION = [];
    
    // Delete the session cookie if it exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Start a fresh session
    session_start();
    
    // Restore success message if it existed
    if ($success_message !== null) {
        $_SESSION['success_message'] = $success_message;
    }
}

// Prevent caching of this page to avoid browser back button issues
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        $query = "SELECT * FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $query);

        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);

            // Check password - support both hashed and plain text
            $passwordMatch = false;
            
            // Check if password is hashed
            if (password_get_info($user['password'])['algo'] !== null) {
                // Password is hashed - use password_verify
                $passwordMatch = password_verify($password, $user['password']);
            } else {
                // Password is plain text - use direct comparison
                $passwordMatch = ($password == $user['password']);
            }
            
            if ($passwordMatch) {
                // Check if user account is pending approval (applies to all non-admin users)
                if ($user['role'] != 'admin' && $user['status'] == 'pending') {
                    $error = "Your account is pending approval. Please wait for administrator confirmation. You will receive an email notification once your account is approved.";
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['fullname'] = $user['fullname'];
                    $_SESSION['role'] = $user['role'];
                    // store user_type for compatibility
                    $_SESSION['user_type'] = isset($user['user_type']) ? $user['user_type'] : $user['role'];
                    $_SESSION['status'] = $user['status'];

                    // Redirect based on role/user_type
                    $roleLower = strtolower($user['role']);
                    if ($roleLower === 'resident') {
                        header("Location: resident_dashboard.php");
                        exit;
                    } elseif ($roleLower === 'worker') {
                        header("Location: worker_dashboard.php");
                        exit;
                    } elseif ($roleLower === 'admin') {
                        header("Location: admin_dashboard.php");
                        exit;
                    } else {
                        // default dashboard for others
                        header("Location: admin_dashboard.php");
                        exit;
                    }
                }
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "Username not found. Please check your username or register a new account.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Login CSS -->
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>
    <div class="login-container">
        <div class="card">
            <div class="card-body">
                <div class="login-content">
                    <!-- Logo Section -->
                    <div class="logo-section">
                        <div class="logo-icon">
                            <img src="assets/img/san-benito-logo.png" alt="San Benito Logo">
                        </div>
                        <div class="system-name">
                            <h4>San Benito Health Center</h4>
                            <p class="system-subtitle">Barangay Health Inventory System</p>
                        </div>
                    </div>

                    <!-- Form Section -->
                    <div class="form-section">
                        <div class="form-title">
                            <h3>Welcome Back</h3>
                            <p>Please sign in to your account</p>
                        </div>

                        <?php if (isset($_SESSION['success_message'])): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php
                                echo htmlspecialchars($_SESSION['success_message']);
                                unset($_SESSION['success_message']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group" style="position: relative;">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required style="padding-right: 40px;">
                                    <i class="fas fa-eye" id="togglePassword" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; color: #6c757d;"></i>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Login</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="forgot_password.php" class="text-muted">Forgot Password?</a>
                        </div>
                        <div class="text-center mt-2">
                            <p class="mb-0">Don't have an account? <a href="register.php"
                                    style="color:rgb(42, 125, 75);">Register here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prevent browser back button bypass and ensure fresh login
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, null, window.location.href);
        };
        
        // Clear any stored authentication data in browser storage
        if (typeof(Storage) !== "undefined") {
            localStorage.clear();
            sessionStorage.clear();
        }
        
        // Disable browser autocomplete for security
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.setAttribute('autocomplete', 'off');
            }
        });
        
        // Clear form on page load to prevent cached values
        window.addEventListener('load', function() {
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
        });
        
        // Toggle password visibility
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    // Toggle the type attribute
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Toggle the icon
                    if (type === 'password') {
                        togglePassword.classList.remove('fa-eye-slash');
                        togglePassword.classList.add('fa-eye');
                    } else {
                        togglePassword.classList.remove('fa-eye');
                        togglePassword.classList.add('fa-eye-slash');
                    }
                });
            }
        });
    </script>
</body>

</html>