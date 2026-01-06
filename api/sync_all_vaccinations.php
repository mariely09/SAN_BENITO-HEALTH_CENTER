<?php
/**
 * API Endpoint: Sync All Vaccinations to Google Calendar
 * Syncs all upcoming vaccinations to the user's Google Calendar
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
$userFullname = $_SESSION['fullname'] ?? '';

error_log("sync_all_vaccinations: Starting sync for user $userId (role: $userRole)");

// Check if user has Google Calendar connected
$tokenCheck = mysqli_query($conn, "SELECT id FROM user_google_tokens WHERE user_id = $userId");
if (!$tokenCheck || mysqli_num_rows($tokenCheck) === 0) {
    error_log("sync_all_vaccinations: User $userId does not have Google Calendar connected");
    echo json_encode([
        'success' => false,
        'needs_auth' => true,
        'message' => 'Please connect your Google Calendar first'
    ]);
    exit;
}

// Get vaccinations to sync based on user role
if ($userRole === 'admin' || $userRole === 'worker') {
    // Workers and admins sync all upcoming vaccinations
    $query = "SELECT v.id, v.baby_id, v.vaccine_type, v.schedule_date, v.status, v.notes,
                     b.full_name as baby_name, b.parent_guardian_name
              FROM vaccinations v
              LEFT JOIN babies b ON v.baby_id = b.id
              WHERE v.schedule_date >= NOW()
              AND v.status IN ('pending', 'confirmed')
              ORDER BY v.schedule_date ASC";
} else {
    // Residents sync only their babies' vaccinations
    $userFullname = mysqli_real_escape_string($conn, $userFullname);
    $query = "SELECT v.id, v.baby_id, v.vaccine_type, v.schedule_date, v.status, v.notes,
                     b.full_name as baby_name, b.parent_guardian_name
              FROM vaccinations v
              LEFT JOIN babies b ON v.baby_id = b.id
              WHERE b.parent_guardian_name = '$userFullname'
              AND v.schedule_date >= NOW()
              AND v.status IN ('pending', 'confirmed')
              ORDER BY v.schedule_date ASC";
}

$result = mysqli_query($conn, $query);

if (!$result) {
    error_log("sync_all_vaccinations: Database query failed - " . mysqli_error($conn));
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . mysqli_error($conn)
    ]);
    exit;
}

$syncedCount = 0;
$skippedCount = 0;
$failedCount = 0;
$failedVaccinations = [];

while ($vaccination = mysqli_fetch_assoc($result)) {
    $vaccinationId = $vaccination['id'];
    
    // Check if already synced for this user
    $checkQuery = "SELECT id FROM vaccination_calendar_sync 
                   WHERE vaccination_id = $vaccinationId 
                   AND user_id = $userId";
    $checkResult = mysqli_query($conn, $checkQuery);
    
    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
        $skippedCount++;
        error_log("sync_all_vaccinations: Vaccination $vaccinationId already synced for user $userId, skipping");
        continue;
    }
    
    // Sync vaccination to user's calendar
    error_log("sync_all_vaccinations: Attempting to sync vaccination $vaccinationId to user $userId's calendar");
    
    $synced = syncVaccinationToCalendar($conn, $vaccinationId, $userId);
    
    if ($synced) {
        $syncedCount++;
        error_log("sync_all_vaccinations: Successfully synced vaccination $vaccinationId");
    } else {
        $failedCount++;
        $failedVaccinations[] = $vaccinationId;
        error_log("sync_all_vaccinations: Failed to sync vaccination $vaccinationId");
    }
}

error_log("sync_all_vaccinations: Summary - Synced: $syncedCount, Failed: $failedCount, Skipped: $skippedCount");

echo json_encode([
    'success' => true,
    'synced' => $syncedCount,
    'skipped' => $skippedCount,
    'failed' => $failedCount,
    'failed_vaccinations' => $failedVaccinations,
    'message' => "Synced $syncedCount vaccination(s), skipped $skippedCount already synced, $failedCount failed"
]);
