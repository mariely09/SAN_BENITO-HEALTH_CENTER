<?php 
session_start(); 

// Clear messages on page refresh or direct access
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_SESSION['keep_message'])) {
    unset($_SESSION['error_message']);
    unset($_SESSION['success_message']);
}

// Clear the keep_message flag after displaying messages
if (isset($_SESSION['keep_message'])) {
    unset($_SESSION['keep_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
                            <h3>Forgot Password</h3>
                            <p>Enter your email address and we'll send you a 6-digit OTP to reset your password</p>
                        </div>
                        
                        <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php 
                            echo htmlspecialchars($_SESSION['success_message']);
                            if (!isset($_SESSION['keep_message'])) {
                                unset($_SESSION['success_message']);
                            }
                            ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php 
                            echo htmlspecialchars($_SESSION['error_message']);
                            if (!isset($_SESSION['keep_message'])) {
                                unset($_SESSION['error_message']);
                            }
                            ?>
                        </div>
                        <?php endif; ?>

                        <form action="send_reset.php" method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="e.g., user@example.com" required>
                                </div>
                            </div>
                            <button type="submit" name="reset-button" class="btn btn-primary">Send OTP</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="login.php" class="text-muted">Back to Login</a>
                        </div>
                        <div class="text-center mt-2">
                            <p class="mb-0">Don't have an account? <a href="register.php" style="color:rgb(42, 125, 75);">Register here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>