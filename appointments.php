<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent browser caching - force fresh page load
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';
requireApproved();

// Handle POST request for cancellation with reason
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel' && isset($_POST['appointment_id'])) {
    $id = intval($_POST['appointment_id']);
    $cancellation_reason = mysqli_real_escape_string($conn, $_POST['cancellation_reason']);

    // Store current filter and search parameters for redirect
    $current_filter = isset($_POST['filter']) ? '?filter=' . $_POST['filter'] : '';
    $current_search = isset($_POST['search']) ? (!empty($current_filter) ? '&' : '?') . 'search=' . urlencode($_POST['search']) : '';
    $redirect_base = "appointments.php" . $current_filter . $current_search;
    $redirect_base .= (strpos($redirect_base, '?') === false) ? '?' : '&';

    // Get appointment details first
    $get_appointment = "SELECT * FROM appointments WHERE id = $id";
    $appointment_result = mysqli_query($conn, $get_appointment);
    $appointment = mysqli_fetch_assoc($appointment_result);

    if ($appointment) {
        // Check if cancellation_reason column exists, if not add it
        $check_column = mysqli_query($conn, "SHOW COLUMNS FROM archived_appointments LIKE 'cancellation_reason'");
        if (mysqli_num_rows($check_column) == 0) {
            mysqli_query($conn, "ALTER TABLE archived_appointments ADD COLUMN cancellation_reason TEXT AFTER notes");
        }
        
        // Check if cancelled_by_role column exists, if not add it
        $check_role_column = mysqli_query($conn, "SHOW COLUMNS FROM archived_appointments LIKE 'cancelled_by_role'");
        if (mysqli_num_rows($check_role_column) == 0) {
            mysqli_query($conn, "ALTER TABLE archived_appointments ADD COLUMN cancelled_by_role VARCHAR(20) AFTER cancellation_reason");
        }
        
        // Get the role of the person cancelling (worker or admin)
        $cancelled_by_role = $_SESSION['role'] ?? 'unknown';

        // Archive to archived_appointments with cancellation reason and role
        $archived_at = date('Y-m-d H:i:s'); // Current timestamp
        $archive_appointment = "INSERT INTO archived_appointments (original_id, user_id, fullname, appointment_type, preferred_datetime, notes, cancellation_reason, cancelled_by_role, status, created_at, archived_at, archived_by) 
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
            $_SESSION['user_id']
        );
        mysqli_stmt_execute($archive_stmt);

        // If it's a vaccination, also archive to archived_vaccinations
        if ($appointment['appointment_type'] == 'Vaccination') {
            preg_match('/^([^(]+)/', $appointment['fullname'], $matches);
            $baby_name = trim($matches[1] ?? '');

            $baby_query = "SELECT id FROM babies WHERE full_name = ?";
            $baby_stmt = mysqli_prepare($conn, $baby_query);
            mysqli_stmt_bind_param($baby_stmt, 's', $baby_name);
            mysqli_stmt_execute($baby_stmt);
            $baby_result = mysqli_stmt_get_result($baby_stmt);
            $baby_data = mysqli_fetch_assoc($baby_result);

            if ($baby_data) {
                // Extract vaccine type from notes (format: "Vaccine Type: VaccineType")
                preg_match('/Vaccine Type:\s*([^\n]+)/', $appointment['notes'], $vaccine_matches);
                $vaccine_type = trim($vaccine_matches[1] ?? 'Unknown');

                $schedule_date_formatted = date('Y-m-d', strtotime($appointment['preferred_datetime']));
                $user_id = $_SESSION['user_id'];

                $archive_vaccination = "INSERT INTO archived_vaccinations (original_id, baby_id, vaccine_type, schedule_date, status, notes, administered_by, administered_date, archived_by, archive_reason) 
                                      VALUES (0, ?, ?, ?, 'cancelled', ?, NULL, NULL, ?, ?)";
                $vacc_stmt = mysqli_prepare($conn, $archive_vaccination);
                mysqli_stmt_bind_param(
                    $vacc_stmt,
                    'isssis',
                    $baby_data['id'],
                    $vaccine_type,
                    $schedule_date_formatted,
                    $appointment['notes'],
                    $user_id,
                    $cancellation_reason
                );
                mysqli_stmt_execute($vacc_stmt);
            }
        }

        // Send email notification to resident BEFORE deleting (with cancellation reason)
        require_once 'config/email.php';
        sendAppointmentStatusUpdateToResident($conn, $id, 'cancelled', $cancellation_reason);
        error_log("Email notification sent to resident for cancelled appointment $id with reason");
        
        // Delete from Google Calendar for all users who synced this appointment
        require_once 'config/google_calendar_functions.php';
        $sync_query = "SELECT user_id, google_event_id FROM appointment_calendar_sync WHERE appointment_id = $id";
        $sync_result = mysqli_query($conn, $sync_query);
        while ($sync_row = mysqli_fetch_assoc($sync_result)) {
            $sync_user_id = $sync_row['user_id'];
            $google_event_id = $sync_row['google_event_id'];
            $access_token = getValidAccessToken($conn, $sync_user_id);
            if ($access_token && $google_event_id) {
                deleteCalendarEvent($access_token, $google_event_id);
                error_log("Deleted Google Calendar event $google_event_id for user $sync_user_id (appointment cancelled)");
            }
        }
        
        // Delete sync records
        $delete_sync = "DELETE FROM appointment_calendar_sync WHERE appointment_id = $id";
        mysqli_query($conn, $delete_sync);
        
        // Delete from main appointments table
        $delete_query = "DELETE FROM appointments WHERE id = $id";
        mysqli_query($conn, $delete_query);

        header("Location: " . $redirect_base . "success=" . urlencode("Appointment cancelled successfully"));
        exit;
    }
}

