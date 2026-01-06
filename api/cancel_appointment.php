<?php
/**
 * API: Cancel Appointment (Resident)
 * Allows residents to cancel their own appointments with a reason
 * Sends email notification to workers/admins
 */

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/functions.php';
require_once '../config/email.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?error=unauthorized");
    exit;
}

// Get appointment ID from GET parameter
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];

// Validate inputs
if ($appointment_id <= 0) {
    header("Location: ../resident_dashboard.php?error=" . urlencode("Invalid appointment ID"));
    exit;
}

// Set default cancellation reason
$cancellation_reason = "Cancelled by resident";

try {
    // Get appointment details and verify ownership
    $get_appointment = "SELECT * FROM appointments WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $get_appointment);
    mysqli_stmt_bind_param($stmt, 'ii', $appointment_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $appointment = mysqli_fetch_assoc($result);
    
    if (!$appointment) {
        header("Location: ../resident_dashboard.php?error=" . urlencode("Appointment not found or you don't have permission to cancel it"));
        exit;
    }
    
    // Check if appointment can be cancelled
    if ($appointment['status'] !== 'pending' && $appointment['status'] !== 'confirmed') {
        header("Location: ../resident_dashboard.php?error=" . urlencode("This appointment cannot be cancelled"));
        exit;
    }
    
    // Check if cancellation_reason column exists in archived_appointments, if not add it
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM archived_appointments LIKE 'cancellation_reason'");
    if (mysqli_num_rows($check_column) == 0) {
        mysqli_query($conn, "ALTER TABLE archived_appointments ADD COLUMN cancellation_reason TEXT AFTER notes");
    }
    
    // Check if cancelled_by_role column exists, if not add it
    $check_role_column = mysqli_query($conn, "SHOW COLUMNS FROM archived_appointments LIKE 'cancelled_by_role'");
    if (mysqli_num_rows($check_role_column) == 0) {
        mysqli_query($conn, "ALTER TABLE archived_appointments ADD COLUMN cancelled_by_role VARCHAR(20) AFTER cancellation_reason");
    }
    
    // Get the role of the person cancelling (resident)
    $cancelled_by_role = 'resident';
    
    // Archive the appointment with cancellation reason and role
    $archived_at = date('Y-m-d H:i:s'); // Current timestamp
    $archive_appointment = "INSERT INTO archived_appointments 
                           (original_id, user_id, fullname, appointment_type, preferred_datetime, notes, cancellation_reason, cancelled_by_role, status, created_at, archived_at, archived_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'cancelled', ?, ?, ?)";
    $archive_stmt = mysqli_prepare($conn, $archive_appointment);
    mysqli_stmt_bind_param(
        $archive_stmt,
        'iissssssssi',
        $appointment['id'],
        $appointment['user_id'],
        $appointment['fullname'],
        $appointment['appointment_type'],
        $appointment['preferred_datetime'],
        $appointment['notes'],
        $cancellation_reason,
        $cancelled_by_role,
        $appointment['created_at'],
        $archived_at,
        $user_id
    );
    mysqli_stmt_execute($archive_stmt);
    
    // Delete from Google Calendar for all users who synced this appointment
    if (file_exists('../config/google_calendar_functions.php')) {
        require_once '../config/google_calendar_functions.php';
        $sync_query = "SELECT user_id, google_event_id FROM appointment_calendar_sync WHERE appointment_id = ?";
        $sync_stmt = mysqli_prepare($conn, $sync_query);
        mysqli_stmt_bind_param($sync_stmt, 'i', $appointment_id);
        mysqli_stmt_execute($sync_stmt);
        $sync_result = mysqli_stmt_get_result($sync_stmt);
        
        while ($sync_row = mysqli_fetch_assoc($sync_result)) {
            $sync_user_id = $sync_row['user_id'];
            $google_event_id = $sync_row['google_event_id'];
            $access_token = getValidAccessToken($conn, $sync_user_id);
            if ($access_token && $google_event_id) {
                deleteCalendarEvent($access_token, $google_event_id);
                error_log("Deleted Google Calendar event $google_event_id for user $sync_user_id (appointment cancelled by resident)");
            }
        }
        
        // Delete sync records
        $delete_sync = "DELETE FROM appointment_calendar_sync WHERE appointment_id = ?";
        $delete_sync_stmt = mysqli_prepare($conn, $delete_sync);
        mysqli_stmt_bind_param($delete_sync_stmt, 'i', $appointment_id);
        mysqli_stmt_execute($delete_sync_stmt);
    }
    
    // Delete the appointment from main table
    $delete_query = "DELETE FROM appointments WHERE id = ?";
    $delete_stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($delete_stmt, 'i', $appointment_id);
    mysqli_stmt_execute($delete_stmt);
    
    // Send email notification to workers/admins
    $appointment_data = [
        'appointment_id' => $appointment['id'],
        'patient_name' => $appointment['fullname'],
        'appointment_type' => $appointment['appointment_type'],
        'preferred_datetime' => $appointment['preferred_datetime'],
        'notes' => $appointment['notes'],
        'cancellation_reason' => $cancellation_reason,
        'cancelled_by' => $_SESSION['fullname']
    ];
    
    sendAppointmentCancellationNotificationToWorkers($conn, $appointment_data);
    error_log("Appointment $appointment_id cancelled by resident (user $user_id). Email notifications sent to workers/admins.");
    
    // Redirect with success message
    header("Location: ../resident_dashboard.php?cancel_success=1");
    exit;
    
} catch (Exception $e) {
    error_log("Error cancelling appointment: " . $e->getMessage());
    header("Location: ../resident_dashboard.php?error=" . urlencode("Failed to cancel appointment. Please try again."));
    exit;
}
?>
