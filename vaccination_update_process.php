<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';
requireApproved();

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $vaccination_id = (int)$_POST['vaccination_id'];
    $vaccine_type = sanitize($_POST['vaccine_type']);
    $schedule_date = sanitize($_POST['schedule_date']);
    $status = sanitize($_POST['status']);
    $notes = sanitize($_POST['notes']);
    
    // Validate input
    if (empty($vaccination_id) || empty($vaccine_type) || empty($schedule_date) || empty($status)) {
        $error = 'Please fill in all required fields';
    } else {
        // Format schedule date
        $schedule_date = formatDate($schedule_date);
        
        // Additional fields for completed status
        $admin_id = null;
        $administered_date = null;
        
        if ($status == 'completed') {
            $admin_id = $_SESSION['user_id'];
            $administered_date = !empty($_POST['administered_date']) ? formatDate($_POST['administered_date']) : date('Y-m-d');
            
            // Update query with administered info
            $query = "UPDATE vaccinations SET 
                      vaccine_type = '$vaccine_type', 
                      schedule_date = '$schedule_date', 
                      status = '$status', 
                      notes = '$notes',
                      administered_by = $admin_id,
                      administered_date = '$administered_date'
                      WHERE id = $vaccination_id";
        } else {
            // Update without administered info
            $query = "UPDATE vaccinations SET 
                      vaccine_type = '$vaccine_type', 
                      schedule_date = '$schedule_date', 
                      status = '$status', 
                      notes = '$notes',
                      administered_by = NULL,
                      administered_date = NULL
                      WHERE id = $vaccination_id";
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update vaccination record
            if (!mysqli_query($conn, $query)) {
                throw new Exception('Failed to update vaccination: ' . mysqli_error($conn));
            }
            
            // Get baby details for appointment update
            $baby_query = "SELECT b.full_name, b.parent_guardian_name FROM babies b 
                          JOIN vaccinations v ON b.id = v.baby_id 
                          WHERE v.id = $vaccination_id";
            $baby_result = mysqli_query($conn, $baby_query);
            $baby_data = mysqli_fetch_assoc($baby_result);
            $baby_name = $baby_data['full_name'];
            $parent_name = $baby_data['parent_guardian_name'];
            
            // Update corresponding appointment
            $appointment_fullname = $baby_name . " (Parent: " . $parent_name . ")";
            $appointment_datetime = $schedule_date . " 09:00:00";
            $appointment_notes = "Vaccination: " . $vaccine_type . ($notes ? " - " . $notes : "");
            $appointment_status = ($status == 'completed') ? 'completed' : 'pending';
            
            $appointment_update = "UPDATE appointments SET 
                                  fullname = '$appointment_fullname',
                                  preferred_datetime = '$appointment_datetime',
                                  notes = '$appointment_notes',
                                  status = '$appointment_status'
                                  WHERE appointment_type = 'Vaccination' 
                                  AND notes LIKE 'Vaccination:%' 
                                  AND fullname LIKE '" . $baby_name . "%'
                                  ORDER BY id DESC LIMIT 1";
            
            // Note: This updates the most recent vaccination appointment for this baby
            // In a production system, you might want to store the appointment_id in the vaccinations table
            mysqli_query($conn, $appointment_update);
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Redirect to vaccinations list with success message
            header("Location: vaccinations.php?success=Vaccination updated successfully for " . urlencode($baby_name));
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
    
    // If there's an error, redirect back with error message
    if (!empty($error)) {
        header("Location: vaccinations.php?error=" . urlencode($error));
        exit;
    }
} else {
    // If not POST request, redirect to vaccinations page
    header("Location: vaccinations.php");
    exit;
}
?>