<?php
/**
 * API Endpoint: Sync Appointment to Google Calendar
 * Handles AJAX requests to sync appointments with Google Calendar
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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit;
}

$appointmentId = (int)$data['appointment_id'];
$userId = $_SESSION['user_id'];

// Verify appointment belongs to user (for residents)
if ($_SESSION['role'] === 'resident') {
    $verifyQuery = "SELECT id FROM appointments WHERE id = $appointmentId AND user_id = $userId";
    $verifyResult = mysqli_query($conn, $verifyQuery);
    
    if (!$verifyResult || mysqli_num_rows($verifyResult) === 0) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or access denied']);
        exit;
    }
}

// Check if user has connected Google Calendar
$tokenQuery = "SELECT id FROM user_google_tokens WHERE user_id = $userId";
$tokenResult = mysqli_query($conn, $tokenQuery);

if (!$tokenResult || mysqli_num_rows($tokenResult) === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Google Calendar not connected',
        'needs_auth' => true
    ]);
    exit;
}

// Sync appointment to calendar
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
