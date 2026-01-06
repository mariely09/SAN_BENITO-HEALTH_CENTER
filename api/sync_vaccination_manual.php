<?php
/**
 * API Endpoint: Manually Sync Single Vaccination to Google Calendar
 * Syncs a specific vaccination to the current user's Google Calendar
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/google_calendar_functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$vaccinationId = isset($input['vaccination_id']) ? (int)$input['vaccination_id'] : 0;

if ($vaccinationId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid vaccination ID']);
    exit;
}

$userId = $_SESSION['user_id'];

// Check if user has Google Calendar connected
$tokenCheck = mysqli_query($conn, "SELECT id FROM user_google_tokens WHERE user_id = $userId");
if (!$tokenCheck || mysqli_num_rows($tokenCheck) === 0) {
    echo json_encode([
        'success' => false,
        'needs_auth' => true,
        'message' => 'Please connect your Google Calendar first'
    ]);
    exit;
}

// Verify vaccination exists
$vaccinationQuery = "SELECT id FROM vaccinations WHERE id = $vaccinationId";
$vaccinationResult = mysqli_query($conn, $vaccinationQuery);

if (!$vaccinationResult || mysqli_num_rows($vaccinationResult) === 0) {
    echo json_encode(['success' => false, 'message' => 'Vaccination not found']);
    exit;
}

// Sync vaccination to user's calendar
$synced = syncVaccinationToCalendar($conn, $vaccinationId, $userId);

if ($synced) {
    echo json_encode([
        'success' => true,
        'message' => 'Vaccination synced to Google Calendar successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to sync vaccination to Google Calendar'
    ]);
}