// Handle appointment actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);

    // Store current filter and search parameters for redirect
    $current_filter = isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : '';
    $current_search = isset($_GET['search']) ? (!empty($current_filter) ? '&' : '?') . 'search=' . urlencode($_GET['search']) : '';
    $redirect_base = "appointments.php" . $current_filter . $current_search;
    $redirect_base .= (strpos($redirect_base, '?') === false) ? '?' : '&';

    if ($action === 'confirm') {
        // Get appointment details first
        $get_appointment = "SELECT * FROM appointments WHERE id = $id";
        $appointment_result = mysqli_query($conn, $get_appointment);
        $appointment = mysqli_fetch_assoc($appointment_result);
        
        // If it's a vaccination appointment, create entry in vaccinations table
        if ($appointment && $appointment['appointment_type'] == 'Vaccination') {
            // Try to extract baby name from notes first (more reliable)
            $baby_name = '';
            if (preg_match('/BABY VACCINATION - ([^(]+)\s*\(Age:/', $appointment['notes'], $note_matches)) {
                $baby_name = trim($note_matches[1]);
                error_log("Extracted baby name from notes: $baby_name");
            }
            
            // Fallback: extract from fullname
            if (empty($baby_name)) {
                preg_match('/^([^(]+)/', $appointment['fullname'], $matches);
                $baby_name = trim($matches[1] ?? '');
                error_log("Extracted baby name from fullname: $baby_name");
            }
            
            // Find baby_id and parent info
            $baby_query = "SELECT id, parent_guardian_name FROM babies WHERE full_name = ?";
            $baby_stmt = mysqli_prepare($conn, $baby_query);
            mysqli_stmt_bind_param($baby_stmt, 's', $baby_name);
            mysqli_stmt_execute($baby_stmt);
            $baby_result = mysqli_stmt_get_result($baby_stmt);
            $baby_data = mysqli_fetch_assoc($baby_result);
            
            if ($baby_data) {
                // Extract vaccine type from notes (format: "Vaccine Type: VaccineType")
                preg_match('/Vaccine Type:\s*([^\n]+)/', $appointment['notes'], $vaccine_matches);
                $vaccine_type = trim($vaccine_matches[1] ?? 'Unknown');
                
                // Extract only additional notes (remove system-generated info)
                $notes = '';
                if (preg_match('/Additional Notes:\s*(.+)/s', $appointment['notes'], $notes_matches)) {
                    $notes = trim($notes_matches[1]);
                }
                
                // Log for debugging
                error_log("Extracting vaccine from appointment $id: baby_name=$baby_name, vaccine_type=$vaccine_type, additional_notes=" . substr($notes, 0, 50));
                
                $schedule_datetime = $appointment['preferred_datetime'];
                
                // Check if vaccination already exists
                $check_duplicate = "SELECT id FROM vaccinations 
                                   WHERE baby_id = ? 
                                   AND vaccine_type = ? 
                                   AND DATE(schedule_date) = DATE(?)";
                $check_stmt = mysqli_prepare($conn, $check_duplicate);
                mysqli_stmt_bind_param($check_stmt, 'iss', $baby_data['id'], $vaccine_type, $schedule_datetime);
                mysqli_stmt_execute($check_stmt);
                $duplicate_result = mysqli_stmt_get_result($check_stmt);
                
                if (mysqli_num_rows($duplicate_result) == 0) {
                    // Create vaccination entry with confirmed status
                    $insert_vaccination = "INSERT INTO vaccinations (baby_id, vaccine_type, schedule_date, status, notes) 
                                          VALUES (?, ?, ?, 'confirmed', ?)";
                    $vacc_stmt = mysqli_prepare($conn, $insert_vaccination);
                    mysqli_stmt_bind_param($vacc_stmt, 'isss', $baby_data['id'], $vaccine_type, $schedule_datetime, $notes);
                    
                    if (mysqli_stmt_execute($vacc_stmt)) {
                        $vaccination_id = mysqli_insert_id($conn);
                        error_log("SUCCESS: Created vaccination entry (ID: $vaccination_id) from confirmed appointment (ID: $id) - Baby: $baby_name, Vaccine: $vaccine_type");
                        
                        // Auto-sync to Google Calendar for all connected users
                        require_once 'config/google_calendar_functions.php';
                        
                        // Sync to current user's calendar
                        $currentUserId = $_SESSION['user_id'];
                        $tokenCheck = mysqli_query($conn, "SELECT id FROM user_google_tokens WHERE user_id = $currentUserId");
                        if ($tokenCheck && mysqli_num_rows($tokenCheck) > 0) {
                            syncVaccinationToCalendar($conn, $vaccination_id, $currentUserId);
                        }
                        
                        // Sync to parent's calendar if they have Google Calendar connected
                        $parent_name = $baby_data['parent_guardian_name'] ?? '';
                        if (!empty($parent_name)) {
                            $parentQuery = "SELECT u.id FROM users u 
                                           INNER JOIN user_google_tokens t ON u.id = t.user_id 
                                           WHERE u.fullname = ? AND u.status = 'approved'";
                            $parentStmt = mysqli_prepare($conn, $parentQuery);
                            mysqli_stmt_bind_param($parentStmt, 's', $parent_name);
                            mysqli_stmt_execute($parentStmt);
                            $parentResult = mysqli_stmt_get_result($parentStmt);
                            
                            if ($parentData = mysqli_fetch_assoc($parentResult)) {
                                syncVaccinationToCalendar($conn, $vaccination_id, $parentData['id']);
                            }
                        }
                        
                        // Sync to all other workers/admins
                        $workersQuery = "SELECT DISTINCT u.id 
                                        FROM users u 
                                        INNER JOIN user_google_tokens t ON u.id = t.user_id 
                                        WHERE u.role IN ('worker', 'admin') 
                                        AND u.status = 'approved'
                                        AND u.id != $currentUserId";
                        $workersResult = mysqli_query($conn, $workersQuery);
                        
                        if ($workersResult) {
                            while ($worker = mysqli_fetch_assoc($workersResult)) {
                                syncVaccinationToCalendar($conn, $vaccination_id, $worker['id']);
                            }
                        }
                    } else {
                        error_log("ERROR: Failed to insert vaccination - " . mysqli_error($conn));
                    }
                } else {
                    error_log("SKIPPED: Vaccination already exists for baby {$baby_data['id']}, vaccine $vaccine_type on $schedule_datetime");
                }
            } else {
                error_log("ERROR: Could not find baby with name: '$baby_name' for vaccination creation (Appointment ID: $id)");
            }
        } else {
            error_log("INFO: Appointment $id is not a vaccination type or appointment not found");
        }
        
        // For vaccination appointments, delete from appointments table after creating vaccination entry
        if ($appointment && $appointment['appointment_type'] == 'Vaccination' && isset($vaccination_id) && $vaccination_id > 0) {
            // Send email notification BEFORE deleting
            require_once 'config/email.php';
            sendAppointmentStatusUpdateToResident($conn, $id, 'confirmed');
            error_log("Email notification sent to resident for confirmed vaccination appointment $id");
            
            // Delete appointment calendar sync records
            require_once 'config/google_calendar_functions.php';
            $sync_query = "SELECT user_id, google_event_id FROM appointment_calendar_sync WHERE appointment_id = $id";
            $sync_result = mysqli_query($conn, $sync_query);
            while ($sync_row = mysqli_fetch_assoc($sync_result)) {
                $sync_user_id = $sync_row['user_id'];
                $google_event_id = $sync_row['google_event_id'];
                $access_token = getValidAccessToken($conn, $sync_user_id);
                if ($access_token && $google_event_id) {
                    deleteCalendarEvent($access_token, $google_event_id);
                    error_log("Deleted appointment Google Calendar event $google_event_id for user $sync_user_id");
                }
            }
            
            // Delete sync records
            mysqli_query($conn, "DELETE FROM appointment_calendar_sync WHERE appointment_id = $id");
            
            // Delete the appointment from appointments table
            $delete_appointment = "DELETE FROM appointments WHERE id = $id";
            if (mysqli_query($conn, $delete_appointment)) {
                error_log("SUCCESS: Deleted vaccination appointment $id from appointments table (moved to vaccinations)");
                $message = "Vaccination appointment confirmed and moved to vaccinations schedule";
            } else {
                error_log("ERROR: Failed to delete appointment $id - " . mysqli_error($conn));
            }
            
            $update = null; // Skip the update query since we deleted the appointment
        } else {
            // For non-vaccination appointments, just update status
            $update = "UPDATE appointments SET status = 'confirmed' WHERE id = $id";
            $message = "Appointment confirmed successfully";
            
            // Send email notification to resident
            require_once 'config/email.php';
            sendAppointmentStatusUpdateToResident($conn, $id, 'confirmed');
            error_log("Email notification sent to resident for confirmed appointment $id");
        }
    } elseif ($action === 'complete') {
        // Get appointment details first
        $get_appointment = "SELECT * FROM appointments WHERE id = $id";
        $appointment_result = mysqli_query($conn, $get_appointment);
        $appointment = mysqli_fetch_assoc($appointment_result);

        if ($appointment) {
            // Archive to archived_appointments
            $archived_at = date('Y-m-d H:i:s'); // Current timestamp
            $archive_appointment = "INSERT INTO archived_appointments (original_id, user_id, fullname, appointment_type, preferred_datetime, notes, status, created_at, archived_at, archived_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?)";
            $archive_stmt = mysqli_prepare($conn, $archive_appointment);
            mysqli_stmt_bind_param(
                $archive_stmt,
                'iissssssi',
                $appointment['id'],
                $appointment['user_id'],
                $appointment['fullname'],
                $appointment['appointment_type'],
                $appointment['preferred_datetime'],
                $appointment['notes'],
                $appointment['created_at'],
                $archived_at,
                $_SESSION['user_id']
            );
            mysqli_stmt_execute($archive_stmt);

            // If it's a vaccination, also archive to archived_vaccinations
            if ($appointment['appointment_type'] == 'Vaccination') {
                // Extract baby name from notes (format: "BABY VACCINATION - Baby Name (Age: X)")
                $baby_name = '';
                if (preg_match('/BABY VACCINATION - ([^(]+)\s*\(Age:/', $appointment['notes'], $matches)) {
                    $baby_name = trim($matches[1]);
                }

                // Find the baby_id by name
                if (!empty($baby_name)) {
                    $baby_query = "SELECT id FROM babies WHERE full_name = ?";
                    $baby_stmt = mysqli_prepare($conn, $baby_query);
                    mysqli_stmt_bind_param($baby_stmt, 's', $baby_name);
                    mysqli_stmt_execute($baby_stmt);
                    $baby_result = mysqli_stmt_get_result($baby_stmt);
                    $baby_data = mysqli_fetch_assoc($baby_result);

                    if ($baby_data) {
                        // Extract vaccine type from notes (format: "Vaccine Type: VaccineType")
                        $vaccine_type = 'Unknown';
                        if (preg_match('/Vaccine Type:\s*([^\n]+)/', $appointment['notes'], $vaccine_matches)) {
                            $vaccine_type = trim($vaccine_matches[1]);
                        }

                        $schedule_date_formatted = $appointment['preferred_datetime']; // Use full datetime
                        $user_id = $_SESSION['user_id'];

                        $archive_vaccination = "INSERT INTO archived_vaccinations (original_id, baby_id, vaccine_type, schedule_date, status, notes, administered_by, administered_date, archived_by, archive_reason) 
                                              VALUES (0, ?, ?, ?, 'completed', ?, ?, NOW(), ?, 'Completed via appointment')";
                        $vacc_stmt = mysqli_prepare($conn, $archive_vaccination);
                        mysqli_stmt_bind_param(
                            $vacc_stmt,
                            'isssii',
                            $baby_data['id'],
                            $vaccine_type,
                            $schedule_date_formatted,
                            $appointment['notes'],
                            $user_id,
                            $user_id
                        );
                        mysqli_stmt_execute($vacc_stmt);
                        
                        error_log("Archived vaccination for baby: $baby_name (ID: {$baby_data['id']}), Vaccine: $vaccine_type");
                    } else {
                        error_log("Could not find baby with name: $baby_name for vaccination archiving");
                    }
                } else {
                    error_log("Could not extract baby name from appointment notes for vaccination archiving");
                }
            }

            // Send email notification to resident BEFORE deleting
            require_once 'config/email.php';
            sendAppointmentStatusUpdateToResident($conn, $id, 'completed');
            error_log("Email notification sent to resident for completed appointment $id");
            
            // Delete from Google Calendar for all users who synced this appointment
            require_once 'config/google_calendar_functions.php';
            $sync_query = "SELECT user_id, google_event_id FROM appointment_calendar_sync WHERE appointment_id = $id";
            $sync_result = mysqli_query($conn, $sync_query);
            while ($sync_row = mysqli_fetch_assoc($sync_result)) {
                $sync_user_id = $sync_row['user_id'];
                $google_event_id = $sync_row['google_event_id'];
                $access_token = getValidAccessToken($conn, $sync_user_id);
                if ($access_token && $google_event_id) {
                    deleteCalendarEvent($access_token, $google_event_id);
                    error_log("Deleted Google Calendar event $google_event_id for user $sync_user_id (appointment completed)");
                }
            }
            
            // Delete sync records
            $delete_sync = "DELETE FROM appointment_calendar_sync WHERE appointment_id = $id";
            mysqli_query($conn, $delete_sync);
            
            // Delete from main appointments table
            $delete_query = "DELETE FROM appointments WHERE id = $id";
            mysqli_query($conn, $delete_query);

            $update = null;
            $message = "Appointment completed and archived successfully";
        }
    } else {
        $update = null;
    }

    if ($update && mysqli_query($conn, $update)) {
        header("Location: " . $redirect_base . "success=" . urlencode($message));
    } elseif ($update === null && strpos($message, "Error") === false) {
        // Archive operation was successful
        header("Location: " . $redirect_base . "success=" . urlencode($message));
    } else {
        header("Location: " . $redirect_base . "error=" . urlencode($message ?: "Failed to update appointment"));
    }
    exit;
}

