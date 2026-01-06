<?php
/**
 * Disconnect Google Calendar API
 * Removes user's Google Calendar token from database
 */

require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not authenticated'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Delete user's Google Calendar token
    $delete_query = "DELETE FROM user_google_tokens WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Also delete all calendar sync records for this user
        $delete_sync_query = "DELETE FROM appointment_calendar_sync WHERE user_id = ?";
        $sync_stmt = mysqli_prepare($conn, $delete_sync_query);
        mysqli_stmt_bind_param($sync_stmt, 'i', $user_id);
        mysqli_stmt_execute($sync_stmt);
        
        $delete_vac_sync_query = "DELETE FROM vaccination_calendar_sync WHERE user_id = ?";
        $vac_sync_stmt = mysqli_prepare($conn, $delete_vac_sync_query);
        mysqli_stmt_bind_param($vac_sync_stmt, 'i', $user_id);
        mysqli_stmt_execute($vac_sync_stmt);
        
        echo json_encode([
            'success' => true,
            'message' => 'Google Calendar disconnected successfully'
        ]);
    } else {
        throw new Exception('Failed to delete token from database');
    }
    
} catch (Exception $e) {
    error_log('Error disconnecting Google Calendar: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
