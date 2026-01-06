<?php
/**
 * Google Calendar Authentication Initiator
 * Redirects user to Google OAuth consent screen
 */

require_once '../config/session.php';
require_once '../config/google_calendar_config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../login.php?error=unauthorized');
    exit;
}

$userId = $_SESSION['user_id'];

// Generate and redirect to Google OAuth URL
$authUrl = getGoogleAuthUrl($userId);
header('Location: ' . $authUrl);
exit;