// Filter and search - auto-archiving disabled

$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build WHERE clause for filtering
$where_clauses = [];
if ($filter == 'today') {
    $where_clauses[] = "DATE(a.preferred_datetime) = CURDATE()";
} elseif ($filter == 'pending') {
    $where_clauses[] = "a.status = 'pending'";
} elseif ($filter == 'confirmed') {
    $where_clauses[] = "a.status = 'confirmed'";
} elseif ($filter == 'completed') {
    $where_clauses[] = "a.status = 'completed'";
} elseif ($filter == 'cancelled') {
    $where_clauses[] = "a.status = 'cancelled'";
} elseif ($filter == 'upcoming') {
    $where_clauses[] = "DATE(a.preferred_datetime) >= CURDATE() AND a.status IN ('pending', 'confirmed')";
} elseif ($filter == '' || $filter == 'active') {
    // Default: show only active appointments (pending and confirmed)
    $where_clauses[] = "a.status IN ('pending', 'confirmed')";
}

if (!empty($search)) {
    $where_clauses[] = "(a.fullname LIKE '%$search%' OR a.appointment_type LIKE '%$search%' OR a.notes LIKE '%$search%')";
}

$where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Auto-archiving disabled

// Get appointments with priority ordering (current/today first, then by status and date)
$query = "SELECT a.*, u.username FROM appointments a 
          LEFT JOIN users u ON a.user_id = u.id 
          $where_clause
          ORDER BY a.preferred_datetime ASC";
