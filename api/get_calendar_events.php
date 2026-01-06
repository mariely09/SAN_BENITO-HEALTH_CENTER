<?php
/**
 * API Endpoint: Get Upcoming Appointments
 * Retrieves upcoming appointments from the database
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'resident';

// Get max results parameter
$maxResults = isset($_GET['max_results']) ? (int)$_GET['max_results'] : 10;

// Set timezone to Asia/Manila to ensure correct time handling
date_default_timezone_set('Asia/Manila');

$events = [];

// ===== FETCH APPOINTMENTS =====
// Build query based on user role
if ($userRole === 'admin' || $userRole === 'worker') {
    // Workers and admins see all upcoming appointments
    $query = "SELECT a.*, u.username, u.fullname as user_fullname 
              FROM appointments a 
              LEFT JOIN users u ON a.user_id = u.id 
              WHERE a.preferred_datetime >= NOW() 
              AND a.status IN ('pending', 'confirmed')
              ORDER BY a.preferred_datetime ASC 
              LIMIT $maxResults";
} else {
    // Residents see only their own appointments
    $query = "SELECT a.*, u.username, u.fullname as user_fullname 
              FROM appointments a 
              LEFT JOIN users u ON a.user_id = u.id 
              WHERE a.user_id = $userId 
              AND a.preferred_datetime >= NOW() 
              AND a.status IN ('pending', 'confirmed')
              ORDER BY a.preferred_datetime ASC 
              LIMIT $maxResults";
}

$result = mysqli_query($conn, $query);

if ($result) {
    // Convert appointments to calendar event format
    while ($row = mysqli_fetch_assoc($result)) {
        $preferredDatetime = $row['preferred_datetime'];
        
        // Create DateTime objects with Manila timezone for accurate time handling
        $startDate = new DateTime($preferredDatetime, new DateTimeZone('Asia/Manila'));
        $endDate = clone $startDate;
        $endDate->modify('+1 hour');
        
        // Format for calendar widget - using ISO 8601 format with timezone
        $event = [
            'id' => 'appointment_' . $row['id'],
            'summary' => $row['appointment_type'] . ' - ' . $row['fullname'],
            'description' => $row['notes'] ?? '',
            'start' => [
                'dateTime' => $startDate->format('c'),
                'timeZone' => 'Asia/Manila'
            ],
            'end' => [
                'dateTime' => $endDate->format('c'),
                'timeZone' => 'Asia/Manila'
            ],
            'location' => 'Barangay Health Center',
            'status' => $row['status'],
            'type' => 'appointment',
            'appointment_type' => $row['appointment_type'],
            'raw_datetime' => $preferredDatetime
        ];
        
        $events[] = $event;
    }
}

// ===== FETCH VACCINATIONS =====
// Build query based on user role
if ($userRole === 'admin' || $userRole === 'worker') {
    // Workers and admins see all upcoming vaccinations
    $vaccinationQuery = "SELECT v.*, b.full_name as baby_name, b.parent_guardian_name 
                        FROM vaccinations v 
                        LEFT JOIN babies b ON v.baby_id = b.id 
                        WHERE v.schedule_date >= NOW() 
                        AND v.status IN ('pending', 'confirmed')
                        ORDER BY v.schedule_date ASC 
                        LIMIT $maxResults";
} else {
    // Residents see only vaccinations for their babies
    $userFullname = mysqli_real_escape_string($conn, $_SESSION['fullname']);
    $vaccinationQuery = "SELECT v.*, b.full_name as baby_name, b.parent_guardian_name 
                        FROM vaccinations v 
                        LEFT JOIN babies b ON v.baby_id = b.id 
                        WHERE b.parent_guardian_name = '$userFullname'
                        AND v.schedule_date >= NOW() 
                        AND v.status IN ('pending', 'confirmed')
                        ORDER BY v.schedule_date ASC 
                        LIMIT $maxResults";
}

$vaccinationResult = mysqli_query($conn, $vaccinationQuery);

if ($vaccinationResult) {
    // Convert vaccinations to calendar event format
    while ($row = mysqli_fetch_assoc($vaccinationResult)) {
        $scheduleDatetime = $row['schedule_date'];
        
        // Create DateTime objects with Manila timezone for accurate time handling
        $startDate = new DateTime($scheduleDatetime, new DateTimeZone('Asia/Manila'));
        $endDate = clone $startDate;
        $endDate->modify('+30 minutes'); // Vaccinations typically take 30 minutes
        
        // Format for calendar widget
        $event = [
            'id' => 'vaccination_' . $row['id'],
            'summary' => '💉 Vaccination - ' . $row['baby_name'],
            'description' => "Vaccine Type: {$row['vaccine_type']}\nBaby: {$row['baby_name']}\nParent/Guardian: {$row['parent_guardian_name']}\n" . ($row['notes'] ?? ''),
            'start' => [
                'dateTime' => $startDate->format('c'),
                'timeZone' => 'Asia/Manila'
            ],
            'end' => [
                'dateTime' => $endDate->format('c'),
                'timeZone' => 'Asia/Manila'
            ],
            'location' => 'Barangay Health Center',
            'status' => $row['status'],
            'type' => 'vaccination',
            'vaccine_type' => $row['vaccine_type'],
            'baby_name' => $row['baby_name'],
            'raw_datetime' => $scheduleDatetime
        ];
        
        $events[] = $event;
    }
}

// Sort all events by datetime
usort($events, function($a, $b) {
    return strtotime($a['start']['dateTime']) - strtotime($b['start']['dateTime']);
});

// Limit to max results after combining
$events = array_slice($events, 0, $maxResults);

echo json_encode([
    'success' => true,
    'events' => $events,
    'count' => count($events)
]);
