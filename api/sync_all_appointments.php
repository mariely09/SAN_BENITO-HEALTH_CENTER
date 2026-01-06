<?php
/**
 * API Endpoint: Sync All Appointments to Google Calendar
 * Automatically syncs all user's appointments when they connect Google Calendar
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

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'resident';

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

// Get all upcoming appointments for the user
if ($userRole === 'admin' || $userRole === 'worker') {
    // Workers and admins sync ALL appointments to THEIR OWN calendar
    $query = "SELECT id FROM appointments 
              WHERE preferred_datetime >= NOW() 
              AND status IN ('pending', 'confirmed')
              ORDER BY preferred_datetime ASC";
} else {
    // Residents sync only their own appointments to their own calendar
    $query = "SELECT id FROM appointments 
              WHERE user_id = $userId 
              AND preferred_datetime >= NOW() 
              AND status IN ('pending', 'confirmed')
              ORDER BY preferred_datetime ASC";
}

$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve appointments'
    ]);
    exit;
}

$syncedCount = 0;
$failedCount = 0;
$skippedCount = 0;

$failedAppointments = [];

while ($row = mysqli_fetch_assoc($result)) {
    $appointmentId = $row['id'];
    
    // Check if already synced for this user
    $checkQuery = "SELECT id FROM appointment_calendar_sync 
                   WHERE appointment_id = $appointmentId 
                   AND user_id = $userId";
    $checkResult = mysqli_query($conn, $checkQuery);
    
    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
        $skippedCount++;
        continue;
    }
    
    // Sync appointment to the logged-in user's Google Calendar
    // For residents: syncs their own appointments
    // For workers/admins: syncs all appointments to their calendar
    error_log("sync_all_appointments: Attempting to sync appointment $appointmentId to user $userId's calendar");
    $synced = syncAppointmentToCalendar($conn, $appointmentId, $userId);
    
    if ($synced) {
        $syncedCount++;
        error_log("sync_all_appointments: Successfully synced appointment $appointmentId");
    } else {
        $failedCount++;
        $failedAppointments[] = $appointmentId;
        error_log("sync_all_appointments: Failed to sync appointment $appointmentId");
    }
}

error_log("sync_all_appointments: Summary - Synced: $syncedCount, Failed: $failedCount, Skipped: $skippedCount");

echo json_encode([
    'success' => true,
    'message' => "Synced $syncedCount appointments to Google Calendar",
    'synced' => $syncedCount,
    'failed' => $failedCount,
    'skipped' => $skippedCount,
    'failed_appointments' => $failedAppointments
]);
