<?php
// Enable error reporting for debugging
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
require_once 'config/archive_functions.php';
require_once 'config/email.php';
requireApproved();

// Auto-migration: Change schedule_date from DATE to DATETIME if needed
$check_column_type = mysqli_query($conn, "SHOW COLUMNS FROM vaccinations LIKE 'schedule_date'");
if ($check_column_type && mysqli_num_rows($check_column_type) > 0) {
    $column_info = mysqli_fetch_assoc($check_column_type);
    if (strtolower($column_info['Type']) === 'date') {
        // Column is DATE, need to change to DATETIME
        mysqli_query($conn, "ALTER TABLE vaccinations MODIFY COLUMN schedule_date DATETIME NOT NULL");
        mysqli_query($conn, "ALTER TABLE archived_vaccinations MODIFY COLUMN schedule_date DATETIME NOT NULL");
        error_log("Migrated schedule_date column from DATE to DATETIME");
    }
}

// Handle vaccination form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'schedule_vaccination') {
    
    $baby_id = (int)($_POST['baby_id'] ?? 0);
    $vaccine_type = trim($_POST['vaccine_type'] ?? '');
    $schedule_date = trim($_POST['schedule_date'] ?? '');
    $schedule_time = trim($_POST['schedule_time'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($baby_id <= 0 || empty($vaccine_type) || empty($schedule_date) || empty($schedule_time)) {
        header("Location: vaccinations.php?error=" . urlencode("Please fill in all required fields"));
        exit;
    }

    $vaccine_type = sanitize($vaccine_type);
    // Combine date and time into datetime format
    $schedule_datetime = date('Y-m-d H:i:s', strtotime($schedule_date . ' ' . $schedule_time));
    $notes = sanitize($notes);

    try {
        // Check for duplicate vaccination (same baby, vaccine type, and date)
        $check_duplicate = "SELECT id FROM vaccinations 
                           WHERE baby_id = ? 
                           AND vaccine_type = ? 
                           AND DATE(schedule_date) = DATE(?)";
        $check_stmt = mysqli_prepare($conn, $check_duplicate);
        mysqli_stmt_bind_param($check_stmt, 'iss', $baby_id, $vaccine_type, $schedule_datetime);
        mysqli_stmt_execute($check_stmt);
        $duplicate_result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($duplicate_result) > 0) {
            header("Location: vaccinations.php?error=" . urlencode("This vaccination is already scheduled for this baby on this date"));
            exit;
        }
        
        // Workers and admins automatically confirm vaccinations they schedule
        $initial_status = (isAdmin() || isWorker()) ? 'confirmed' : 'pending';
        
        $query = "INSERT INTO vaccinations (baby_id, vaccine_type, schedule_date, status, notes) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'issss', $baby_id, $vaccine_type, $schedule_datetime, $initial_status, $notes);
        
        if (mysqli_stmt_execute($stmt)) {
            $vaccination_id = mysqli_insert_id($conn);
            
            $baby_query = "SELECT full_name, parent_guardian_name FROM babies WHERE id = ?";
            $baby_stmt = mysqli_prepare($conn, $baby_query);
            mysqli_stmt_bind_param($baby_stmt, 'i', $baby_id);
            mysqli_stmt_execute($baby_stmt);
            $baby_result = mysqli_stmt_get_result($baby_stmt);
            $baby_data = mysqli_fetch_assoc($baby_result);
            $baby_name = $baby_data['full_name'] ?? 'Unknown';
            $parent_name = $baby_data['parent_guardian_name'] ?? '';
            
            // Auto-sync to Google Calendar for all connected users
            require_once 'config/google_calendar_functions.php';
            
            // 1. Sync to current user's calendar (worker/admin who scheduled it)
            $currentUserId = $_SESSION['user_id'];
            $tokenCheck = mysqli_query($conn, "SELECT id FROM user_google_tokens WHERE user_id = $currentUserId");
            if ($tokenCheck && mysqli_num_rows($tokenCheck) > 0) {
                syncVaccinationToCalendar($conn, $vaccination_id, $currentUserId);
                error_log("Auto-synced new vaccination $vaccination_id to scheduler's calendar (user $currentUserId)");
            }
            
            // 2. Sync to parent's calendar if they have Google Calendar connected
            if (!empty($parent_name)) {
                $parentQuery = "SELECT u.id FROM users u 
                               INNER JOIN user_google_tokens t ON u.id = t.user_id 
                               WHERE u.fullname = ? AND u.status = 'approved'";
                $parentStmt = mysqli_prepare($conn, $parentQuery);
                mysqli_stmt_bind_param($parentStmt, 's', $parent_name);
                mysqli_stmt_execute($parentStmt);
                $parentResult = mysqli_stmt_get_result($parentStmt);
                
                if ($parentData = mysqli_fetch_assoc($parentResult)) {
                    $parentId = $parentData['id'];
                    syncVaccinationToCalendar($conn, $vaccination_id, $parentId);
                    error_log("Auto-synced new vaccination $vaccination_id to parent's calendar (user $parentId)");
                }
            }
            
            // 3. Sync to all other workers/admins who have Google Calendar connected
            $workersQuery = "SELECT DISTINCT u.id 
                            FROM users u 
                            INNER JOIN user_google_tokens t ON u.id = t.user_id 
                            WHERE u.role IN ('worker', 'admin') 
                            AND u.status = 'approved'
                            AND u.id != $currentUserId";
            $workersResult = mysqli_query($conn, $workersQuery);
            
            if ($workersResult) {
                while ($worker = mysqli_fetch_assoc($workersResult)) {
                    $workerId = $worker['id'];
                    syncVaccinationToCalendar($conn, $vaccination_id, $workerId);
                    error_log("Auto-synced new vaccination $vaccination_id to worker/admin calendar (user $workerId)");
                }
            }
            
            // Send email notification to parent/guardian if they are a registered user
            if (!empty($parent_name) && (isAdmin() || isWorker())) {
                // Find the user by matching fullname with parent_guardian_name
                $user_query = "SELECT id, email, fullname FROM users 
                              WHERE fullname = ? AND status = 'approved' 
                              AND email IS NOT NULL AND email != ''";
                $user_stmt = mysqli_prepare($conn, $user_query);
                mysqli_stmt_bind_param($user_stmt, 's', $parent_name);
                mysqli_stmt_execute($user_stmt);
                $user_result = mysqli_stmt_get_result($user_stmt);
                
                if ($user_data = mysqli_fetch_assoc($user_result)) {
                    // Parent is a registered user, send notification
                    $vaccination_data = [
                        'baby_name' => $baby_name,
                        'vaccine_type' => $vaccine_type,
                        'schedule_datetime' => $schedule_datetime,
                        'notes' => $notes,
                        'status' => $initial_status
                    ];
                    
                    sendVaccinationScheduleNotification(
                        $user_data['email'],
                        $user_data['fullname'],
                        $vaccination_data
                    );
                    
                    error_log("Vaccination schedule notification sent to parent: {$user_data['email']}");
                }
            }
            
            header("Location: vaccinations.php?show_success_modal=1&baby_name=" . urlencode($baby_name));
        } else {
            if (strpos(mysqli_error($conn), 'Duplicate entry') !== false) {
                header("Location: vaccinations.php?error=" . urlencode("This vaccination already exists"));
            } else {
                header("Location: vaccinations.php?error=" . urlencode("Failed to schedule vaccination"));
            }
        }
    } catch (Exception $e) {
        header("Location: vaccinations.php?error=" . urlencode("Error: " . $e->getMessage()));
    }
    exit;
}

