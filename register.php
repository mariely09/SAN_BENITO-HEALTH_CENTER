<?php
require_once 'config/database.php';
require_once 'config/session.php';

// Clear any existing session when user visits register page
// This ensures users must login again even if they were previously logged in
if (isLoggedIn()) {
    session_destroy();
    session_start();
}

$error = '';
$success = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    // Role selection: accept only 'worker' or 'resident'. Default to 'worker'.
    $allowed_roles = array('worker', 'resident');
    $role = 'worker'; // Default role is worker, admin must be created directly in DB
    if (isset($_POST['role']) && in_array($_POST['role'], $allowed_roles)) {
        $role = $_POST['role'];
    }
    
    // Validate input
    if (empty($username) || empty($password) || empty($confirm_password) || empty($fullname) || empty($email) || empty($contact_number)) {
        $error = "Please fill in all required fields";
    } elseif (strlen($password) < 6 || strlen($password) > 8) {
        $error = "Password must be between 6 to 8 characters long";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match. Please make sure both passwords are the same.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (!preg_match('/^09\d{9}$/', $contact_number)) {
        $error = "Please enter a valid contact number starting with '09' followed by 9 digits (e.g., 09123456789)";
    } else {
        // Check if username exists
        $query = "SELECT * FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) > 0) {
            $error = "This username is already taken. Please choose a different username.";
        } else {
            // No password hashing as per requirements
            // user_type mirrors role for compatibility with new workflows
            $user_type = $role;
            $query = "INSERT INTO users (username, password, fullname, email, contact_number, role, user_type, status) 
                      VALUES ('$username', '$password', '$fullname', '$email', '$contact_number', '$role', '$user_type', 'pending')";
            
            if (mysqli_query($conn, $query)) {
                $success = "Registration successful! Your account is pending approval. You will be notified once approved.";
            } else {
                $error = "Registration failed. Please try again or contact support if the problem persists.";
                $error = "Registration failed: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
     <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/register.css"> 
    <link rel="stylesheet" href="assets/css/login.css">
    <!-- Success/Error Messages Styles -->
    <link rel="stylesheet" href="assets/css/success-error_messages.css">
</head>
<body>
    <div class="register-container">
        <div class="card">
            <div class="card-body">
                <div class="register-content">
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
                            <h3>Create Account</h3>
                            <p>Please fill in your information to register</p>
                        </div>
                        
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                        <?php endif; ?>
                        

                        
                        <?php if (empty($success)): ?>
                        <form method="POST" action="">
                            <!-- Personal Information Section -->
                            <div id="personalSection">
                                <h6 class="mb-2" style="color: #2c3e50;">Personal Information</h6>
                                <div class="mb-3">
                                    <label for="fullname" class="form-label">Full Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="fullname" name="fullname" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               placeholder="your.email@example.com" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="contact_number" class="form-label">Contact Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                               placeholder="09XXXXXXXXX" required 
                                               maxlength="11" 
                                               pattern="09[0-9]{9}"
                                               oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 11);"
                                               title="Please enter a valid 11-digit number starting with '09'">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="role" class="form-label">Register as</label>
                                    <select class="form-select" name="role" id="role" required>
                                        <option value="" disabled <?php echo empty($_POST['role']) ? 'selected' : ''; ?>>Select your role</option>
                                        <option value="worker" <?php echo (isset($_POST['role']) && $_POST['role'] === 'worker') ? 'selected' : ''; ?>>Worker</option>
                                        <option value="resident" <?php echo (isset($_POST['role']) && $_POST['role'] === 'resident') ? 'selected' : ''; ?>>Resident</option>
                                    </select>
                                </div>

                                <div class="mb-3 text-center button-container">
                                    <button type="button" class="btn btn-secondary" id="nextBtn" onclick="showAccountSection()">
                                        Next <i class="fas fa-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Account Information Section -->
                            <div id="accountSection" style="display: none;">
                            <h6 class="mb-2" style="color: #2c3e50;">Account Information</h6>
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
                                    <input type="password" class="form-control" id="password" name="password" required 
                                           minlength="6" maxlength="8" style="padding-right: 40px;">
                                    <i class="fas fa-eye" id="togglePassword" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; color: #6c757d;"></i>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group" style="position: relative;">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                                           minlength="6" maxlength="8" style="padding-right: 40px;">
                                    <i class="fas fa-eye" id="toggleConfirmPassword" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; color: #6c757d;"></i>
                                </div>
                            </div>

                            <div class="mb-3 button-container">
                                <button type="submit" class="btn btn-primary w-100 mb-2" id="registerBtn">Register</button>
                                <button type="button" class="btn btn-outline-secondary w-100" onclick="showPersonalSection()">
                                    <i class="fas fa-arrow-left me-1"></i> Back
                                </button>
                            </div>
                        </div>
                </form>
                        <div class="text-center mt-3">
                            <p class="mb-0">Already have an account? <a href="login.php" style="color:rgb(42, 125, 75);">Login here</a></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <?php if (!empty($success)): ?>
    <div class="message-modal show message-modal-success" id="messageModal">
        <div class="message-modal-content">
            <div class="message-modal-header">
                <button class="message-modal-close" onclick="closeModal()">&times;</button>
                <div class="message-modal-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3 class="message-modal-title">Registration Successful!</h3>
            </div>
            <div class="message-modal-body">
                <p class="message-modal-message">
                    Your account has been created and is pending approval from an administrator. You will be notified once approved and can then login to your account.
                </p>
                <div class="message-modal-actions">
                    <a href="login.php" class="message-modal-btn message-modal-btn-primary">Go to Login</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Contact number validation
        document.getElementById('contact_number').addEventListener('keypress', function(e) {
            // Only allow numbers
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
            
            // Prevent input if length is already 11
            if (this.value.length >= 11) {
                e.preventDefault();
            }
        });
        
        // Real-time validation - clear errors when user starts typing
        document.getElementById('fullname').addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                const errorMsg = this.closest('.mb-3').querySelector('.invalid-feedback');
                if (errorMsg) errorMsg.remove();
            }
        });
        
        document.getElementById('email').addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                const errorMsg = this.closest('.mb-3').querySelector('.invalid-feedback');
                if (errorMsg) errorMsg.remove();
            }
        });
        
        document.getElementById('contact_number').addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                const errorMsg = this.closest('.mb-3').querySelector('.invalid-feedback');
                if (errorMsg) errorMsg.remove();
            }
        });

        document.getElementById('contact_number').addEventListener('paste', function(e) {
            e.preventDefault();
            let pastedText = (e.clipboardData || window.clipboardData).getData('text');
            pastedText = pastedText.replace(/[^0-9]/g, '').substring(0, 11);
            if (pastedText.length > 0 && pastedText.startsWith('09')) {
                this.value = pastedText;
            }
        });

        // Toggle password visibility
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle for password field
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

            // Toggle for confirm password field
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            if (toggleConfirmPassword) {
                toggleConfirmPassword.addEventListener('click', function() {
                    // Toggle the type attribute
                    const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPasswordInput.setAttribute('type', type);
                    
                    // Toggle the icon
                    if (type === 'password') {
                        toggleConfirmPassword.classList.remove('fa-eye-slash');
                        toggleConfirmPassword.classList.add('fa-eye');
                    } else {
                        toggleConfirmPassword.classList.remove('fa-eye');
                        toggleConfirmPassword.classList.add('fa-eye-slash');
                    }
                });
            }
        });

        // Form submission validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Clear previous errors
            clearPasswordErrors();
            
            let isValid = true;
            
            // Validate password length
            if (password.length < 6 || password.length > 8) {
                showFieldError('password', 'Password must be between 6 and 8 characters');
                isValid = false;
            }
            
            // Validate password match
            if (password !== confirmPassword) {
                showFieldError('confirm_password', 'Passwords do not match');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
        });
        
        // Real-time password validation
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Clear error when user starts typing
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                const errorMsg = this.closest('.mb-3').querySelector('.invalid-feedback');
                if (errorMsg) errorMsg.remove();
            }
            
            // Show real-time validation
            if (password.length > 0 && (password.length < 6 || password.length > 8)) {
                showFieldError('password', 'Password must be between 6 and 8 characters');
            }
            
            // Check if passwords match when both are filled
            if (confirmPassword.length > 0 && password !== confirmPassword) {
                showFieldError('confirm_password', 'Passwords do not match');
            } else if (confirmPassword.length > 0 && password === confirmPassword) {
                // Clear confirm password error if they now match
                const confirmField = document.getElementById('confirm_password');
                confirmField.classList.remove('is-invalid');
                const errorMsg = confirmField.closest('.mb-3').querySelector('.invalid-feedback');
                if (errorMsg) errorMsg.remove();
            }
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            // Clear error when user starts typing
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                const errorMsg = this.closest('.mb-3').querySelector('.invalid-feedback');
                if (errorMsg) errorMsg.remove();
            }
            
            // Show real-time validation
            if (confirmPassword.length > 0 && password !== confirmPassword) {
                showFieldError('confirm_password', 'Passwords do not match');
            }
        });
        
        // Helper function to clear password errors
        function clearPasswordErrors() {
            const fields = ['password', 'confirm_password'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                field.classList.remove('is-invalid');
                const inputGroup = field.closest('.mb-3');
                const errorMsg = inputGroup.querySelector('.invalid-feedback');
                if (errorMsg) {
                    errorMsg.remove();
                }
            });
        }

        // Section navigation functions
        function showAccountSection() {
            // Clear any previous error styling
            clearFieldErrors();
            
            // Get form field values
            const fullname = document.getElementById('fullname').value.trim();
            const email = document.getElementById('email').value.trim();
            const contactNumber = document.getElementById('contact_number').value.trim();
            
            let isValid = true;
            let errorMessage = '';
            
            // Validate full name
            if (!fullname) {
                showFieldError('fullname', 'Full name is required');
                isValid = false;
            } else if (fullname.length < 2) {
                showFieldError('fullname', 'Full name must be at least 2 characters');
                isValid = false;
            } else if (!/^[a-zA-Z\s\-\.]+$/.test(fullname)) {
                showFieldError('fullname', 'Full name can only contain letters, spaces, hyphens, and periods');
                isValid = false;
            }
            
            // Validate email
            if (!email) {
                showFieldError('email', 'Email address is required');
                isValid = false;
            } else {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    showFieldError('email', 'Please enter a valid email address');
                    isValid = false;
                }
            }
            
            // Validate contact number
            if (!contactNumber) {
                showFieldError('contact_number', 'Contact number is required');
                isValid = false;
            } else {
                const contactRegex = /^09\d{9}$/;
                if (!contactRegex.test(contactNumber)) {
                    showFieldError('contact_number', 'Contact number must start with 09 and be exactly 11 digits');
                    isValid = false;
                }
            }
            
            // If validation fails, show general error message
            if (!isValid) {
                showGeneralError('Please correct the errors above before proceeding.');
                return;
            }
            
            // Hide Personal Information section and show Account section
            document.getElementById('personalSection').style.display = 'none';
            document.getElementById('accountSection').style.display = 'block';
        }
        
        // Helper function to show field-specific errors
        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const inputGroup = field.closest('.input-group') || field.closest('.mb-3');
            
            // Add error styling
            field.classList.add('is-invalid');
            
            // Remove existing error message
            const existingError = inputGroup.querySelector('.invalid-feedback');
            if (existingError) {
                existingError.remove();
            }
            
            // Add error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            errorDiv.textContent = message;
            inputGroup.appendChild(errorDiv);
        }
        
        // Helper function to clear all field errors
        function clearFieldErrors() {
            // Remove error styling from all fields
            const fields = ['fullname', 'email', 'contact_number'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                field.classList.remove('is-invalid');
                
                // Remove error messages
                const inputGroup = field.closest('.input-group') || field.closest('.mb-3');
                const errorMsg = inputGroup.querySelector('.invalid-feedback');
                if (errorMsg) {
                    errorMsg.remove();
                }
            });
            
            // Clear general error message
            const generalError = document.getElementById('generalError');
            if (generalError) {
                generalError.remove();
            }
        }
        
        // Helper function to show general error message
        function showGeneralError(message) {
            // Remove existing general error
            const existingError = document.getElementById('generalError');
            if (existingError) {
                existingError.remove();
            }
            
            // Create and show new error message
            const errorDiv = document.createElement('div');
            errorDiv.id = 'generalError';
            errorDiv.className = 'alert alert-danger mt-3';
            errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + message;
            
            // Insert before the Next button
            const nextBtn = document.getElementById('nextBtn');
            nextBtn.parentNode.insertBefore(errorDiv, nextBtn);
        }
        
        function showPersonalSection() {
            // Show Personal Information section and hide Account section
            document.getElementById('personalSection').style.display = 'block';
            document.getElementById('accountSection').style.display = 'none';
        }

        // Modal functions
        function closeModal() {
            const modal = document.getElementById('messageModal');
            if (modal) {
                modal.classList.add('hiding');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 300);
            }
        }

        // Auto redirect after 8 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('messageModal');
            if (modal) {
                setTimeout(() => {
                    closeModal();
                }, 8000);
            }
        });

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('messageModal');
            if (modal && event.target === modal) {
                closeModal();
            }
        });
    </script>
</body>
</html> 