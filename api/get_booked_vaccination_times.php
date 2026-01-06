<?php
/**
 * API: Get Booked Vaccination Times
 * Returns array of booked time slots for a specific date
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized', 'booked_times' => []]);
    exit;
}

// Get date parameter
$date = isset($_GET['date']) ? $_GET['date'] : '';

if (empty($date)) {
    echo json_encode(['error' => 'Date parameter required', 'booked_times' => []]);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format', 'booked_times' => []]);
    exit;
}

try {
    // Get all booked times for the specified date from vaccinations table
    $query = "SELECT TIME_FORMAT(schedule_date, '%H:%i') as booked_time 
              FROM vaccinations 
              WHERE DATE(schedule_date) = ? 
              AND status IN ('pending', 'confirmed')";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 's', $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $booked_times = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if (!empty($row['booked_time'])) {
            $booked_times[] = $row['booked_time'];
        }
    }
    
    // Return booked times
    echo json_encode([
        'success' => true,
        'date' => $date,
        'booked_times' => $booked_times,
        'count' => count($booked_times)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'booked_times' => []
    ]);
}
?>