// Archive vaccination record
if (isset($_GET['delete_id'])) {
    requireAdmin();

    $id = (int) $_GET['delete_id'];

    // Store current filter and search parameters
    $current_filter = isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : '';
    $current_search = isset($_GET['search']) ? (!empty($current_filter) ? '&' : '?') . 'search=' . urlencode($_GET['search']) : '';

    // Add success/error parameter with proper ? or & prefix
    $redirect_base = "vaccinations.php" . $current_filter . $current_search;
    $redirect_base .= (strpos($redirect_base, '?') === false) ? '?' : '&';

    try {
        archiveVaccination($id, $_SESSION['user_id']);
        header("Location: " . $redirect_base . "show_archive_modal=1");
        exit;
    } catch (Exception $e) {
        header("Location: " . $redirect_base . "error=" . urlencode($e->getMessage()));
        exit;
    }
}
require_once 'config/session.php';
require_once 'config/functions.php';
require_once 'config/archive_functions.php';
requireApproved();

// Auto-archive cancelled appointments older than 30 days
try {
    archiveOldCancelledAppointments($_SESSION['user_id'] ?? 1);
} catch (Exception $e) {
    // Log error but don't stop page loading
    error_log("Auto-archive error: " . $e->getMessage());
}

// Handle vaccination actions
if (isset($_GET['action']) && isset($_GET['vaccination_id'])) {
    $action = $_GET['action'];
    $vaccination_id = intval($_GET['vaccination_id']);

    // Store current filter and search parameters for redirect
    $current_filter = isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : '';
    $current_search = isset($_GET['search']) ? (!empty($current_filter) ? '&' : '?') . 'search=' . urlencode($_GET['search']) : '';
    $redirect_base = "vaccinations.php" . $current_filter . $current_search;
    $redirect_base .= (strpos($redirect_base, '?') === false) ? '?' : '&';

    if ($action === 'confirm' && $vaccination_id > 0) {
        // First check current status
        $check_query = "SELECT id, status FROM vaccinations WHERE id = $vaccination_id";
        $check_result = mysqli_query($conn, $check_query);
        $current_record = mysqli_fetch_assoc($check_result);
        
        if (!$current_record) {
            header("Location: " . $redirect_base . "error=" . urlencode("Vaccination record not found (ID: $vaccination_id)"));
            exit;
        }
        
        // Update the vaccination record to confirmed
        $vaccination_update = "UPDATE vaccinations SET status = 'confirmed' WHERE id = $vaccination_id";
        $result = mysqli_query($conn, $vaccination_update);
        
        // Log for debugging
        error_log("Confirm action - ID: $vaccination_id, Old status: {$current_record['status']}, Query: " . ($result ? 'success' : 'failed') . ", Rows: " . mysqli_affected_rows($conn));
        
        if ($result) {
            // Update Google Calendar events for all synced users
            require_once 'config/google_calendar_functions.php';
            $syncQuery = "SELECT user_id FROM vaccination_calendar_sync WHERE vaccination_id = $vaccination_id";
            $syncResult = mysqli_query($conn, $syncQuery);
            if ($syncResult) {
                while ($syncRow = mysqli_fetch_assoc($syncResult)) {
                    syncVaccinationToCalendar($conn, $vaccination_id, $syncRow['user_id']);
                    error_log("Updated Google Calendar event for vaccination $vaccination_id (user {$syncRow['user_id']})");
                }
            }
            
            // Always redirect to show modal, even if no rows affected (already confirmed)
            header("Location: " . $redirect_base . "show_confirm_modal=1");
            exit;
        } else {
            $error_msg = mysqli_error($conn);
            header("Location: " . $redirect_base . "error=" . urlencode("Database error: " . $error_msg));
            exit;
        }
    } elseif ($action === 'complete' && $vaccination_id > 0) {
        // Get vaccination details first
        $get_vaccination = "SELECT * FROM vaccinations WHERE id = $vaccination_id";
        $vaccination_result = mysqli_query($conn, $get_vaccination);
        $vaccination = mysqli_fetch_assoc($vaccination_result);

        if ($vaccination) {
            $user_id = $_SESSION['user_id'];
            
            // Delete from Google Calendar for all synced users
            require_once 'config/google_calendar_functions.php';
            deleteVaccinationFromCalendar($conn, $vaccination_id);
            
            // Archive to archived_vaccinations
            $archive_vaccination = "INSERT INTO archived_vaccinations (original_id, baby_id, vaccine_type, schedule_date, status, notes, administered_by, administered_date, archived_by, archive_reason) 
                                   VALUES (?, ?, ?, ?, 'completed', ?, ?, NOW(), ?, 'Completed vaccination')";
            $archive_stmt = mysqli_prepare($conn, $archive_vaccination);
            mysqli_stmt_bind_param(
                $archive_stmt,
                'iissiii',
                $vaccination['id'],
                $vaccination['baby_id'],
                $vaccination['vaccine_type'],
                $vaccination['schedule_date'],
                $vaccination['notes'],
                $user_id,
                $user_id
            );
            mysqli_stmt_execute($archive_stmt);

            // Delete from main vaccinations table
            $delete_query = "DELETE FROM vaccinations WHERE id = $vaccination_id";
            mysqli_query($conn, $delete_query);

            header("Location: " . $redirect_base . "show_complete_modal=1");
            exit;
        } else {
            header("Location: " . $redirect_base . "error=" . urlencode("Vaccination record not found"));
            exit;
        }
    } elseif ($action === 'cancel' && $vaccination_id > 0) {
        // Get vaccination details first
        $get_vaccination = "SELECT * FROM vaccinations WHERE id = $vaccination_id";
        $vaccination_result = mysqli_query($conn, $get_vaccination);
        $vaccination = mysqli_fetch_assoc($vaccination_result);

        if ($vaccination) {
            $user_id = $_SESSION['user_id'];
            
            // Delete from Google Calendar for all synced users
            require_once 'config/google_calendar_functions.php';
            deleteVaccinationFromCalendar($conn, $vaccination_id);
            
            // Archive to archived_vaccinations
            $archive_vaccination = "INSERT INTO archived_vaccinations (original_id, baby_id, vaccine_type, schedule_date, status, notes, administered_by, administered_date, archived_by, archive_reason) 
                                   VALUES (?, ?, ?, ?, 'cancelled', ?, NULL, NULL, ?, 'Cancelled vaccination')";
            $archive_stmt = mysqli_prepare($conn, $archive_vaccination);
            mysqli_stmt_bind_param(
                $archive_stmt,
                'iisssi',
                $vaccination['id'],
                $vaccination['baby_id'],
                $vaccination['vaccine_type'],
                $vaccination['schedule_date'],
                $vaccination['notes'],
                $user_id
            );
            mysqli_stmt_execute($archive_stmt);

            // Delete from main vaccinations table
            $delete_query = "DELETE FROM vaccinations WHERE id = $vaccination_id";
            mysqli_query($conn, $delete_query);

            header("Location: " . $redirect_base . "show_cancel_modal=1");
            exit;
        } else {
            header("Location: " . $redirect_base . "error=" . urlencode("Vaccination record not found"));
            exit;
        }
    }
    
    // If we reach here, something went wrong
    header("Location: " . $redirect_base . "error=" . urlencode("Invalid action or vaccination ID"));
    exit;
}

