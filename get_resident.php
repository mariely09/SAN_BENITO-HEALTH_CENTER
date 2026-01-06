<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Only approved users can access
requireApproved();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Resident ID is required']);
    exit;
}

$id = intval($_GET['id']);
$query = "SELECT * FROM barangay_residents WHERE id = $id";
$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $resident = mysqli_fetch_assoc($result);
    echo json_encode(['success' => true, 'resident' => $resident]);
} else {
    echo json_encode(['success' => false, 'message' => 'Resident not found']);
}
?>