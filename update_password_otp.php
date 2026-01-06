<?php
session_start();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

require_once('config/database.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: verify_otp.php");
    exit();
}

// Check if OTP has been verified
if (!isset($_SESSION['otp_verified']) || !$_SESSION['otp_verified']) {
    $_SESSION['error_message'] = "Please verify your OTP first.";
    header("Location: verify_otp.php");
    exit();
}

$email = $_SESSION['verified_email'];
$otp = $_SESSION['verified_otp'];
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

// Validate inputs
if (empty($new_password) || empty($confirm_password)) {
    $_SESSION['error_message'] = "All fields are required.";
    header("Location: reset_password.php");
    exit();
}

if ($new_password !== $confirm_password) {
    $_SESSION['error_message'] = "Passwords do not match.";
    header("Location: reset_password.php");
    exit();
}

if (strlen($new_password) < 6) {
    $_SESSION['error_message'] = "Password must be at least 6 characters long.";
    header("Location: reset_password.php");
    exit();
}

// Verify OTP is still valid before updating password
$query = "SELECT * FROM users WHERE email = ? AND reset_otp = ? AND reset_otp_expiry > NOW()";
$stmt = $conn->prepare($query);

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['error_message'] = "System error. Please try again.";
    header("Location: reset_password.php");
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

// Update password and clear OTP
$update_query = "UPDATE users SET password = ?, reset_otp = NULL, reset_otp_expiry = NULL WHERE email = ? AND reset_otp = ?";
$update_stmt = $conn->prepare($update_query);

if (!$update_stmt) {
    error_log("Prepare failed: " . $conn->error);
    $_SESSION['error_message'] = "System error. Please try again.";
    header("Location: reset_password.php");
    exit();
}

$update_stmt->bind_param("sss", $new_password, $email, $otp);

if ($update_stmt->execute()) {
    // Log successful password reset
    error_log("Password reset successful for email: $email");
    
    // Clear OTP session data
    unset($_SESSION['otp_verified']);
    unset($_SESSION['verified_email']);
    unset($_SESSION['verified_otp']);
    unset($_SESSION['reset_email']);
    
    // Set success message and redirect to login page
    $_SESSION['success_message'] = "Password has been reset successfully! You can now login with your new password.";
    header("Location: login.php");
    exit();
} else {
    error_log("Password update failed: " . $update_stmt->error);
    $_SESSION['error_message'] = "Failed to update password. Please try again.";
    header("Location: reset_password.php");
    exit();
}
?>