// Auto-archiving happens only when actions are taken

// Filter and search
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build query
$where_clauses = array();

// Filter by status - only show active vaccinations (pending and confirmed)
if ($filter == 'pending') {
    $where_clauses[] = "v.status = 'pending'";
} elseif ($filter == 'confirmed') {
    $where_clauses[] = "v.status = 'confirmed'";
} elseif ($filter == 'today') {
    $where_clauses[] = "v.schedule_date = CURDATE()";
} elseif ($filter == 'upcoming') {
    $where_clauses[] = "v.schedule_date >= CURDATE() AND v.schedule_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
} elseif ($filter == 'overdue') {
    $where_clauses[] = "v.schedule_date < CURDATE() AND v.status IN ('pending', 'confirmed')";
}
// Default: show all active vaccinations (pending and confirmed only)

if (!empty($search)) {
    $where_clauses[] = "(b.full_name LIKE '%$search%' OR b.parent_guardian_name LIKE '%$search%' OR v.vaccine_type LIKE '%$search%')";
}

$where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get vaccinations with baby info - NO APPOINTMENTS JOIN TO PREVENT DUPLICATES
$query = "SELECT v.*, b.full_name, b.date_of_birth, b.parent_guardian_name
          FROM vaccinations v 
          JOIN babies b ON v.baby_id = b.id
          $where_clause
          ORDER BY v.schedule_date ASC";
$result = mysqli_query($conn, $query);

