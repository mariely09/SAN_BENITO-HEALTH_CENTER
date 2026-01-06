<?php
/**
 * API Endpoint: Manually Sync Single Appointment to Google Calendar
 * Syncs a specific appointment to the current user's Google Calendar
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
$appointmentId = isset($input['appointment_id']) ? (int)$input['appointment_id'] : 0;

if ($appointmentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
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

// Verify appointment exists
$appointmentQuery = "SELECT id FROM appointments WHERE id = $appointmentId";
$appointmentResult = mysqli_query($conn, $appointmentQuery);

if (!$appointmentResult || mysqli_num_rows($appointmentResult) === 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

// Sync appointment to user's calendar
$synced = syncAppointmentToCalendar($conn, $appointmentId, $userId);

if ($synced) {
    echo json_encode([
        'success' => true,
        'message' => 'Appointment synced to Google Calendar successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to sync appointment to Google Calendar'
    ]);
}
