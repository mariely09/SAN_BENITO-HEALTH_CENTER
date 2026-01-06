<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

// Resident must be logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    echo json_encode(['error' => true, 'message' => 'Unauthorized']);
    exit;
}

$fullname = $_SESSION['fullname'];
$fullname_escaped = mysqli_real_escape_string($conn, $fullname);

// Get upcoming vaccinations for this resident's babies
$query = "SELECT v.id, v.vaccine_type, v.schedule_date, v.status, v.notes,
                 b.full_name as baby_name, b.date_of_birth
          FROM vaccinations v
          JOIN babies b ON v.baby_id = b.id
          WHERE b.parent_guardian_name = '$fullname_escaped'
          AND v.status IN ('pending', 'confirmed')
          AND v.schedule_date >= NOW()
          ORDER BY v.schedule_date ASC
          LIMIT 5";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['error' => true, 'message' => 'Database error']);
    exit;
}

$vaccinations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $vaccinations[] = [
        'id' => $row['id'],
        'baby_name' => $row['baby_name'],
        'vaccine_type' => $row['vaccine_type'],
        'schedule_date' => $row['schedule_date'],
        'status' => $row['status'],
        'notes' => $row['notes'],
        'date_of_birth' => $row['date_of_birth']
    ];
}

echo json_encode([
    'error' => false,
    'vaccinations' => $vaccinations,
    'count' => count($vaccinations)
]);