// Get vaccination statistics
$stats_query = "SELECT 
                COUNT(*) as total_vaccinations,
                (SELECT COUNT(*) FROM archived_vaccinations WHERE status = 'completed') as completed,
                COUNT(CASE WHEN v.schedule_date >= CURDATE() THEN 1 END) as upcoming,
                COUNT(CASE WHEN v.schedule_date < CURDATE() THEN 1 END) as overdue
                FROM vaccinations v";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Schedule Management</title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Navbar Styles -->
    <link rel="stylesheet" href="assets/css/navbar.css">
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/residents_dashboard.css">
    <link rel="stylesheet" href="assets/css/vaccinations.css?v=<?php echo time(); ?>">
    <!-- Success/Error Messages Styles -->
    <link rel="stylesheet" href="assets/css/success-error_messages.css">
    
    <style>
        /* Style for booked and past time slots */
        #schedule_time option:disabled {
            color: #dc3545 !important;
            background-color: #f8d7da !important;
            font-weight: 600;
        }
    </style>
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
                        <i class="fas fa-syringe me-2"></i>
                        Vaccination Schedule Management
                    </h1>
                    <p class="welcome-subtitle">Monitor and manage vaccination schedules, track immunization records,
                        and ensure timely healthcare delivery.</p>
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

        <!-- Statistics Overview -->
        <section class="statistics-section mb-4">
            <h2 class="section-title">
                <i class="fas fa-chart-line me-2"></i>Vaccination Statistics Overview
            </h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-icon">
                                <i class="fas fa-syringe"></i>
                            </div>
                            <h3 class="stats-number"><?php echo $stats['total_vaccinations']; ?></h3>
                            <p class="stats-label">Total Vaccinations</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="stats-number"><?php echo $stats['completed']; ?></h3>
                            <p class="stats-label">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="stats-number"><?php echo $stats['upcoming']; ?></h3>
                            <p class="stats-label">Upcoming</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h3 class="stats-number"><?php echo $stats['overdue']; ?></h3>
                            <p class="stats-label">Overdue</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Vaccination Schedule List -->
        <section class="vaccination-list">
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-nowrap justify-content-end align-items-center">
                    <div class="d-flex flex-nowrap vaccinations-header-buttons">
                        <button type="button" class="btn btn-secondary btn-sm vaccinations-btn" onclick="window.history.back()" title="Go Back">
                            <i class="fas fa-arrow-left"></i><span class="d-none d-lg-inline ms-1">Back</span>
                        </button>
                        <a href="archives.php?type=vaccinations" class="btn btn-warning btn-sm vaccinations-btn" title="View Archives">
                            <i class="fas fa-archive"></i><span class="d-none d-lg-inline ms-1">Archives</span>
                        </a>
                        <button type="button" class="btn btn-primary btn-sm vaccinations-btn" data-bs-toggle="modal"
                            data-bs-target="#addVaccinationModal" title="Schedule New Vaccination">
                            <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-1">Schedule New</span>
                        </button>
                    </div>
                </div>

                <!-- Filters inside table card -->
                <div class="card-body border-bottom">
                    <form action="vaccinations.php" method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label fw-semibold">
                                <i class="fas fa-search me-1"></i>Search Vaccinations
                            </label>
                            <input type="text" class="form-control" id="search" name="search"
                                placeholder="Baby name, parent, or vaccine type..."
                                value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filter" class="form-label fw-semibold">
                                <i class="fas fa-filter me-1"></i>Filter by Status
                            </label>
                            <select class="form-select" id="filter" name="filter">
                                <option value="" <?php echo $filter == '' ? 'selected' : ''; ?>>All Vaccinations</option>
                                <option value="today" <?php echo $filter == 'today' ? 'selected' : ''; ?>>Today's
                                    Vaccinations</option>
                                <option value="upcoming" <?php echo $filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming
                                    (7 days)</option>
                                <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending
                                </option>
                                <option value="confirmed" <?php echo $filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed
                                </option>
                                <option value="overdue" <?php echo $filter == 'overdue' ? 'selected' : ''; ?>>Overdue
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-center" style="padding-top: 2rem;">
                            <a href="vaccinations.php" class="btn btn-outline-secondary btn-sm w-100" title="Reset Filters">
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
                                    <th><i class="fas fa-baby me-1"></i>Patient Name</th>
                                    <th><i class="fas fa-vial me-1"></i>Vaccine Type</th>
                                    <th><i class="fas fa-calendar me-1"></i>Date & Time</th>
                                    <th><i class="fas fa-sticky-note me-1"></i>Notes</th>
                                    <th><i class="fas fa-flag me-1"></i>Status</th>
                                    <th><i class="fas fa-cogs me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $rowClass = '';
                                        $status = '';
                                        $statusClass = '';

                                        // Use vaccination status for display
                                        $vaccinationStatus = $row['status'] ?? 'pending';
                                        
                                        // Debug: Log the actual status from database
                                        // error_log("Vaccination ID: {$row['id']}, Status from DB: {$vaccinationStatus}");

                                        switch ($vaccinationStatus) {
                                            case 'completed':
                                                $statusClass = 'bg-success';
                                                $rowClass = 'table-success';
                                                break;
                                            case 'confirmed':
                                                $statusClass = 'bg-info';
                                                $rowClass = 'table-info';
                                                break;
                                            case 'cancelled':
                                                $statusClass = 'bg-danger';
                                                $rowClass = 'table-danger';
                                                break;
                                            case 'pending':
                                            default:
                                                $statusClass = 'bg-warning text-dark';
                                                $rowClass = 'table-warning';
                                        }

                                        // No appointment data needed - buttons work based on vaccination status only
                                        $appointmentId = 0;

                                        // Add special styling for today's appointments
                                        $isToday = (strtotime($row['schedule_date']) == strtotime('today'));
                                        if ($isToday) {
                                            $rowClass = 'table-info'; // Light blue highlight for today's row
                                        }

                                        // Highlight today's appointments
                                        if ($isToday) {
                                            $rowClass = 'table-primary';
                                        }

                                        echo "<tr class='appointment-row $rowClass'>";
                                        echo "<td><strong>" . htmlspecialchars($row['full_name']) . "</strong></td>";
                                        echo "<td>" . htmlspecialchars($row['vaccine_type']) . "</td>";
                                        
                                        // Display date and time
                                        $displayDate = $isToday ? date('M d, Y', strtotime($row['schedule_date'])) . ' (TODAY)' : date('M d, Y', strtotime($row['schedule_date']));
                                        
                                        // Check if time is set (not midnight 00:00:00)
                                        $scheduleTime = date('H:i:s', strtotime($row['schedule_date']));
                                        
                                        // Debug: Log the actual time from database
                                        // error_log("Vaccination ID {$row['id']}: schedule_date = {$row['schedule_date']}, extracted time = $scheduleTime");
                                        
                                        if ($scheduleTime !== '00:00:00') {
                                            // Time was set, show it
                                            $displayTime = date('g:i A', strtotime($row['schedule_date']));
                                            echo "<td>" . $displayDate . "<br><small class='text-muted'>" . $displayTime . "</small></td>";
                                        } else {
                                            // No time set (old record), show only date
                                            echo "<td>" . $displayDate . "</td>";
                                        }
                                        echo "<td>";
                                        if (empty($row['notes'])) {
                                            echo '<em class="text-muted">No notes</em>';
                                        } else {
                                            $notes = htmlspecialchars($row['notes']);
                                            if (strlen($notes) > 50) {
                                                echo '<span title="' . $notes . '">' . substr($notes, 0, 50) . '...</span>';
                                            } else {
                                                echo $notes;
                                            }
                                        }
                                        echo "</td>";
                                        // Display status with actual value for debugging
                                        $displayStatus = ucfirst($vaccinationStatus);
                                        echo "<td><span class='badge $statusClass' title='DB Status: {$vaccinationStatus}'>$displayStatus</span></td>";
                                        echo "<td>
                                                <div class='vaccination-actions-container'>";

                                        // All users can take actions on vaccinations
                                        $canTakeAction = true;

                                        // BUTTON LOGIC: 
                                        // PENDING: Confirmed=active, Completed=active, Cancelled=active
                                        // CONFIRMED: Confirmed=disabled (already confirmed), Completed=active, Cancelled=active
                                        // COMPLETED/CANCELLED: All disabled

                                        // CONFIRMED BUTTON (Green) - Only show for pending vaccinations
                                        if ($vaccinationStatus == 'pending') {
                                            echo "<a href='vaccinations.php?action=confirm&vaccination_id={$row['id']}' title='Mark as Confirmed' class='btn btn-sm btn-success vaccination-btn-confirm'>
                                                    <i class='fas fa-check'></i>
                                                  </a>";
                                        } else {
                                            $confirmTitle = $vaccinationStatus == 'confirmed' ? 'Already Confirmed' : 'Cannot Confirm';
                                            echo "<button title='$confirmTitle' disabled class='btn btn-sm btn-secondary vaccination-btn-confirm' style='opacity: 0.6; cursor: not-allowed;'>
                                                    <i class='fas fa-check'></i>
                                                  </button>";
                                        }

                                        // COMPLETED BUTTON (Blue) - Active for pending and confirmed
                                        if ($vaccinationStatus == 'pending' || $vaccinationStatus == 'confirmed') {
                                            echo "<a href='vaccinations.php?action=complete&vaccination_id={$row['id']}' title='Mark as Completed' onclick='return confirm(\"Mark this vaccination as completed?\")' class='btn btn-sm btn-primary vaccination-btn-complete'>
                                                    <i class='fas fa-check-circle'></i>
                                                  </a>";
                                        } else {
                                            echo "<button title='Already Completed' disabled class='btn btn-sm btn-secondary vaccination-btn-complete' style='opacity: 0.6; cursor: not-allowed;'>
                                                    <i class='fas fa-check-circle'></i>
                                                  </button>";
                                        }

                                        // CANCELLED BUTTON (Red) - Active for pending and confirmed
                                        if ($vaccinationStatus == 'pending' || $vaccinationStatus == 'confirmed') {
                                            echo "<a href='vaccinations.php?action=cancel&vaccination_id={$row['id']}' title='Mark as Cancelled' onclick='return confirm(\"Are you sure you want to cancel this vaccination?\")' class='btn btn-sm btn-danger vaccination-btn-cancel'>
                                                    <i class='fas fa-times'></i>
                                                  </a>";
                                        } else {
                                            echo "<button title='Already Cancelled' disabled class='btn btn-sm btn-secondary vaccination-btn-cancel' style='opacity: 0.6; cursor: not-allowed;'>
                                                    <i class='fas fa-times'></i>
                                                  </button>";
                                        }


                                        echo "</div></td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7' class='text-center py-4'>
                                            <div class='text-muted'>
                                                <i class='fas fa-syringe fa-3x mb-3 d-block'></i>
                                                <h5>No vaccination records found</h5>
                                                <p>Try adjusting your search criteria or schedule a new vaccination.</p>
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

    <!-- View Vaccination Modal -->
    <div class="modal fade" id="viewVaccinationModal" tabindex="-1" aria-labelledby="viewVaccinationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewVaccinationModalLabel">
                        <i class="fas fa-eye me-2"></i>Vaccination Details
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Vaccination Information -->
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-syringe me-2"></i>Vaccination Information
                            </h6>
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="35%" class="bg-light">
                                            <i class="fas fa-vial me-2"></i>Vaccine Type
                                        </th>
                                        <td class="fw-semibold" id="viewVaccineType"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">
                                            <i class="fas fa-calendar-alt me-2"></i>Schedule Date
                                        </th>
                                        <td id="viewScheduleDate"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">
                                            <i class="fas fa-flag me-2"></i>Status
                                        </th>
                                        <td><span id="viewStatus" class="badge"></span></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">
                                            <i class="fas fa-sticky-note me-2"></i>Notes
                                        </th>
                                        <td id="viewNotes"></td>
                                    </tr>
                                    <tr id="viewAdministeredDateRow" style="display: none;">
                                        <th class="bg-light">
                                            <i class="fas fa-calendar-check me-2"></i>Administered Date
                                        </th>
                                        <td id="viewAdministeredDate"></td>
                                    </tr>
                                    <tr id="viewAdministeredByRow" style="display: none;">
                                        <th class="bg-light">
                                            <i class="fas fa-user-md me-2"></i>Administered By
                                        </th>
                                        <td id="viewAdministeredBy"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Baby Information -->
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-baby me-2"></i>Baby Information
                            </h6>
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <p class="mb-2">
                                        <strong><i class="fas fa-user me-2"></i>Name:</strong>
                                        <span id="viewBabyName"></span>
                                    </p>
                                    <p class="mb-2">
                                        <strong><i class="fas fa-birthday-cake me-2"></i>Age:</strong>
                                        <span id="viewBabyAge"></span>
                                    </p>
                                    <p class="mb-2">
                                        <strong><i class="fas fa-calendar me-2"></i>Date of Birth:</strong>
                                        <span id="viewBabyDob"></span>
                                    </p>
                                    <p class="mb-0">
                                        <strong><i class="fas fa-users me-2"></i>Parent/Guardian:</strong>
                                        <span id="viewParentName"></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" id="editFromViewBtn">
                        <i class="fas fa-edit me-2"></i>Edit Vaccination
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Vaccination Modal -->
    <div class="modal fade" id="editVaccinationModal" tabindex="-1" aria-labelledby="editVaccinationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editVaccinationModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Vaccination - <span id="editBabyName"></span>
                    </h5>
                </div>
                <div class="modal-body">
                    <form id="editVaccinationForm" method="POST" action="vaccination_update_process.php">
                        <input type="hidden" id="editVaccinationId" name="vaccination_id">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editVaccineType" class="form-label">
                                    <i class="fas fa-vial me-2"></i>Vaccine Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="editVaccineType" name="vaccine_type" required>
                                    <option value="">-- Select vaccine type --</option>
                                    <optgroup label="Birth - 2 Months">
                                        <option value="BCG">BCG (Bacillus Calmette-Guérin)</option>
                                        <option value="Hepatitis B">Hepatitis B</option>
                                    </optgroup>
                                    <optgroup label="2 - 6 Months">
                                        <option value="DTaP">DTaP (Diphtheria, Tetanus, Pertussis)</option>
                                        <option value="Hib">Hib (Haemophilus influenzae type b)</option>
                                        <option value="IPV">IPV (Inactivated Poliovirus)</option>
                                        <option value="PCV">PCV (Pneumococcal Conjugate)</option>
                                        <option value="Rotavirus">Rotavirus</option>
                                    </optgroup>
                                    <optgroup label="12+ Months">
                                        <option value="MMR">MMR (Measles, Mumps, Rubella)</option>
                                        <option value="Varicella">Varicella (Chickenpox)</option>
                                        <option value="Hepatitis A">Hepatitis A</option>
                                    </optgroup>
                                    <optgroup label="Annual">
                                        <option value="Influenza">Influenza (Flu)</option>
                                    </optgroup>
                                    <option value="Other">Other (specify in notes)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="editScheduleDate" class="form-label">
                                    <i class="fas fa-calendar-alt me-2"></i>Schedule Date <span
                                        class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="editScheduleDate" name="schedule_date"
                                    required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editStatus" class="form-label">
                                    <i class="fas fa-flag me-2"></i>Status <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="editStatus" name="status" required
                                    onchange="toggleEditAdministeredFields()">
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="editAdministeredDateDiv" style="display: none;">
                                <label for="editAdministeredDate" class="form-label">
                                    <i class="fas fa-calendar-check me-2"></i>Administered Date
                                </label>
                                <input type="date" class="form-control" id="editAdministeredDate"
                                    name="administered_date">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editNotes" class="form-label">
                                <i class="fas fa-sticky-note me-2"></i>Additional Notes
                            </label>
                            <textarea class="form-control" id="editNotes" name="notes" rows="3"
                                placeholder="Any special instructions, allergies, or additional information..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" form="editVaccinationForm" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Vaccination
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Vaccination Modal -->
    <div class="modal fade" id="addVaccinationModal" tabindex="-1" aria-labelledby="addVaccinationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addVaccinationModalLabel">
                        <i class="fas fa-calendar-plus me-2"></i>Schedule New Vaccination
                    </h5>
                </div>
                <div class="modal-body">
                    <form id="addVaccinationForm" method="POST" action="vaccinations.php">
                        <input type="hidden" name="action" value="schedule_vaccination">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="baby_id" class="form-label">
                                    <i class="fas fa-baby me-2"></i>Select Baby <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="baby_id" name="baby_id" required>
                                    <option value="">-- Choose baby for vaccination --</option>
                                    <?php
                                    // Get all babies for dropdown
                                    $babies_query = "SELECT id, full_name, date_of_birth FROM babies ORDER BY full_name ASC";
                                    $babies_result = mysqli_query($conn, $babies_query);
                                    while ($baby_row = mysqli_fetch_assoc($babies_result)) {
                                        echo "<option value='{$baby_row['id']}'>" .
                                            htmlspecialchars($baby_row['full_name']) .
                                            " (" . getAge($baby_row['date_of_birth']) . ")" .
                                            "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="vaccine_type" class="form-label">
                                    <i class="fas fa-vial me-2"></i>Vaccine Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="vaccine_type" name="vaccine_type" required>
                                    <option value="">-- Select vaccine type --</option>
                                    <optgroup label="Birth - 2 Months">
                                        <option value="BCG">BCG (Bacillus Calmette-Guérin)</option>
                                        <option value="Hepatitis B">Hepatitis B</option>
                                    </optgroup>
                                    <optgroup label="2 - 6 Months">
                                        <option value="DTaP">DTaP (Diphtheria, Tetanus, Pertussis)</option>
                                        <option value="Hib">Hib (Haemophilus influenzae type b)</option>
                                        <option value="IPV">IPV (Inactivated Poliovirus)</option>
                                        <option value="PCV">PCV (Pneumococcal Conjugate)</option>
                                        <option value="Rotavirus">Rotavirus</option>
                                    </optgroup>
                                    <optgroup label="12+ Months">
                                        <option value="MMR">MMR (Measles, Mumps, Rubella)</option>
                                        <option value="Varicella">Varicella (Chickenpox)</option>
                                        <option value="Hepatitis A">Hepatitis A</option>
                                    </optgroup>
                                    <optgroup label="Annual">
                                        <option value="Influenza">Influenza (Flu)</option>
                                    </optgroup>
                                    <option value="Other">Other (specify in notes)</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="schedule_date" class="form-label">
                                    <i class="fas fa-calendar-alt me-2"></i>Schedule Date <span
                                        class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="schedule_date" name="schedule_date"
                                    min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="schedule_time" class="form-label">
                                    <i class="fas fa-clock me-2"></i>Preferred Time <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="schedule_time" name="schedule_time" required>
                                    <option value="">-- Select time --</option>
                                    <option value="08:00">8:00 AM</option>
                                    <option value="08:30">8:30 AM</option>
                                    <option value="09:00">9:00 AM</option>
                                    <option value="09:30">9:30 AM</option>
                                    <option value="10:00">10:00 AM</option>
                                    <option value="10:30">10:30 AM</option>
                                    <option value="11:00">11:00 AM</option>
                                    <option value="11:30">11:30 AM</option>
                                    <option value="13:00">1:00 PM</option>
                                    <option value="13:30">1:30 PM</option>
                                    <option value="14:00">2:00 PM</option>
                                    <option value="14:30">2:30 PM</option>
                                    <option value="15:00">3:00 PM</option>
                                    <option value="15:30">3:30 PM</option>
                                    <option value="16:00">4:00 PM</option>
                                    <option value="16:30">4:30 PM</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">
                                <i class="fas fa-sticky-note me-2"></i>Additional Notes
                            </label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                placeholder="Any special instructions, allergies, or additional information..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" form="addVaccinationForm" class="btn btn-primary" id="submitVaccinationBtn">
                        <i class="fas fa-calendar-check me-2"></i>Schedule Vaccination
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom spacing for better UX -->
    <div class="bottom-spacing"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Function to check booked vaccination times for a specific date
        async function checkBookedVaccinationTimes(selectedDate) {
            if (!selectedDate) return [];
            
            try {
                const response = await fetch('api/get_booked_vaccination_times.php?date=' + selectedDate);
                const data = await response.json();
                return data.booked_times || [];
            } catch (error) {
                console.error('Error fetching booked times:', error);
                return [];
            }
        }

        // Function to update time slot availability
        async function updateVaccinationTimeSlots() {
            const dateInput = document.getElementById('schedule_date');
            const timeSelect = document.getElementById('schedule_time');
            
            if (!dateInput || !timeSelect) return;
            
            const selectedDate = dateInput.value;
            
            if (!selectedDate) {
                // Reset all options to enabled
                Array.from(timeSelect.options).forEach(option => {
                    if (option.value) {
                        option.disabled = false;
                        option.text = option.text.replace(' (Booked)', '').replace(' (Past)', '');
                    }
                });
                return;
            }
            
            // Get booked times for selected date
            const bookedTimes = await checkBookedVaccinationTimes(selectedDate);
            
            // Check if selected date is today
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const selected = new Date(selectedDate);
            selected.setHours(0, 0, 0, 0);
            const isToday = selected.getTime() === today.getTime();
            
            // Get current time in HH:MM format
            const now = new Date();
            const currentTime = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
            
            // Update time options
            Array.from(timeSelect.options).forEach(option => {
                if (option.value) {
                    const isBooked = bookedTimes.includes(option.value);
                    const isPast = isToday && option.value < currentTime;
                    
                    // Disable if booked or past time
                    option.disabled = isBooked || isPast;
                    
                    // Update option text
                    let baseText = option.text.replace(' (Booked)', '').replace(' (Past)', '');
                    if (isBooked) {
                        option.text = baseText + ' (Booked)';
                    } else if (isPast) {
                        option.text = baseText + ' (Past)';
                    } else {
                        option.text = baseText;
                    }
                }
            });
        }

        // Maintain scroll position during search/filter
        document.addEventListener('DOMContentLoaded', function () {
            // Store scroll position before form submission
            const searchForm = document.querySelector('form');
            if (searchForm) {
                searchForm.addEventListener('submit', function () {
                    const searchSection = document.getElementById('search-section');
                    if (searchSection) {
                        const scrollPosition = searchSection.offsetTop - 20;
                        sessionStorage.setItem('vaccinationScrollPosition', scrollPosition);
                    }
                });
            }

            // Restore scroll position after page load
            const savedPosition = sessionStorage.getItem('vaccinationScrollPosition');
            if (savedPosition && window.location.hash === '#search-section') {
                // Remove the hash to prevent default scrolling
                history.replaceState(null, null, window.location.pathname + window.location.search);
                // Set scroll position immediately without animation
                window.scrollTo(0, parseInt(savedPosition));
                // Clear the stored position
                sessionStorage.removeItem('vaccinationScrollPosition');
            }

            // Modal form handling
            const addVaccinationModal = document.getElementById('addVaccinationModal');
            const scheduleDate = document.getElementById('schedule_date');
            const scheduleTime = document.getElementById('schedule_time');

            // Set minimum date and default value when modal opens
            addVaccinationModal.addEventListener('show.bs.modal', function () {
                const today = new Date().toISOString().split('T')[0];
                scheduleDate.min = today;
                if (!scheduleDate.value) {
                    scheduleDate.value = today;
                }
                // Check booked times when modal opens
                updateVaccinationTimeSlots();
            });

            // Update time slots when date changes
            if (scheduleDate) {
                scheduleDate.addEventListener('change', function() {
                    // Reset time selection
                    if (scheduleTime) {
                        scheduleTime.value = '';
                    }
                    // Update available time slots
                    updateVaccinationTimeSlots();
                });
            }

            // Reset form when modal closes
            addVaccinationModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('addVaccinationForm').reset();
                submitted = false;
                const submitBtn = document.getElementById('submitVaccinationBtn');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-calendar-check me-2"></i>Schedule Vaccination';
            });

            // Prevent double submission
            const addVaccinationForm = document.getElementById('addVaccinationForm');
            let submitted = false;
            
            addVaccinationForm.addEventListener('submit', function (e) {
                const submitBtn = document.getElementById('submitVaccinationBtn');
                
                if (submitted || submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                
                submitted = true;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Scheduling...';
            });

            // Edit vaccination modal handling
            const editVaccinationModal = document.getElementById('editVaccinationModal');

            editVaccinationModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;

                // Get data from button attributes
                const vaccinationId = button.getAttribute('data-id');
                const vaccineType = button.getAttribute('data-vaccine-type');
                const scheduleDate = button.getAttribute('data-schedule-date');
                const status = button.getAttribute('data-status');
                const notes = button.getAttribute('data-notes');
                const babyName = button.getAttribute('data-baby-name');

                // Populate form fields
                document.getElementById('editVaccinationId').value = vaccinationId;
                document.getElementById('editVaccineType').value = vaccineType;
                document.getElementById('editScheduleDate').value = scheduleDate;
                document.getElementById('editStatus').value = status;
                document.getElementById('editNotes').value = notes;
                document.getElementById('editBabyName').textContent = babyName;

                // Set administered date to today if status is completed
                if (status === 'completed') {
                    document.getElementById('editAdministeredDate').value = new Date().toISOString().split('T')[0];
                    document.getElementById('editAdministeredDateDiv').style.display = 'block';
                } else {
                    document.getElementById('editAdministeredDateDiv').style.display = 'none';
                }
            });

            // Reset edit form when modal closes
            editVaccinationModal.addEventListener('hidden.bs.modal', function () {
                document.getElementById('editVaccinationForm').reset();
                document.getElementById('editAdministeredDateDiv').style.display = 'none';
            });

            // View vaccination modal handling
            const viewVaccinationModal = document.getElementById('viewVaccinationModal');
            let currentViewData = {};

            viewVaccinationModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;

                // Get data from button attributes
                currentViewData = {
                    id: button.getAttribute('data-id'),
                    vaccineType: button.getAttribute('data-vaccine-type'),
                    scheduleDate: button.getAttribute('data-schedule-date'),
                    status: button.getAttribute('data-status'),
                    notes: button.getAttribute('data-notes'),
                    babyName: button.getAttribute('data-baby-name'),
                    babyDob: button.getAttribute('data-baby-dob'),
                    parentName: button.getAttribute('data-parent-name')
                };

                // Populate modal fields
                document.getElementById('viewVaccineType').textContent = currentViewData.vaccineType;
                document.getElementById('viewScheduleDate').textContent = formatDate(currentViewData.scheduleDate);
                document.getElementById('viewBabyName').textContent = currentViewData.babyName;
                document.getElementById('viewBabyDob').textContent = formatDate(currentViewData.babyDob);
                document.getElementById('viewBabyAge').textContent = calculateAge(currentViewData.babyDob);
                document.getElementById('viewParentName').textContent = currentViewData.parentName;
                document.getElementById('viewNotes').textContent = currentViewData.notes || 'N/A';

                // Set status badge
                const statusElement = document.getElementById('viewStatus');
                const scheduleDate = new Date(currentViewData.scheduleDate);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                scheduleDate.setHours(0, 0, 0, 0);

                if (currentViewData.status === 'completed') {
                    statusElement.textContent = 'Completed';
                    statusElement.className = 'badge bg-success';
                } else if (scheduleDate < today) {
                    statusElement.textContent = 'Overdue';
                    statusElement.className = 'badge bg-danger';
                } else if (scheduleDate.getTime() === today.getTime()) {
                    statusElement.textContent = 'Today';
                    statusElement.className = 'badge bg-primary';
                } else {
                    statusElement.textContent = 'Pending';
                    statusElement.className = 'badge bg-warning text-dark';
                }

                // Hide administered fields for now (would need additional AJAX call to get this data)
                document.getElementById('viewAdministeredDateRow').style.display = 'none';
                document.getElementById('viewAdministeredByRow').style.display = 'none';
            });

            // Edit from view modal
            document.getElementById('editFromViewBtn').addEventListener('click', function () {
                // Close view modal first
                const viewModal = bootstrap.Modal.getInstance(viewVaccinationModal);
                if (viewModal) {
                    viewModal.hide();
                }

                // Wait for view modal to close, then open edit modal
                setTimeout(() => {
                    // Populate edit form with current data
                    document.getElementById('editVaccinationId').value = currentViewData.id;
                    document.getElementById('editVaccineType').value = currentViewData.vaccineType;
                    document.getElementById('editScheduleDate').value = currentViewData.scheduleDate;
                    document.getElementById('editStatus').value = currentViewData.status;
                    document.getElementById('editNotes').value = currentViewData.notes || '';
                    document.getElementById('editBabyName').textContent = currentViewData.babyName;

                    // Handle administered date field
                    if (currentViewData.status === 'completed') {
                        document.getElementById('editAdministeredDate').value = new Date().toISOString().split('T')[0];
                        document.getElementById('editAdministeredDateDiv').style.display = 'block';
                    } else {
                        document.getElementById('editAdministeredDateDiv').style.display = 'none';
                    }

                    // Open edit modal
                    const editModalElement = document.getElementById('editVaccinationModal');
                    let editModal = bootstrap.Modal.getInstance(editModalElement);
                    if (!editModal) {
                        editModal = new bootstrap.Modal(editModalElement);
                    }
                    editModal.show();
                }, 300);
            });
        });

        // Helper functions
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function calculateAge(birthDate) {
            const birth = new Date(birthDate);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();

            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }

            if (age < 1) {
                const months = (today.getFullYear() - birth.getFullYear()) * 12 + monthDiff;
                return months <= 0 ? 'Newborn' : months + ' month' + (months > 1 ? 's' : '') + ' old';
            }

            return age + ' year' + (age > 1 ? 's' : '') + ' old';
        }

        // Function to toggle administered date field in edit modal
        function toggleEditAdministeredFields() {
            const status = document.getElementById('editStatus').value;
            const administeredDateDiv = document.getElementById('editAdministeredDateDiv');
            const administeredDateInput = document.getElementById('editAdministeredDate');

            if (status === 'completed') {
                administeredDateDiv.style.display = 'block';
                if (!administeredDateInput.value) {
                    administeredDateInput.value = new Date().toISOString().split('T')[0];
                }
            } else {
                administeredDateDiv.style.display = 'none';
                administeredDateInput.value = '';
            }
        }

        // Check if we should show success modals
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // Schedule success modal
            if (urlParams.has('show_success_modal')) {
                const successModal = document.getElementById('successModal');
                const babyName = urlParams.get('baby_name') || 'the baby';
                const messageElement = document.getElementById('successMessage');
                
                if (successModal && messageElement) {
                    messageElement.textContent = `Vaccination scheduled successfully for ${babyName}.`;
                    successModal.classList.add('show');
                    
                    urlParams.delete('show_success_modal');
                    urlParams.delete('baby_name');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    history.replaceState(null, '', newUrl);
                }
            }
            
            // Confirm modal
            if (urlParams.has('show_confirm_modal')) {
                console.log('Showing confirm modal'); // Debug
                const confirmModal = document.getElementById('confirmModal');
                if (confirmModal) {
                    confirmModal.classList.add('show');
                    urlParams.delete('show_confirm_modal');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    history.replaceState(null, '', newUrl);
                } else {
                    console.error('Confirm modal element not found!'); // Debug
                }
            }
            
            // Complete modal
            if (urlParams.has('show_complete_modal')) {
                const completeModal = document.getElementById('completeModal');
                if (completeModal) {
                    completeModal.classList.add('show');
                    urlParams.delete('show_complete_modal');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    history.replaceState(null, '', newUrl);
                }
            }
            
            // Cancel modal
            if (urlParams.has('show_cancel_modal')) {
                const cancelModal = document.getElementById('cancelModal');
                if (cancelModal) {
                    cancelModal.classList.add('show');
                    urlParams.delete('show_cancel_modal');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    history.replaceState(null, '', newUrl);
                }
            }
            
            // Archive modal
            if (urlParams.has('show_archive_modal')) {
                const archiveModal = document.getElementById('archiveModal');
                if (archiveModal) {
                    archiveModal.classList.add('show');
                    urlParams.delete('show_archive_modal');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    history.replaceState(null, '', newUrl);
                }
            }
        });

        // Modal close functions
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('show');
        }
        
        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('show');
        }
        
        function closeCompleteModal() {
            document.getElementById('completeModal').classList.remove('show');
        }
        
        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('show');
        }
        
        function closeArchiveModal() {
            document.getElementById('archiveModal').classList.remove('show');
        }
        
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
    </script>

    <!-- Success Modal (Schedule) -->
    <div id="successModal" class="message-modal">
        <div class="message-modal-content message-modal-success">
            <div class="message-modal-header">
                <button type="button" class="message-modal-close" onclick="closeSuccessModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="message-modal-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3 class="message-modal-title">Success!</h3>
            </div>
            <div class="message-modal-body">
                <p class="message-modal-message" id="successMessage">Vaccination scheduled successfully.</p>
                <div class="message-modal-actions">
                    <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="closeSuccessModal()">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmed Modal -->
    <div id="confirmModal" class="message-modal">
        <div class="message-modal-content message-modal-success">
            <div class="message-modal-header">
                <button type="button" class="message-modal-close" onclick="closeConfirmModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="message-modal-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3 class="message-modal-title">Confirmed!</h3>
            </div>
            <div class="message-modal-body">
                <p class="message-modal-message">Vaccination has been confirmed successfully.</p>
                <div class="message-modal-actions">
                    <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="closeConfirmModal()">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Completed Modal -->
    <div id="completeModal" class="message-modal">
        <div class="message-modal-content message-modal-success">
            <div class="message-modal-header">
                <button type="button" class="message-modal-close" onclick="closeCompleteModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="message-modal-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="message-modal-title">Completed!</h3>
            </div>
            <div class="message-modal-body">
                <p class="message-modal-message">Vaccination has been marked as completed successfully.</p>
                <div class="message-modal-actions">
                    <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="closeCompleteModal()">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancelled Modal -->
    <div id="cancelModal" class="message-modal">
        <div class="message-modal-content message-modal-success">
            <div class="message-modal-header">
                <button type="button" class="message-modal-close" onclick="closeCancelModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="message-modal-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3 class="message-modal-title">Cancelled!</h3>
            </div>
            <div class="message-modal-body">
                <p class="message-modal-message">Vaccination has been cancelled successfully.</p>
                <div class="message-modal-actions">
                    <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="closeCancelModal()">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Archived Modal -->
    <div id="archiveModal" class="message-modal">
        <div class="message-modal-content message-modal-success">
            <div class="message-modal-header">
                <button type="button" class="message-modal-close" onclick="closeArchiveModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="message-modal-icon">
                    <i class="fas fa-archive"></i>
                </div>
                <h3 class="message-modal-title">Archived!</h3>
            </div>
            <div class="message-modal-body">
                <p class="message-modal-message">Vaccination record has been archived successfully.</p>
                <div class="message-modal-actions">
                    <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="closeArchiveModal()">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Force page reload when navigating back from another page
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                // Page was loaded from cache (back button), force reload
                window.location.reload();
            }
        });
    </script>
</body>

</html>