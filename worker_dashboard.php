<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Only approved workers can view - redirect admins to main dashboard
requireApproved();

// Redirect admins to the main dashboard
if (isAdmin()) {
    header("Location: index.php");
    exit;
}

// Handle simple actions: confirm, complete, cancel
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    $allowed = ['confirm' => 'confirmed', 'complete' => 'completed', 'cancel' => 'cancelled'];

    if ($action === 'confirm') {
        $update = "UPDATE appointments SET status = 'confirmed' WHERE id = $id";
    } elseif ($action === 'complete') {
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
        
        // Move to archive and delete from main table
        $archive_query = "INSERT INTO appointments_archive (user_id, fullname, appointment_type, preferred_datetime, notes, status, created_at, archived_at) 
                         SELECT user_id, fullname, appointment_type, preferred_datetime, notes, 'completed', created_at, NOW() 
                         FROM appointments WHERE id = $id";
        $delete_query = "DELETE FROM appointments WHERE id = $id";
        
        if (mysqli_query($conn, $archive_query) && mysqli_query($conn, $delete_query)) {
            $update = null;
        } else {
            $update = false;
        }
    } elseif ($action === 'cancel') {
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
        
        // Move to archive and delete from main table
        $archive_query = "INSERT INTO appointments_archive (user_id, fullname, appointment_type, preferred_datetime, notes, status, created_at, archived_at) 
                         SELECT user_id, fullname, appointment_type, preferred_datetime, notes, 'cancelled', created_at, NOW() 
                         FROM appointments WHERE id = $id";
        $delete_query = "DELETE FROM appointments WHERE id = $id";
        
        if (mysqli_query($conn, $archive_query) && mysqli_query($conn, $delete_query)) {
            $update = null;
        } else {
            $update = false;
        }
    } else {
        $update = null;
    }

    if ($update) {
        mysqli_query($conn, $update);
    } elseif ($update === false) {
        // Archive operation failed
        error_log("Failed to archive appointment with ID: $id");
    }

    // Redirect back to avoid duplicate action on refresh
    header('Location: worker_dashboard.php');
    exit;
}

// Fetch counts for dashboard cards
$appointments_query = "SELECT COUNT(*) as total_appointments FROM appointments";
$appointments_result = mysqli_query($conn, $appointments_query);
$total_appointments = mysqli_fetch_assoc($appointments_result)['total_appointments'];

$medicines_query = "SELECT COUNT(*) as total_medicines FROM medicines";
$medicines_result = mysqli_query($conn, $medicines_query);
$total_medicines = mysqli_fetch_assoc($medicines_result)['total_medicines'];

$vaccinations_query = "SELECT COUNT(*) as total_vaccinations FROM vaccinations";
$vaccinations_result = mysqli_query($conn, $vaccinations_query);
$total_vaccinations = mysqli_fetch_assoc($vaccinations_result)['total_vaccinations'];

$babies_query = "SELECT COUNT(*) as total_babies FROM babies";
$babies_result = mysqli_query($conn, $babies_query);
$total_babies = mysqli_fetch_assoc($babies_result)['total_babies'];

// Handle appointment filtering
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

}

if (!empty($search)) {
    $where_clauses[] = "(a.fullname LIKE '%$search%' OR a.appointment_type LIKE '%$search%' OR a.notes LIKE '%$search%')";
}

$where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch appointments with priority ordering (current/today first, then by status and date)
$query = "SELECT a.*, u.username FROM appointments a 
          LEFT JOIN users u ON a.user_id = u.id 
          $where_clause
          ORDER BY 
            CASE 
                WHEN DATE(a.preferred_datetime) = CURDATE() THEN 0
                WHEN a.status = 'pending' THEN 1
                WHEN a.status = 'confirmed' THEN 2
                WHEN a.status = 'completed' THEN 3
                ELSE 4
            END,
            a.preferred_datetime ASC";
