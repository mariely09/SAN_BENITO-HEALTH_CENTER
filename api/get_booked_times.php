<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => true, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Unauthorized']);
    exit;
}

// Get the selected date from query parameter
$selected_date = isset($_GET['date']) ? $_GET['date'] : '';

if (empty($selected_date)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Date parameter is required']);
    exit;
}

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $selected_date)) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

try {
    // Query to get all booked time slots for the selected date
    // Only consider appointments that are not cancelled or rejected
    $query = "SELECT TIME(preferred_datetime) as booked_time 
              FROM appointments 
              WHERE DATE(preferred_datetime) = ? 
              AND status IN ('pending', 'approved', 'confirmed')";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, 's', $selected_date);
    mysqli_stmt_execute($stmt);
    
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        throw new Exception('Database query failed: ' . mysqli_error($conn));
    }
    
    $booked_times = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Convert time to HH:MM format for comparison
        $booked_times[] = substr($row['booked_time'], 0, 5);
    }
    
    mysqli_stmt_close($stmt);
    
    // Return the booked times
    echo json_encode([
        'error' => false,
        'date' => $selected_date,
        'booked_times' => $booked_times
    ]);
    
} catch (Exception $e) {
    error_log('Error in get_booked_times.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => true, 
        'message' => 'Internal server error'
    ]);
}
?>