$result = mysqli_query($conn, $query);

// Debug: Check if query failed
if (!$result) {
    die("Query failed: " . mysqli_error($conn) . "<br>Query: " . $query);
}

// Get appointment statistics
$stats_query = "SELECT 
                COUNT(CASE WHEN a.status IN ('pending', 'confirmed') THEN 1 END) as total_appointments,
                COUNT(CASE WHEN a.status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN a.status = 'confirmed' THEN 1 END) as confirmed,
                COUNT(CASE WHEN DATE(a.preferred_datetime) = CURDATE() AND a.status IN ('pending', 'confirmed') THEN 1 END) as today,
                (SELECT COUNT(*) FROM archived_appointments WHERE status = 'completed') as completed
                FROM appointments a";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Management</title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Navbar Styles -->
    <link rel="stylesheet" href="assets/css/navbar.css">
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/residents_dashboard.css">
    <!-- Appointments Styles -->
    <link rel="stylesheet" href="assets/css/appointments.css">
    <!-- Toast Notifications Styles -->
    <link rel="stylesheet" href="assets/css/success-error_messages.css">
    <!-- Responsive Styles -->
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?php echo time(); ?>">


</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="welcome-section mb-5">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">
                        <i class="fas fa-calendar-check me-2"></i>
                        Appointments Management
                    </h1>
                    <p class="welcome-subtitle">Manage and track all health appointments, monitor schedules, and ensure
                        quality healthcare delivery.</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="welcome-date">
                        <i class="fas fa-calendar-day me-2"></i>
                        <?php
                        date_default_timezone_set('Asia/Manila');
                        echo date('l, F j, Y');
                        ?>
                        <br>
                        <i class="fas fa-clock me-2"></i>
                        <?php echo date('g:i A'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message Modal -->
        <div class="message-modal" id="messageModal">
            <div class="message-modal-content">
                <div class="message-modal-header">
                    <button type="button" class="message-modal-close" onclick="hideModal()">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="message-modal-icon" id="modalIcon">
                        <i class="fas fa-check" id="modalIconSymbol"></i>
                    </div>
                    <h3 class="message-modal-title" id="modalTitle">Success</h3>
                </div>
                <div class="message-modal-body">
                    <p class="message-modal-message" id="modalMessage">Operation completed successfully!</p>
                    <div class="message-modal-actions">
                        <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="hideModal()">
                            <i class="fas fa-check me-2"></i>OK
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancellation Reason Modal -->
        <div class="modal fade" id="cancelReasonModal" tabindex="-1" aria-labelledby="cancelReasonModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="appointments.php" id="cancelReasonForm">
                        <div class="modal-header">
                            <h5 class="modal-title" id="cancelReasonModalLabel">
                                <i class="fas fa-times-circle me-2"></i>Cancel Appointment
                            </h5>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="appointment_id" id="cancelAppointmentId">
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            
                            <div class="mb-3">
                                <label for="cancellationReason" class="form-label fw-semibold">
                                    <i class="fas fa-comment-alt me-1"></i>Reason for Cancellation <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" id="cancellationReason" name="cancellation_reason" rows="4" 
                                    placeholder="Please provide a reason for cancelling this appointment..." required></textarea>
                                <small class="text-muted">This reason will be saved in the archive and may be visible to the patient.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Close
                            </button>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times-circle me-1"></i> Cancel Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <section class="statistics-section mb-4">
            <h2 class="section-title">
                <i class="fas fa-chart-line me-2"></i>Appointments Statistics Overview
            </h2>
            <div class="row">
                <div class="col-lg-3 col-md-6 col-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h3 class="stats-number"><?php echo $stats['total_appointments']; ?></h3>
                            <p class="stats-label">Total Appointments</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-icon">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <h3 class="stats-number"><?php echo $stats['today']; ?></h3>
                            <p class="stats-label">Today's Appointments</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="stats-number"><?php echo $stats['pending']; ?></h3>
                            <p class="stats-label">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="stats-number"><?php echo $stats['completed'] ?? 0; ?></h3>
                            <p class="stats-label">Completed</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Appointments List -->
        <section class="appointments-list">
            <div class="card table-card">
                <div class="card-header d-flex flex-nowrap justify-content-start align-items-center">
                    <div class="d-flex flex-nowrap appointments-header-buttons">
                        <button type="button" class="btn btn-secondary btn-sm appointments-btn" onclick="window.history.back()" title="Go Back">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </button>
                        <?php if (isAdmin() || isWorker()): ?>
                            <a href="archives.php?type=appointments" class="btn btn-warning btn-sm appointments-btn" title="View Archives">
                                <i class="fas fa-archive me-1"></i> Archives
                            </a>
                        <?php endif; ?>
                        <a href="vaccinations.php" class="btn btn-success btn-sm appointments-btn" title="Manage Vaccines">
                            <i class="fas fa-syringe me-1"></i> Vaccines
                        </a>
                    </div>
                </div>

                <!-- Filters inside table card -->
                <div class="card-body border-bottom">
                    <form action="appointments.php" method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label fw-semibold">
                                <i class="fas fa-search me-1"></i>Search Appointments
                            </label>
                            <input type="text" class="form-control" id="search" name="search"
                                placeholder="Name, type, or notes..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label for="filter" class="form-label fw-semibold">
                                <i class="fas fa-filter me-1"></i>Filter by Status
                            </label>
                            <select class="form-select" id="filter" name="filter">
                                <option value="" <?php echo $filter == '' ? 'selected' : ''; ?>>All Appointments</option>
                                <option value="today" <?php echo $filter == 'today' ? 'selected' : ''; ?>>Today's
                                    Appointments</option>
                                <option value="upcoming" <?php echo $filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming
                                </option>
                                <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending
                                </option>
                                <option value="confirmed" <?php echo $filter == 'confirmed' ? 'selected' : ''; ?>>
                                    Confirmed</option>

                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-center" style="padding-top: 2rem;">
                            <a href="appointments.php" class="btn btn-outline-secondary btn-sm w-100" title="Reset Filters">
                                <i class="fas fa-redo"></i><span class="d-none d-sm-inline ms-1">Reset</span>
                            </a>
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user me-1"></i>Patient Name</th>
                                    <th><i class="fas fa-medical-kit me-1"></i>Type</th>
                                    <th><i class="fas fa-calendar me-1"></i>Date & Time</th>
                                    <th><i class="fas fa-sticky-note me-1"></i>Notes</th>
                                    <th><i class="fas fa-flag me-1"></i>Status</th>
                                    <th class="text-center"><i class="fas fa-cogs me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $statusClass = '';

                                        switch ($row['status']) {
                                            case 'confirmed':
                                                $statusClass = 'bg-success';
                                                break;
                                            case 'completed':
                                                $statusClass = 'bg-info';
                                                break;
                                            case 'cancelled':
                                                $statusClass = 'bg-danger';
                                                break;
                                            default:
                                                $statusClass = 'bg-warning text-dark';
                                        }

                                        echo "<tr class='appointment-row'>";
                                        // Standardize patient name display
                                        $patientName = $row['fullname'];
                                        $patientSubtext = '';

                                        // For vaccination appointments, try to extract baby name from notes
                                        if ($row['appointment_type'] === 'Vaccination' && !empty($row['notes'])) {
                                            // Check if baby name is in notes (format: "BABY VACCINATION - Baby Name (Age: X)")
                                            if (preg_match('/BABY VACCINATION - ([^(]+)\s*\(Age:/', $row['notes'], $matches)) {
                                                $patientName = trim($matches[1]);
                                            } 
                                            // Check for new baby vaccination
                                            elseif (strpos($row['notes'], 'NEW BABY VACCINATION') !== false) {
                                                // For new baby vaccinations, show parent name with subtext
                                                $patientSubtext = '<br><small class="text-muted">(Parent/Guardian)</small>';
                                            }
                                        }

                                        echo "<td><strong>" . htmlspecialchars($patientName) . "</strong>" . $patientSubtext . "</td>";
                                        echo "<td>" . htmlspecialchars($row['appointment_type']) . "</td>";
                                        echo "<td>" . date('M d, Y g:i A', strtotime($row['preferred_datetime'])) . "</td>";
                                        // Clean up notes display for vaccination appointments
                                        $displayNotes = $row['notes'];
                                        if ($row['appointment_type'] === 'Vaccination' && !empty($displayNotes)) {
                                            // Remove redundant baby name and system information from notes
                                            if (strpos($displayNotes, 'BABY VACCINATION - ') !== false) {
                                                // Remove "BABY VACCINATION - Baby Name (Age: X)" part
                                                $displayNotes = preg_replace('/BABY VACCINATION - [^)]+\)\s*/', '', $displayNotes);
                                                // Clean up any remaining "Additional Notes:" prefix
                                                $displayNotes = str_replace('Additional Notes: ', '', $displayNotes);
                                                $displayNotes = trim($displayNotes);
                                            } elseif (strpos($displayNotes, 'Vaccination: ') !== false) {
                                                // Keep vaccination type info but make it cleaner
                                                $displayNotes = str_replace('Vaccination: ', '', $displayNotes);
                                            }
                                            
                                            $displayNotes = trim($displayNotes);
                                        }
                                        echo "<td>" . (empty($displayNotes) ? '<em class="text-muted">No additional notes</em>' : htmlspecialchars($displayNotes)) . "</td>";
                                        echo "<td><span class='badge $statusClass'>" . ucfirst($row['status']) . "</span></td>";
                                        // Check if user can take actions (admin or own appointment)
                                        $canTakeAction = isAdmin() || (isset($_SESSION['user_id']) && $row['user_id'] == $_SESSION['user_id']);

                                        // TEMPORARY: Force all users to have action permissions for testing
                                        $canTakeAction = true;

                                        echo "<td>
                                                <div class='appointment-actions-container'>";

                                        // CONFIRM BUTTON (Accept)
                                        if ($row['status'] == 'pending' && $canTakeAction) {
                                            echo "<a href='appointments.php?action=confirm&id={$row['id']}' class='appointment-btn-confirm' title='Accept Appointment' onclick='return confirm(\"Confirm this appointment?\")' style='width: 28px; height: 28px; padding: 0; margin: 0 1px;'>
                                                    <i class='fas fa-check' style='font-size: 12px;'></i>
                                                  </a>";
                                        } else {
                                            echo "<button class='appointment-btn-confirm disabled' title='Cannot Accept' disabled style='width: 28px; height: 28px; padding: 0; margin: 0 1px;'>
                                                    <i class='fas fa-check' style='font-size: 12px;'></i>
                                                  </button>";
                                        }

                                        // COMPLETE BUTTON (Mark as Done)
                                        if (($row['status'] == 'pending' || $row['status'] == 'confirmed') && $canTakeAction) {
                                            echo "<a href='appointments.php?action=complete&id={$row['id']}' class='appointment-btn-complete' title='Mark as Done' onclick='return confirm(\"Mark this appointment as completed?\")' style='width: 28px; height: 28px; padding: 0; margin: 0 1px;'>
                                                    <i class='fas fa-check-circle' style='font-size: 12px;'></i>
                                                  </a>";
                                        } else {
                                            echo "<button class='appointment-btn-complete disabled' title='Cannot Complete' disabled style='width: 28px; height: 28px; padding: 0; margin: 0 1px;'>
                                                    <i class='fas fa-check-circle' style='font-size: 12px;'></i>
                                                  </button>";
                                        }

                                        // CANCEL BUTTON
                                        if (($row['status'] == 'pending' || $row['status'] == 'confirmed') && $canTakeAction) {
                                            echo "<button type='button' class='appointment-btn-cancel' title='Cancel Appointment' onclick='openCancelModal({$row['id']})' style='width: 28px; height: 28px; padding: 0; margin: 0 1px; border: none; cursor: pointer;'>
                                                    <i class='fas fa-times' style='font-size: 12px;'></i>
                                                  </button>";
                                        } else {
                                            echo "<button class='appointment-btn-cancel disabled' title='Cannot Cancel' disabled style='width: 28px; height: 28px; padding: 0; margin: 0 1px;'>
                                                    <i class='fas fa-times' style='font-size: 12px;'></i>
                                                  </button>";
                                        }

                                        echo "</div></td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center py-4'>
                                            <div class='text-muted'>
                                                <i class='fas fa-calendar-times fa-3x mb-3 d-block'></i>
                                                <h5>No appointments found</h5>
                                                <p>Try adjusting your search criteria.</p>
                                            </div>
                                          </td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- Bottom spacing for better UX -->
    <div style="height: 60px;"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Modal Notifications Script -->
    <script>
        // Open cancellation reason modal
        function openCancelModal(appointmentId) {
            document.getElementById('cancelAppointmentId').value = appointmentId;
            document.getElementById('cancellationReason').value = '';
            const modal = new bootstrap.Modal(document.getElementById('cancelReasonModal'));
            modal.show();
        }

        // Modal notification function
        function showModal(message, type = 'success') {
            const modal = document.getElementById('messageModal');
            const modalIcon = document.getElementById('modalIcon');
            const modalIconSymbol = document.getElementById('modalIconSymbol');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');

            // Reset classes
            modal.className = 'message-modal';
            modalIcon.className = 'message-modal-icon';

            // Set content based on type
            if (type === 'success') {
                modal.classList.add('message-modal-success');
                modalIconSymbol.className = 'fas fa-check';
                modalTitle.textContent = 'Success';
            } else {
                modal.classList.add('message-modal-error');
                modalIconSymbol.className = 'fas fa-exclamation-triangle';
                modalTitle.textContent = 'Error';
            }

            modalMessage.textContent = message;

            // Show modal
            modal.classList.add('show');

            // Auto-hide after 5 seconds
            setTimeout(() => {
                hideModal();
            }, 5000);
        }

        // Hide modal function
        function hideModal() {
            const modal = document.getElementById('messageModal');
            modal.classList.add('hiding');

            setTimeout(() => {
                modal.classList.remove('show', 'hiding');
            }, 300);
        }

        // Close modal when clicking outside
        document.addEventListener('click', function (e) {
            const modal = document.getElementById('messageModal');
            if (e.target === modal) {
                hideModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hideModal();
            }
        });

        // Show modal on page load if there are messages
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (isset($_GET['success'])): ?>
                showModal('<?php echo addslashes(htmlspecialchars($_GET['success'])); ?>', 'success');
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                showModal('<?php echo addslashes(htmlspecialchars($_GET['error'])); ?>', 'error');
            <?php endif; ?>
            
            // Auto-submit for search input (with debounce)
            const searchInput = document.getElementById('search');
            let searchTimeout;
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        searchInput.form.submit();
                    }, 500); // Wait 500ms after user stops typing
                });
            }

            // Auto-submit for filter dropdown
            const filterSelect = document.getElementById('filter');
            if (filterSelect) {
                filterSelect.addEventListener('change', function() {
                    this.form.submit();
                });
            }

            // Force page reload when navigating back from another page
            window.addEventListener('pageshow', function(event) {
                if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                    // Page was loaded from cache (back button), force reload
                    window.location.reload();
                }
            });
        });
    </script>
</body>

</html>