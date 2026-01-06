<?php
/**
 * Google Calendar OAuth Callback Handler
 * Handles the OAuth callback from Google and stores tokens
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/google_calendar_config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php?error=unauthorized');
    exit;
}

// Check for authorization code
if (!isset($_GET['code'])) {
    header('Location: index.php?error=calendar_auth_failed');
    exit;
}

// Get state parameter (contains user_id)
$state = isset($_GET['state']) ? json_decode(base64_decode($_GET['state']), true) : null;
if (!$state || !isset($state['user_id'])) {
    header('Location: index.php?error=invalid_state');
    exit;
}

$userId = (int)$state['user_id'];
$sessionUserId = (int)$_SESSION['user_id'];

// Verify user_id matches session
if ($userId !== $sessionUserId) {
    header('Location: index.php?error=user_mismatch');
    exit;
}

// Exchange authorization code for tokens
$code = $_GET['code'];
$tokens = exchangeCodeForToken($code);

// Debug: Log token exchange result
error_log("Token Exchange Result: " . print_r($tokens, true));

if (!$tokens || !isset($tokens['access_token'])) {
    error_log("Token exchange failed - No access token received");
    header('Location: index.php?error=token_exchange_failed');
    exit;
}

// Store tokens in database
$accessToken = mysqli_real_escape_string($conn, $tokens['access_token']);
$refreshToken = isset($tokens['refresh_token']) ? mysqli_real_escape_string($conn, $tokens['refresh_token']) : '';
$expiresIn = $tokens['expires_in'];
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

// Check if user already has tokens
$checkQuery = "SELECT id FROM user_google_tokens WHERE user_id = $userId";
$checkResult = mysqli_query($conn, $checkQuery);

if (mysqli_num_rows($checkResult) > 0) {
    // Update existing tokens
    $updateQuery = "UPDATE user_google_tokens 
                   SET access_token = '$accessToken', 
                       refresh_token = '$refreshToken', 
                       token_expiry = '$expiresAt',
                       updated_at = NOW()
                   WHERE user_id = $userId";
    $success = mysqli_query($conn, $updateQuery);
} else {
    // Insert new tokens
    $insertQuery = "INSERT INTO user_google_tokens 
                   (user_id, access_token, refresh_token, token_expiry, created_at) 
                   VALUES ($userId, '$accessToken', '$refreshToken', '$expiresAt', NOW())";
    $success = mysqli_query($conn, $insertQuery);
}

if ($success) {
    error_log("Tokens saved successfully for user_id: $userId");
    
    // Verify tokens were actually saved
    $verifyQuery = "SELECT id, access_token FROM user_google_tokens WHERE user_id = $userId";
    $verifyResult = mysqli_query($conn, $verifyQuery);
    if ($verifyResult && mysqli_num_rows($verifyResult) > 0) {
        error_log("Verification: Tokens found in database");
    } else {
        error_log("Verification: Tokens NOT found in database!");
    }
    
    // Redirect based on user role
    $role = $_SESSION['role'];
    if ($role === 'admin') {
        header('Location: admin_dashboard.php?calendar_connected=1');
    } elseif ($role === 'worker') {
        header('Location: worker_dashboard.php?calendar_connected=1');
    } else {
        header('Location: resident_dashboard.php?calendar_connected=1');
    }
} else {
    error_log("Failed to save tokens. MySQL Error: " . mysqli_error($conn));
    header('Location: index.php?error=token_storage_failed');
}

exit;
