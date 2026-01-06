<?php
session_start();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

require_once('config/database.php');

// Check if OTP has been verified
if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
    $_SESSION['error_message'] = "Please verify your OTP first.";
    header("Location: verify_otp.php");
    exit();
}

$email = $_SESSION['verified_email'];
$otp = $_SESSION['verified_otp'];

// Double-check that the OTP is still valid
$query = "SELECT * FROM users WHERE email = ? AND reset_otp = ? AND reset_otp_expiry > NOW()";
$stmt = $conn->prepare($query);

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['error_message'] = "System error. Please try again.";
    header("Location: verify_otp.php");
    exit();
}

$stmt->bind_param("ss", $email, $otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "OTP has expired. Please request a new password reset.";
    // Clear session data
    unset($_SESSION['otp_verified']);
    unset($_SESSION['verified_email']);
    unset($_SESSION['verified_otp']);
    header("Location: forgot_password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom CSS -->
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
                            <h3>Reset Password</h3>
                            <p>Enter your new password</p>
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

                        <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php 
                            echo htmlspecialchars($_SESSION['error_message']);
                            unset($_SESSION['error_message']);
                            ?>
                        </div>
                        <?php endif; ?>

                        <form action="update_password_otp.php" method="POST">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group" style="position: relative;">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           placeholder="Enter new password" required style="padding-right: 40px;">
                                    <i class="fas fa-eye" id="toggleNewPassword" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; color: #6c757d;"></i>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group" style="position: relative;">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm new password" required style="padding-right: 40px;">
                                    <i class="fas fa-eye" id="toggleConfirmPassword" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; color: #6c757d;"></i>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Reset Password</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="login.php" class="text-muted">Back to Login</a>
                        </div>
                        <div class="text-center mt-2">
                            <p class="mb-0">Remember your password? <a href="login.php" style="color:rgb(42, 125, 75);">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility for New Password
        document.addEventListener('DOMContentLoaded', function() {
            const toggleNewPassword = document.getElementById('toggleNewPassword');
            const newPasswordInput = document.getElementById('new_password');
            
            if (toggleNewPassword) {
                toggleNewPassword.addEventListener('click', function() {
                    // Toggle the type attribute
                    const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    newPasswordInput.setAttribute('type', type);
                    
                    // Toggle the icon
                    if (type === 'password') {
                        toggleNewPassword.classList.remove('fa-eye-slash');
                        toggleNewPassword.classList.add('fa-eye');
                    } else {
                        toggleNewPassword.classList.remove('fa-eye');
                        toggleNewPassword.classList.add('fa-eye-slash');
                    }
                });
            }
            
            // Toggle password visibility for Confirm Password
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

        // Form validation
        document.querySelector('form').addEventListener('submit', function(event) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                event.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 6) {
                event.preventDefault();
                alert('Password must be at least 6 characters long!');
                return false;
            }
        });

    </script>
</body>
</html>