$result = mysqli_query($conn, $query);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome <?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Worker'); ?></title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Navbar Styles -->
    <link rel="stylesheet" href="assets/css/navbar.css">
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/residents_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/worker_dashboard.css">
    <!-- Emergency Alerts Styles -->
    <link rel="stylesheet" href="assets/css/emergency_alerts.css">
    <!-- Responsive Styles -->
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?php echo time(); ?>">
    <style>
        /* Alert close button positioning */
        .alert {
            padding-right: 3rem;
            position: relative;
        }

        .alert .btn-close {
            position: absolute !important;
            top: 50% !important;
            right: 1rem !important;
            transform: translateY(-50%) !important;
            padding: 0.5rem;
        }

        .alert .btn-close:hover {
            transform: translateY(-50%) scale(1.1) !important;
        }

        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .welcome-subtitle {
                display: none !important;
            }
            
            .section-title {
                display: flex !important;
                align-items: center !important;
                white-space: nowrap !important;
            }
            
            .section-title i {
                margin-right: 0.5rem !important;
                flex-shrink: 0 !important;
            }
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
                        <i class="fas fa-hand-wave me-2 text-warning"></i>
                        Welcome, <?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Worker'); ?>!
                    </h1>
                    <p class="welcome-subtitle">Manage health appointments and provide quality healthcare services to residents.</p>
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

            <!-- Dashboard Widgets Section -->
            <section class="dashboard-widgets mb-4">
                <div class="row g-4">
                    <!-- Left Side: Google Calendar Widget -->
                    <div class="col-lg-7">
                        <?php include 'includes/google_calendar_widget.php'; ?>
                    </div>
                    
                    <!-- Right Side: Health Tip and Weather Stacked -->
                    <div class="col-lg-5">
                        <div class="row g-4">
                            <!-- Daily Health Tip Widget -->
                            <div class="col-12">
                                <div class="widget-card health-tip-widget">
                                    <div class="widget-header">
                                        <div class="widget-title-section">
                                            <span class="widget-icon">💡</span>
                                            <h3 class="widget-title">Daily Health Tip</h3>
                                        </div>
                                        <button class="widget-refresh-btn" id="refreshTipBtn" title="Get new tip">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                    <div class="widget-body">
                                        <div class="loading-state" id="loadingState">
                                            <div class="spinner"></div>
                                            <p>Loading tip…</p>
                                        </div>
                                        <div class="tip-content" id="tipContent" style="display: none;">
                                            <blockquote class="tip-quote" id="tipQuote">
                                                <!-- Quote text will be inserted here -->
                                            </blockquote>
                                            <p class="tip-author" id="tipAuthor">
                                                <!-- Author will be inserted here -->
                                            </p>
                                        </div>
                                        <div class="error-state" id="errorState" style="display: none;">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <p>Unable to load tip.</p>
                                            <button class="retry-btn" onclick="fetchHealthTip()">Retry</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Today's Weather Widget -->
                            <div class="col-12">
                                <div class="widget-card weather-widget">
                                    <div class="widget-header">
                                        <div class="widget-title-section">
                                            <span class="widget-icon">🌦</span>
                                            <h3 class="widget-title">Today's Weather</h3>
                                        </div>
                                        <button class="widget-refresh-btn" id="refreshWeatherBtn" title="Refresh weather">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                    <div class="widget-body">
                                        <!-- Loading State -->
                                        <div class="weather-loading-state" id="weatherLoadingState">
                                            <div class="spinner"></div>
                                            <p>Loading weather…</p>
                                        </div>
                                        
                                        <!-- Weather Content -->
                                        <div class="weather-content" id="weatherContent" style="display: none;">
                                            <div class="weather-main-compact">
                                                <div class="weather-location-compact">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <span id="weatherCity">Manila</span>
                                                </div>
                                                <div class="weather-display">
                                                    <img id="weatherIcon" src="" alt="Weather icon" class="weather-icon-compact">
                                                    <div class="weather-temp-compact">
                                                        <div class="temp-value" id="weatherTemp">--°C</div>
                                                        <div class="weather-condition-compact" id="weatherCondition">--</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="weather-details-compact">
                                                <div class="weather-detail-compact">
                                                    <i class="fas fa-tint"></i>
                                                    <div>
                                                        <span class="detail-label">Humidity</span>
                                                        <span class="detail-value" id="weatherHumidity">--%</span>
                                                    </div>
                                                </div>
                                                <div class="weather-detail-compact">
                                                    <i class="fas fa-wind"></i>
                                                    <div>
                                                        <span class="detail-label">Wind</span>
                                                        <span class="detail-value" id="weatherWind">-- m/s</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Error State -->
                                        <div class="weather-error-state" id="weatherErrorState" style="display: none;">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <p>Unable to load weather.</p>
                                            <button class="retry-btn" onclick="fetchWeatherData()">Retry</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Quick Actions -->
            <section class="quick-actions">
                <h2 class="section-title">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h2>
                <!-- All 5 Cards in One Line -->
                <div class="row g-3">
                    <div class="col-xl col-lg col-md-6 col-6 mb-3">
                        <div class="card action-card schedule worker-card h-100">
                            <div class="card-body text-center py-4 d-flex flex-column">
                                <div class="action-icon mb-3" style="width: 55px; height: 55px;">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h5 class="card-title mb-3">Appointments</h5>
                                <p class="card-text mb-4 flex-grow-1">View and manage all scheduled appointments from residents.</p>
                                <a href="appointments.php" class="btn action-btn btn-schedule mt-auto">
                                    <i class="fas fa-calendar-check me-2"></i> Appointments
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-lg col-md-6 col-6 mb-3">
                        <div class="card action-card profile worker-card h-100">
                            <div class="card-body text-center py-4 d-flex flex-column">
                                <div class="action-icon mb-3" style="width: 55px; height: 55px;">
                                    <i class="fas fa-pills"></i>
                                </div>
                                <h5 class="card-title mb-3">Medicines</h5>
                                <p class="card-text mb-4 flex-grow-1">Monitor medicine stock levels and manage inventory.</p>
                                <a href="medicines.php" class="btn action-btn btn-profile mt-auto">
                                    <i class="fas fa-boxes me-2"></i> Medicines
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-lg col-md-6 col-6 mb-3">
                        <div class="card action-card appointments worker-card h-100">
                            <div class="card-body text-center py-4 d-flex flex-column">
                                <div class="action-icon mb-3" style="width: 55px; height: 55px;">
                                    <i class="fas fa-syringe"></i>
                                </div>
                                <h5 class="card-title mb-3">Vaccinations</h5>
                                <p class="card-text mb-4 flex-grow-1">Manage vaccination schedules and immunization records.</p>
                                <a href="vaccinations.php" class="btn action-btn btn-appointments mt-auto">
                                    <i class="fas fa-syringe me-2"></i> Vaccinations
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-lg col-md-6 col-6 mb-3">
                        <div class="card action-card babies worker-card h-100">
                            <div class="card-body text-center py-4 d-flex flex-column">
                                <div class="action-icon mb-3" style="width: 55px; height: 55px;">
                                    <i class="fas fa-baby"></i>
                                </div>
                                <h5 class="card-title mb-3">Baby Records</h5>
                                <p class="card-text mb-4 flex-grow-1">Manage baby information and vaccination records.</p>
                                <a href="babies.php" class="btn action-btn btn-babies mt-auto">
                                    <i class="fas fa-baby me-2"></i> Babies
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl col-lg col-md-6 col-6 mb-3">
                        <div class="card action-card residents worker-card h-100">
                            <div class="card-body text-center py-4 d-flex flex-column">
                                <div class="action-icon mb-3" style="width: 55px; height: 55px;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h5 class="card-title mb-3">Residents</h5>
                                <p class="card-text mb-4 flex-grow-1">Manage resident information, demographics, and health records.</p>
                                <a href="residents.php" class="btn action-btn btn-residents mt-auto">
                                    <i class="fas fa-users me-2"></i> Residents
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Statistics Cards -->
            <section class="statistics-section mb-4">
                <h2 class="section-title">
                    <i class="fas fa-chart-line me-2"></i>Statistics Overview
                </h2>
                <div class="row">
                    <div class="col-6 col-md-6 col-lg-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stats-icon checkup-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $total_appointments; ?></h3>
                                <p class="stats-label">Total Appointments</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-6 col-lg-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stats-icon vaccination-icon">
                                    <i class="fas fa-pills"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $total_medicines; ?></h3>
                                <p class="stats-label">Total Medicines</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-6 col-lg-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stats-icon vaccination-icon">
                                    <i class="fas fa-syringe"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $total_vaccinations; ?></h3>
                                <p class="stats-label">Total Vaccinations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-6 col-lg-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <div class="stats-icon checkup-icon">
                                    <i class="fas fa-baby"></i>
                                </div>
                                <h3 class="stats-number"><?php echo $total_babies; ?></h3>
                                <p class="stats-label">Total Babies</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

    <!-- Appointments Modal -->
    <div class="modal fade" id="appointmentsModal" tabindex="-1" aria-labelledby="appointmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="appointmentsModalLabel">
                        <i class="fas fa-calendar-check"></i> Manage Appointments
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Filter Controls -->
                    <div class="mb-4 p-3 border-bottom">
                        <form method="GET" class="row g-3" id="appointmentFilterForm">
                            <div class="col-md-4">
                                <label for="search" class="form-label fw-semibold">
                                    <i class="fas fa-search me-1"></i>Search Appointments
                                </label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Name, type, or notes..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="filter" class="form-label fw-semibold">
                                    <i class="fas fa-filter me-1"></i>Filter by Status
                                </label>
                                <select class="form-select" id="filter" name="filter">
                                    <option value="" <?php echo $filter == '' ? 'selected' : ''; ?>>All Appointments</option>
                                    <option value="today" <?php echo $filter == 'today' ? 'selected' : ''; ?>>Today's Appointments</option>
                                    <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>

                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-center " style="padding-top: 2rem;">
                                <button type="button" class="btn btn-primary me-2" onclick="applyFilters()">
                                    <i class="fas fa-search me-1"></i>Apply Filters
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                                    <i class="fas fa-redo me-1"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="table-responsive shadow-sm rounded">
                        <table class="table table-hover mb-0 appointments-table">
                            <thead class="table-primary">
                                <tr>
                                    <th class="border-0 py-2 px-3" style="font-size: 0.85rem;">#</th>
                                    <th class="border-0 py-2 px-3" style="font-size: 0.85rem;">
                                        <i class="fas fa-user me-1"></i>Full Name
                                    </th>
                                    <th class="border-0 py-2 px-3" style="font-size: 0.85rem;">
                                        <i class="fas fa-medical-kit me-1"></i>Type
                                    </th>
                                    <th class="border-0 py-2 px-3" style="font-size: 0.85rem;">
                                        <i class="fas fa-calendar-clock me-1"></i>Date & Time
                                    </th>
                                    <th class="border-0 py-2 px-3" style="font-size: 0.85rem;">
                                        <i class="fas fa-sticky-note me-1"></i>Notes
                                    </th>
                                    <th class="border-0 py-2 px-3 text-center" style="font-size: 0.85rem;">
                                        <i class="fas fa-info-circle me-1"></i>Status
                                    </th>
                                    <th class="border-0 py-2 px-3 text-center" style="font-size: 0.85rem;">
                                        <i class="fas fa-cogs me-1"></i>Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php $i = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr class="appointment-row">
                                            <td class="py-2 px-3 align-middle" style="font-size: 0.9rem;">
                                                <span class="fw-semibold text-primary"><?php echo $i++; ?></span>
                                            </td>
                                            <td class="py-2 px-3 align-middle" style="font-size: 0.9rem;">
                                                <span class="fw-semibold text-dark"><?php echo htmlspecialchars($row['fullname']); ?></span>
                                            </td>
                                            <td class="py-2 px-3 align-middle" style="font-size: 0.9rem;">
                                                <div class="d-flex align-items-center">
                                                    <div class="appointment-icon me-2" style="width: 30px; height: 30px;">
                                                        <i class="fas fa-<?php echo $row['appointment_type'] == 'Vaccination' ? 'syringe text-primary' : 'stethoscope text-info'; ?>" style="font-size: 0.9rem;"></i>
                                                    </div>
                                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($row['appointment_type']); ?></span>
                                                </div>
                                            </td>
                                            <td class="py-2 px-3 align-middle" style="font-size: 0.9rem;">
                                                <div class="appointment-datetime">
                                                    <div class="fw-semibold text-dark" style="font-size: 0.85rem;"><?php echo date('M d, Y', strtotime($row['preferred_datetime'])); ?></div>
                                                    <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('g:i A', strtotime($row['preferred_datetime'])); ?></small>
                                                </div>
                                            </td>
                                            <td class="py-2 px-3 align-middle" style="font-size: 0.9rem;">
                                                <div class="appointment-notes">
                                                    <?php if (!empty($row['notes'])): ?>
                                                        <span class="text-dark" style="font-size: 0.85rem;"><?php echo htmlspecialchars($row['notes']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic" style="font-size: 0.8rem;">No additional notes</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-2 px-3 align-middle text-center">
                                                <span class="badge px-2 py-1 rounded-pill bg-<?php 
                                                    echo $row['status'] == 'confirmed' ? 'success' : 
                                                        ($row['status'] == 'completed' ? 'info' : 
                                                        ($row['status'] == 'cancelled' ? 'danger' : 'warning')); 
                                                ?>" style="font-size: 0.75rem;">
                                                    <i class="fas fa-<?php 
                                                        if ($row['status'] == 'confirmed') {
                                                            echo 'check-circle';
                                                        } elseif ($row['status'] == 'completed') {
                                                            echo 'check-double';
                                                        } elseif ($row['status'] == 'cancelled') {
                                                            echo 'times-circle';
                                                        } else {
                                                            echo 'clock';
                                                        }
                                                    ?> me-1" style="font-size: 0.7rem;"></i>
                                                    <?php echo ucfirst($row['status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-2 px-3 align-middle text-center">
                                                <div class="btn-group" role="group">
                                                    <?php if ($row['status'] == 'pending'): ?>
                                                        <a href="worker_dashboard.php?action=confirm&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success" title="Confirm Appointment" style="padding: 0.25rem 0.4rem; font-size: 0.75rem;">
                                                            <i class="fas fa-check" style="font-size: 0.7rem;"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-success disabled opacity-50" title="Already Confirmed" disabled style="padding: 0.25rem 0.4rem; font-size: 0.75rem;">
                                                            <i class="fas fa-check" style="font-size: 0.7rem;"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($row['status'] != 'completed' && $row['status'] != 'cancelled'): ?>
                                                        <a href="worker_dashboard.php?action=complete&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary" title="Mark as Complete" style="padding: 0.25rem 0.4rem; font-size: 0.75rem;">
                                                            <i class="fas fa-check-circle" style="font-size: 0.7rem;"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-primary disabled opacity-50" title="Cannot Complete" disabled style="padding: 0.25rem 0.4rem; font-size: 0.75rem;">
                                                            <i class="fas fa-check-circle" style="font-size: 0.7rem;"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($row['status'] != 'cancelled' && $row['status'] != 'completed'): ?>
                                                        <a href="worker_dashboard.php?action=cancel&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" title="Cancel Appointment" style="padding: 0.25rem 0.4rem; font-size: 0.75rem;">
                                                            <i class="fas fa-times" style="font-size: 0.7rem;"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-danger disabled opacity-50" title="Cannot Cancel" disabled style="padding: 0.25rem 0.4rem; font-size: 0.75rem;">
                                                            <i class="fas fa-times" style="font-size: 0.7rem;"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div class="text-muted">
                                                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                                <h5>No appointments scheduled</h5>
                                                <p>There are currently no appointments in the system.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="reports.php" class="btn btn-primary">
                        <i class="fas fa-file-alt me-2"></i>View All Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Medicines Modal -->
    <div class="modal fade" id="medicinesModal" tabindex="-1" aria-labelledby="medicinesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="medicinesModalLabel">
                        <i class="fas fa-pills"></i> Medicine Inventory
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading medicines...</span>
                        </div>
                        <p class="mt-2">Loading medicine inventory...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                    <a href="medicines.php" class="btn btn-primary">
                        <i class="fas fa-pills me-2"></i>Go to Medicines
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Modal -->
    <div class="modal fade" id="reportsModal" tabindex="-1" aria-labelledby="reportsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportsModalLabel">
                        <i class="fas fa-chart-bar"></i> Health Reports
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading reports...</span>
                        </div>
                        <p class="mt-2">Loading health reports...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="reports.php" class="btn btn-primary">
                        <i class="fas fa-chart-bar me-2"></i>Go to Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">
                        <i class="fas fa-user-edit"></i> Edit Profile
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="profileModalBody">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading profile...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom spacing for better UX -->
    <div style="height: 60px;"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/profile_modal.js"></script>
    
    <script>
        // Password toggle function for profile modal
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input && icon) {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        }
    </script>
    
    <script>
        // Fix close button positioning
        function fixCloseButtonPosition() {
            const closeButtons = document.querySelectorAll('.btn-close');
            closeButtons.forEach(button => {
                const modal = button.closest('.modal-content');
                if (modal) {
                    const rect = modal.getBoundingClientRect();
                    button.style.position = 'absolute';
                    button.style.top = '15px';
                    button.style.right = '15px';
                    button.style.transform = 'none';
                    button.style.zIndex = '9999';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Fix close button positions on page load
            fixCloseButtonPosition();
            
            // Fix positions when modals are shown
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('shown.bs.modal', fixCloseButtonPosition);
            });
            // Enhanced button interactions
            const actionButtons = document.querySelectorAll('.btn-group .btn');
            
            actionButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Add loading state
                    this.classList.add('loading');
                    
                    // Add success animation for confirm and complete buttons
                    if (this.classList.contains('btn-success') || this.classList.contains('btn-primary')) {
                        setTimeout(() => {
                            this.classList.add('success-animation');
                        }, 100);
                    }
                });
            });
            
            // Initialize tooltips for better UX
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    placement: 'top',
                    trigger: 'hover'
                });
            });
            
            // Add confirmation dialogs for destructive actions
            const cancelButtons = document.querySelectorAll('.btn-danger');
            cancelButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    
                    if (confirm('Are you sure you want to cancel this appointment?')) {
                        window.location.href = href;
                    } else {
                        this.classList.remove('loading');
                    }
                });
            });
            
            // Add confirmation for complete action
            const completeButtons = document.querySelectorAll('.btn-primary[title*="Complete"]');
            completeButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    
                    if (confirm('Mark this appointment as completed?')) {
                        window.location.href = href;
                    } else {
                        this.classList.remove('loading');
                    }
                });
            });
        });

        // Load profile content when modal is shown
        document.getElementById('profileModal').addEventListener('show.bs.modal', function () {
            fetch('profile_modal.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('profileModalBody').innerHTML = data;
                    // Initialize profile forms after content is loaded
                    if (typeof initializeProfileForms === 'function') {
                        setTimeout(initializeProfileForms, 100);
                    }
                    // Initialize password toggles
                    if (typeof initializePasswordToggles === 'function') {
                        setTimeout(initializePasswordToggles, 100);
                    }
                })
                .catch(error => {
                    document.getElementById('profileModalBody').innerHTML = 
                        '<div class="alert alert-danger">Error loading profile. Please try again.</div>';
                });
        });

        // Load medicines content when modal is shown
        document.getElementById('medicinesModal').addEventListener('show.bs.modal', function () {
            const modalBody = this.querySelector('.modal-body');
            modalBody.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6 d-flex flex-column">
                        <h6 class="text-primary mb-3 d-flex align-items-center" style="height: 30px; min-height: 30px;"><i class="fas fa-exclamation-triangle me-2"></i>Low Stock Medicines</h6>
                        <div class="alert alert-warning flex-fill d-flex align-items-center" style="height: 70px; min-height: 70px;">
                            <i class="fas fa-info-circle me-2"></i> Medicines running low on stock need immediate attention.
                        </div>
                    </div>
                    <div class="col-md-6 d-flex flex-column">
                        <h6 class="text-danger mb-3 d-flex align-items-center" style="height: 30px; min-height: 30px;"><i class="fas fa-times-circle me-2"></i>Expired Medicines</h6>
                        <div class="alert alert-danger flex-fill d-flex align-items-center" style="height: 70px; min-height: 70px;">
                            <i class="fas fa-exclamation-circle me-2"></i> Expired medicines need immediate removal from inventory.
                        </div>
                    </div>
                </div>

            `;
        });

        // Load reports content when modal is shown
        document.getElementById('reportsModal').addEventListener('show.bs.modal', function () {
            const modalBody = this.querySelector('.modal-body');
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-4 mb-3 d-flex">
                        <div class="card text-center h-100 w-100" style="border: none;">
                            <div class="card-body d-flex flex-column">
                                <i class="fas fa-syringe fa-3x text-secondary mb-3"></i>
                                <h5 class="card-title">Vaccination Reports</h5>
                                <p class="card-text flex-fill">View vaccination statistics and coverage reports</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 d-flex">
                        <div class="card text-center h-100 w-100" style="border: none;">
                            <div class="card-body d-flex flex-column">
                                <i class="fas fa-pills fa-3x text-secondary mb-3"></i>
                                <h5 class="card-title">Medicine Reports</h5>
                                <p class="card-text flex-fill">Monitor medicine usage and inventory reports</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 d-flex">
                        <div class="card text-center h-100 w-100" style="border: none;">
                            <div class="card-body d-flex flex-column">
                                <i class="fas fa-calendar-check fa-3x text-secondary mb-3"></i>
                                <h5 class="card-title">Appointment Reports</h5>
                                <p class="card-text flex-fill">Track appointment statistics and trends</p>
                            </div>
                        </div>
                    </div>
                </div>

            `;
        });

        // Appointment filtering functions
        function applyFilters() {
            const search = document.getElementById('search').value;
            const filter = document.getElementById('filter').value;
            
            // Build URL with parameters
            let url = 'worker_dashboard.php?';
            const params = [];
            
            if (search) params.push('search=' + encodeURIComponent(search));
            if (filter) params.push('filter=' + encodeURIComponent(filter));
            
            if (params.length > 0) {
                url += params.join('&');
            }
            
            // Reload page with filters
            window.location.href = url;
        }

        function resetFilters() {
            window.location.href = 'worker_dashboard.php';
        }

        // Allow Enter key to trigger search
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilters();
            }
        });

        // ========================================
        // Daily Health Tip - API Integration
        // ========================================

        // API Configuration - Using PHP proxy to avoid CORS issues
        const API_URL = 'api/get_health_tip.php';

        /**
         * Fetch a random health tip from API
         * Uses PHP proxy to handle server-side API calls
         */
        async function fetchHealthTip() {
            const loadingState = document.getElementById('loadingState');
            const tipContent = document.getElementById('tipContent');
            const errorState = document.getElementById('errorState');
            const refreshBtn = document.getElementById('refreshTipBtn');

            // Show loading state
            loadingState.style.display = 'block';
            tipContent.style.display = 'none';
            errorState.style.display = 'none';
            refreshBtn.classList.add('spinning');

            try {
                const response = await fetch(API_URL, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.error) {
                    throw new Error(data.message || 'API error');
                }

                if (data && data.length > 0) {
                    const quote = data[0];
                    document.getElementById('tipQuote').textContent = quote.quote;
                    document.getElementById('tipAuthor').textContent = quote.author || 'Unknown';
                    loadingState.style.display = 'none';
                    tipContent.style.display = 'block';
                } else {
                    throw new Error('No health tip available');
                }

            } catch (error) {
                console.error('Error fetching health tip:', error);
                loadingState.style.display = 'none';
                errorState.style.display = 'block';
            } finally {
                refreshBtn.classList.remove('spinning');
            }
        }

        // Event listener for refresh button
        document.getElementById('refreshTipBtn').addEventListener('click', function() {
            fetchHealthTip();
        });

        // ========================================
        // Weather Section - OpenWeatherMap API Integration
        // ========================================

        /**
         * Weather API Configuration
         * Uses PHP proxy to avoid CORS issues and keep API key secure
         */
        const WEATHER_API_URL = 'api/get_weather.php?city=Manila,PH';

        /**
         * Fetch weather data from OpenWeatherMap API via PHP proxy
         */
        async function fetchWeatherData() {
            const weatherLoadingState = document.getElementById('weatherLoadingState');
            const weatherContent = document.getElementById('weatherContent');
            const weatherErrorState = document.getElementById('weatherErrorState');
            const refreshWeatherBtn = document.getElementById('refreshWeatherBtn');

            // Show loading state
            weatherLoadingState.style.display = 'block';
            weatherContent.style.display = 'none';
            weatherErrorState.style.display = 'none';
            refreshWeatherBtn.classList.add('spinning');

            try {
                const response = await fetch(WEATHER_API_URL, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                // Check for error in proxy response
                if (data.error) {
                    throw new Error(data.message || 'Weather API error');
                }

                // Check for API error response
                if (data.cod && data.cod !== 200) {
                    throw new Error(data.message || 'Weather API error');
                }

                if (data && data.main && data.weather && data.weather.length > 0) {
                    // Extract weather information
                    const cityName = data.name || 'Unknown';
                    const temperature = Math.round(data.main.temp);
                    const weatherCondition = data.weather[0].main;
                    const weatherDescription = data.weather[0].description;
                    const weatherIcon = data.weather[0].icon;
                    const humidity = data.main.humidity;
                    const windSpeed = data.wind.speed;

                    // Weather icon URL
                    const iconUrl = `https://openweathermap.org/img/wn/${weatherIcon}@2x.png`;

                    // Update DOM
                    document.getElementById('weatherCity').textContent = cityName;
                    document.getElementById('weatherTemp').textContent = `${temperature}°C`;
                    document.getElementById('weatherCondition').textContent = weatherCondition;
                    document.getElementById('weatherIcon').src = iconUrl;
                    document.getElementById('weatherIcon').alt = weatherDescription;
                    document.getElementById('weatherHumidity').textContent = `${humidity}%`;
                    document.getElementById('weatherWind').textContent = `${windSpeed} m/s`;

                    weatherLoadingState.style.display = 'none';
                    weatherContent.style.display = 'block';
                } else {
                    throw new Error('Invalid weather data received');
                }

            } catch (error) {
                console.error('Error fetching weather data:', error);
                console.error('Error details:', error.message);
                weatherLoadingState.style.display = 'none';
                weatherErrorState.style.display = 'block';
                
                const errorMsg = weatherErrorState.querySelector('p');
                if (errorMsg && error.message) {
                    errorMsg.textContent = `Unable to load weather. ${error.message}`;
                }
            } finally {
                refreshWeatherBtn.classList.remove('spinning');
            }
        }

        // Event listener for weather refresh button
        document.getElementById('refreshWeatherBtn').addEventListener('click', function() {
            fetchWeatherData();
        });

        // Initialize widgets when page loads
        fetchHealthTip();
        fetchWeatherData();
    </script>
</body>
</html>

