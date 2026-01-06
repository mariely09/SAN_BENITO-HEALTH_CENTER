<?php
session_start();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

require_once('config/database.php');
require_once('config/email.php');

// Clear any old messages at the start of a new session
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    unset($_SESSION['error_message']);
    unset($_SESSION['success_message']);
}

$error = '';
$otp_verified = false;

// Process OTP verification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $otp = trim($_POST['otp']);
    
    // Validate inputs
    if (empty($email) || empty($otp)) {
        $error = "Email and OTP are required.";
    } elseif (!preg_match('/^\d{6}$/', $otp)) {
        $error = "OTP must be a 6-digit number.";
    } else {
        // First, let's check if the OTP exists at all and get timing info
        $debug_query = "SELECT email, reset_otp, reset_otp_expiry, NOW() as server_time, 
                        TIMESTAMPDIFF(MINUTE, NOW(), reset_otp_expiry) as minutes_left 
                        FROM users WHERE email = ? AND reset_otp = ?";
        $debug_stmt = $conn->prepare($debug_query);
        
        if ($debug_stmt) {
            $debug_stmt->bind_param("ss", $email, $otp);
            $debug_stmt->execute();
            $debug_result = $debug_stmt->get_result();
            
            if ($debug_result->num_rows > 0) {
                $debug_row = $debug_result->fetch_assoc();
                
                // Always log this info for debugging
                error_log("OTP Verification Debug:");
                error_log("Email: $email");
                error_log("OTP: $otp");
                error_log("OTP Expiry: " . $debug_row['reset_otp_expiry']);
                error_log("Server Time: " . $debug_row['server_time']);
                error_log("Minutes Left: " . $debug_row['minutes_left']);
            }
        }
        
        // Now verify OTP exists and is still valid
        $query = "SELECT * FROM users WHERE email = ? AND reset_otp = ? AND reset_otp_expiry > NOW()";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            $error = "System error. Please try again.";
        } else {
            $stmt->bind_param("ss", $email, $otp);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Check if OTP exists but is expired
                $expired_query = "SELECT reset_otp, reset_otp_expiry, NOW() as server_time FROM users WHERE email = ? AND reset_otp = ?";
                $expired_stmt = $conn->prepare($expired_query);
                if ($expired_stmt) {
                    $expired_stmt->bind_param("ss", $email, $otp);
                    $expired_stmt->execute();
                    $expired_result = $expired_stmt->get_result();
                    
                    if ($expired_result->num_rows > 0) {
                        $expired_row = $expired_result->fetch_assoc();
                        
                        // Log debugging info in development mode
                        if(isDevelopmentMode()) {
                            error_log("OTP Debug - Email: $email, OTP: $otp");
                            error_log("OTP Expiry: " . $expired_row['reset_otp_expiry']);
                            error_log("Server Time: " . $expired_row['server_time']);
                        }
                        
                        $error = "OTP has expired. Please request a new password reset.";
                    } else {
                        $error = "Invalid OTP. Please check your email and try again.";
                    }
                } else {
                    $error = "Invalid OTP. Please check your email and try again.";
                }
            } else {
                // OTP is valid, store in session and redirect to reset password page
                $_SESSION['verified_email'] = $email;
                $_SESSION['verified_otp'] = $otp;
                $_SESSION['otp_verified'] = true;
                $_SESSION['success_message'] = "OTP verified successfully! You can now reset your password.";
                
                // Log successful OTP verification
                error_log("OTP verified successfully for email: $email");
                
                header("Location: reset_password.php");
                exit();
            }
        }
    }
}

// Get email from session or URL parameter
$email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : (isset($_GET['email']) ? $_GET['email'] : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
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
                            <h3>Enter Verification Code</h3>
                            <p>We've sent a 6-digit code to your email address</p>
                        </div>
                        

                        
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <?php if (!empty($email)): ?>
                            <!-- Show email info and hide field -->
                            <div class="alert alert-info">
                                <i class="fas fa-envelope me-2"></i>
                                OTP sent to: <strong><?= htmlspecialchars($email) ?></strong>
                            </div>
                            <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                            
                            <?php else: ?>
                            <!-- Show email field if not in session -->
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="Enter your email address" required>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="otp" class="form-label">Enter OTP Code</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="text" class="form-control" id="otp" name="otp" 
                                           placeholder="Enter 6-digit OTP" maxlength="6" 
                                           pattern="[0-9]{6}" required autofocus>
                                </div>
                                <small class="text-muted">Check your email for the 6-digit OTP code</small>
                            </div>
                            <button type="submit" class="btn btn-primary">Verify OTP</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="forgot_password.php" class="text-muted">Request New OTP</a>
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
        // OTP input formatting
        document.getElementById('otp').addEventListener('input', function(e) {
            // Only allow numbers
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Limit to 6 digits
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });
    </script>
</body>
</html>