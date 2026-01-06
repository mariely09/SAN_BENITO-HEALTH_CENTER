<?php
require_once 'config/session.php';

// Redirect to landing page if not logged in
if (!isLoggedIn()) {
    header("Location: index.php");
    exit;
}

require_once 'config/database.php';
require_once 'config/functions.php';
requireApproved();

// Get total counts
$query = "SELECT 
            (SELECT COUNT(*) FROM medicines) as medicine_count,
            (SELECT COUNT(*) FROM medicines WHERE quantity <= low_stock_threshold) as low_stock_count,
            (SELECT COUNT(*) FROM medicines WHERE expiry_date < CURDATE()) as expired_count,
            (SELECT COUNT(*) FROM babies) as baby_count,
            (SELECT COUNT(*) FROM vaccinations) as vaccination_count,
            (SELECT COUNT(*) FROM vaccinations WHERE status = 'pending' AND schedule_date = CURDATE()) as today_vaccination_count,
            (SELECT COUNT(*) FROM vaccinations WHERE status = 'pending' AND schedule_date < CURDATE()) as overdue_vaccination_count,
            (SELECT COUNT(*) FROM appointments) as appointment_count,
            (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_users_count,
            (SELECT COUNT(*) FROM users) as total_users_count";
$result = mysqli_query($conn, $query);
$counts = mysqli_fetch_assoc($result);

// Check if archive tables exist and get counts
$archive_tables = [
    'archived_babies' => 'archived_babies_count',
    'archived_vaccinations' => 'archived_vaccinations_count',
    'archived_medicines' => 'archived_medicines_count',
    'archived_users' => 'archived_users_count',
    'archived_appointments' => 'archived_appointments_count'
];

foreach ($archive_tables as $table => $stat_key) {
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($table_check) > 0) {
        $archive_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM $table");
        if ($archive_result) {
            $counts[$stat_key] = mysqli_fetch_assoc($archive_result)['count'];
        } else {
            $counts[$stat_key] = 0;
        }
    } else {
        $counts[$stat_key] = 0;
    }
}

// Get recent vaccinations
$query = "SELECT v.*, b.full_name 
          FROM vaccinations v 
          JOIN babies b ON v.baby_id = b.id
          ORDER BY 
            CASE WHEN v.status = 'pending' AND v.schedule_date = CURDATE() THEN 0
                 WHEN v.status = 'pending' AND v.schedule_date < CURDATE() THEN 1
                 WHEN v.status = 'pending' THEN 2
                 ELSE 3 END,
            v.schedule_date ASC
          LIMIT 5";
$vaccinations_result = mysqli_query($conn, $query);

// Get low stock medicines
$query = "SELECT * FROM medicines WHERE quantity <= low_stock_threshold ORDER BY quantity ASC LIMIT 5";
$low_stock_result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Navbar Styles -->
    <link rel="stylesheet" href="assets/css/navbar.css">
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/residents_dashboard.css?v=<?php echo time(); ?>">
    <!-- Admin Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/admin_dashboard.css?v=<?php echo time(); ?>">
    <!-- Responsive Styles -->
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?php echo time(); ?>">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
         body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%) !important;
            min-height: 100vh !important;
        }

         .welcome-title .fas.fa-shield-alt {
            color: #28a745;
        }
        
        /* Mobile responsiveness improvements */
        @media (max-width: 768px) {
            .welcome-subtitle {
                display: none !important;
            }
            
            .welcome-title {
                font-size: 1.3rem !important;
                line-height: 1.2 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            
            .welcome-title i {
                font-size: 1.2rem !important;
                margin-right: 0.5rem !important;
            }
            
            /* Section titles - keep icon and text on same line */
            .section-title {
                font-size: 1.15rem !important;
                margin-bottom: 0.75rem !important;
                line-height: 1.1 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                display: flex !important;
                align-items: center !important;
            }
            
            .section-title i {
                font-size: 1.1rem !important;
                margin-right: 0.6rem !important;
                flex-shrink: 0 !important;
            }
            
            /* Quick Actions - 2 columns on mobile - Match worker dashboard */
            .quick-actions .row .col-xl-2 {
                flex: 0 0 50% !important;
                max-width: 50% !important;
            }
            
            .admin-card {
                min-height: 220px !important;
            }
            
            .admin-card .card-body {
                padding: 1.75rem 1.25rem !important;
            }
            
            .admin-card .action-icon {
                width: 40px !important;
                height: 40px !important;
                margin-bottom: 0.75rem !important;
            }
            
            .admin-card .card-title {
                font-size: 1rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            .admin-card .card-text {
                display: block !important;
                font-size: 0.85rem !important;
                margin-bottom: 0.75rem !important;
                line-height: 1.3 !important;
            }
            
            .admin-card .btn {
                padding: 0.4rem 0.8rem !important;
                font-size: 0.75rem !important;
            }
            
            /* System Statistics - 2 columns on mobile */
            .statistics-section .row .col-xl-3 {
                flex: 0 0 50% !important;
                max-width: 50% !important;
            }
            
            .admin-stats-card .card-body {
                padding: 0.75rem 0.5rem !important;
            }
            
            .admin-stats-icon {
                width: 35px !important;
                height: 35px !important;
                margin-bottom: 0.5rem !important;
            }
            
            .admin-stats-number {
                font-size: 1.2rem !important;
                margin-bottom: 0.25rem !important;
            }
            
            .admin-stats-label {
                font-size: 0.7rem !important;
                line-height: 1.1 !important;
            }
        }
        
        @media (max-width: 576px) {
            .welcome-title {
                font-size: 1.1rem !important;
            }
            
            .welcome-title i {
                font-size: 1rem !important;
                margin-right: 0.4rem !important;
            }
            
            .section-title {
                font-size: 1rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            .section-title i {
                font-size: 0.95rem !important;
                margin-right: 0.5rem !important;
            }
            
            /* Even more compact on small screens - Match worker dashboard */
            .admin-card {
                min-height: 180px !important;
                margin-bottom: 1rem !important;
            }
            
            .admin-card .card-body {
                padding: 1.25rem 0.75rem !important;
            }
            
            .admin-card .action-icon {
                width: 35px !important;
                height: 35px !important;
                margin-bottom: 0.75rem !important;
            }
            
            .admin-card .card-title {
                font-size: 0.9rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            .admin-card .card-text {
                font-size: 0.75rem !important;
                margin-bottom: 0.75rem !important;
                line-height: 1.3 !important;
            }
            
            .admin-card .btn {
                padding: 0.4rem 0.8rem !important;
                font-size: 0.75rem !important;
            }
            
            .admin-stats-icon {
                width: 50px !important;
                height: 50px !important;
            }
            
            .admin-stats-number {
                font-size: 1.1rem !important;
            }
            
            .admin-stats-label {
                font-size: 0.8rem !important;
            }
        }
        
        /* Data table card bodies white background */
        .card .card-body {
            background-color: white !important;
        }
        
        /* Ensure white background for all table elements */
        .card .card-body .table-responsive,
        .card .card-body .table,
        .card .card-body .table thead,
        .card .card-body .table thead tr,
        .card .card-body .table thead tr th,
        .card .card-body .table tbody,
        .card .card-body .table tbody tr,
        .card .card-body .table tbody tr td {
            background-color: white !important;
        }
        
        /* Table text styling to match medicines.php */
        .card .card-body .table thead th {
            color: #495057 !important;
            font-weight: 600 !important;
        }
        
        .card .card-body .table tbody td {
            color: #212529 !important;
        }
        
        /* Ensure white background on mobile for data tables */
        @media (max-width: 768px) {
            .card .card-body {
                background-color: white !important;
            }
            
            .card .card-body .table-responsive,
            .card .card-body .table,
            .card .card-body .table thead,
            .card .card-body .table thead tr,
            .card .card-body .table thead tr th,
            .card .card-body .table tbody,
            .card .card-body .table tbody tr,
            .card .card-body .table tbody tr td {
                background-color: white !important;
            }
            
            /* Table text styling for 768px mobile */
            .card .card-body .table thead th {
                color: #495057 !important;
                font-weight: 600 !important;
            }
            
            .card .card-body .table tbody td {
                color: #212529 !important;
            }
        }
        
        @media (max-width: 576px) {
            .card .card-body {
                background-color: white !important;
            }
            
            .card .card-body .table-responsive,
            .card .card-body .table,
            .card .card-body .table thead,
            .card .card-body .table thead tr,
            .card .card-body .table thead tr th,
            .card .card-body .table tbody,
            .card .card-body .table tbody tr,
            .card .card-body .table tbody tr td {
                background-color: white !important;
            }
            
            /* Table text styling for 576px mobile */
            .card .card-body .table thead th {
                color: #495057 !important;
                font-weight: 600 !important;
            }
            
            .card .card-body .table tbody td {
                color: #212529 !important;
            }
        }
        
        @media (max-width: 480px) {
            .card .card-body {
                background-color: white !important;
            }
            
            .card .card-body .table-responsive,
            .card .card-body .table,
            .card .card-body .table thead,
            .card .card-body .table thead tr,
            .card .card-body .table thead tr th,
            .card .card-body .table tbody,
            .card .card-body .table tbody tr,
            .card .card-body .table tbody tr td {
                background-color: white !important;
            }
            
            /* Table text styling for 480px mobile */
            .card .card-body .table thead th {
                color: #495057 !important;
                font-weight: 600 !important;
            }
            
            .card .card-body .table tbody td {
                color: #212529 !important;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="admin-welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">
                        <i class="fas fa-shield-alt me-2"></i>
                        Admin Dashboard
                    </h1>
                    <p class="welcome-subtitle">Comprehensive system administration and health center management
                        overview.</p>
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
        <section class="quick-actions mb-4">
            <h2 class="section-title">
                <i class="fas fa-tools me-2"></i>Quick Actions
            </h2>
            <!-- Responsive Grid - Equal Height Cards - 6 cards per row on desktop -->
            <div class="row g-3">
                <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="card action-card schedule admin-card h-100">
                        <div class="card-body text-center py-3 d-flex flex-column">
                            <div class="action-icon mb-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h5 class="card-title mb-2">Appointments</h5>
                            <p class="card-text mb-3 flex-grow-1 small">View and manage all scheduled appointments from residents.</p>
                            <a href="appointments.php" class="btn action-btn btn-schedule btn-sm mt-auto" title="View Appointments">
                                <i class="fas fa-calendar-check me-1"></i> Appointments
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="card action-card profile admin-card h-100">
                        <div class="card-body text-center py-3 d-flex flex-column">
                            <div class="action-icon mb-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-pills"></i>
                            </div>
                            <h5 class="card-title mb-2">Medicines</h5>
                            <p class="card-text mb-3 flex-grow-1 small">Monitor medicine stock levels and manage inventory.</p>
                            <a href="medicines.php" class="btn action-btn btn-profile btn-sm mt-auto" title="Manage Medicines">
                                <i class="fas fa-pills me-1"></i> Medicines
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="card action-card appointments admin-card h-100">
                        <div class="card-body text-center py-3 d-flex flex-column">
                            <div class="action-icon mb-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-syringe"></i>
                            </div>
                            <h5 class="card-title mb-2">Vaccinations</h5>
                            <p class="card-text mb-3 flex-grow-1 small">Manage vaccination schedules and immunization records.</p>
                            <a href="vaccinations.php" class="btn action-btn btn-appointments btn-sm mt-auto" title="View Vaccinations">
                                <i class="fas fa-syringe me-1"></i> Vaccinations
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="card action-card babies admin-card h-100">
                        <div class="card-body text-center py-3 d-flex flex-column">
                            <div class="action-icon mb-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-baby"></i>
                            </div>
                            <h5 class="card-title mb-2">Baby Records</h5>
                            <p class="card-text mb-3 flex-grow-1 small">Manage baby information and vaccination records.</p>
                            <a href="babies.php" class="btn action-btn btn-babies btn-sm mt-auto" title="Manage Babies">
                                <i class="fas fa-baby me-1"></i> Babies
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="card action-card residents admin-card h-100">
                        <div class="card-body text-center py-3 d-flex flex-column">
                            <div class="action-icon mb-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-users"></i>
                            </div>
                            <h5 class="card-title mb-2">Residents</h5>
                            <p class="card-text mb-3 flex-grow-1 small">Manage resident information, demographics, and health records.</p>
                            <a href="residents.php" class="btn action-btn btn-residents btn-sm mt-auto" title="View Residents">
                                <i class="fas fa-users me-1"></i> Residents
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 col-12">
                    <div class="card action-card users admin-card h-100">
                        <div class="card-body text-center py-3 d-flex flex-column">
                            <div class="action-icon mb-3" style="width: 50px; height: 50px;">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            <h5 class="card-title mb-2">Users</h5>
                            <p class="card-text mb-3 flex-grow-1 small">Manage system users and their permissions.</p>
                            <a href="users.php" class="btn action-btn btn-users btn-sm mt-auto" title="Manage Users">
                                <i class="fas fa-user-cog me-1"></i> Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Statistics Overview -->
        <section class="statistics-section mb-4">
            <h2 class="section-title">
                <i class="fas fa-chart-line me-2"></i>System Statistics Overview
            </h2>
            <div class="row g-3">
                <!-- Medicine Inventory Issues (Critical Priority) -->
                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                    <div class="card admin-stats-card h-100">
                        <div class="card-body text-center">
                            <div class="admin-stats-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h3 class="admin-stats-number"><?php echo $counts['low_stock_count']; ?></h3>
                            <p class="admin-stats-label">Low Stock Medicines</p>
                        </div>
                    </div>
                </div>
                
                <!-- Administrative Tasks -->
                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                    <div class="card admin-stats-card position-relative h-100">
                        <?php if ($counts['pending_users_count'] > 0): ?>
                            <div class="notification-dot"></div>
                        <?php endif; ?>
                        <div class="card-body text-center">
                            <div class="admin-stats-icon">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <h3 class="admin-stats-number"><?php echo $counts['pending_users_count']; ?></h3>
                            <p class="admin-stats-label">Pending Users</p>
                        </div>
                    </div>
                </div>
                
                <!-- Health Services Statistics -->
                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                    <div class="card admin-stats-card h-100">
                        <div class="card-body text-center">
                            <div class="admin-stats-icon">
                                <i class="fas fa-syringe"></i>
                            </div>
                            <h3 class="admin-stats-number"><?php echo $counts['vaccination_count']; ?></h3>
                            <p class="admin-stats-label">Total Vaccinations</p>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                    <div class="card admin-stats-card h-100">
                        <div class="card-body text-center">
                            <div class="admin-stats-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h3 class="admin-stats-number"><?php echo $counts['appointment_count']; ?></h3>
                            <p class="admin-stats-label">Total Appointments</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        
        <!-- Data Tables -->
        <div class="row g-3">
            <!-- Recent Vaccinations -->
            <div class="col-xl-6 col-lg-12 col-md-12 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header"
                        style="background: linear-gradient(135deg, #2c3e50, #34495e); color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-syringe me-2"></i>Recent Vaccination Schedules
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($vaccinations_result) > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Baby</th>
                                            <th>Vaccine</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($vaccination = mysqli_fetch_assoc($vaccinations_result)): ?>
                                            <?php
                                            $status = '';
                                            $statusClass = '';

                                            if ($vaccination['status'] == 'completed') {
                                                $status = 'Completed';
                                                $statusClass = 'bg-success';
                                            } elseif ($vaccination['status'] == 'confirmed') {
                                                $status = 'Confirmed';
                                                $statusClass = 'bg-info';
                                            } elseif ($vaccination['status'] == 'cancelled') {
                                                $status = 'Cancelled';
                                                $statusClass = 'bg-danger';
                                            } else {
                                                // Pending status
                                                if (strtotime($vaccination['schedule_date']) < strtotime('today')) {
                                                    $status = 'Overdue';
                                                    $statusClass = 'bg-danger';
                                                } elseif (strtotime($vaccination['schedule_date']) == strtotime('today')) {
                                                    $status = 'Today';
                                                    $statusClass = 'bg-primary';
                                                } else {
                                                    $status = 'Pending';
                                                    $statusClass = 'bg-warning text-dark';
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($vaccination['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($vaccination['vaccine_type']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($vaccination['schedule_date'])); ?></td>
                                                <td><span
                                                        class="badge <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="vaccinations.php" class="btn btn-primary">
                                    <i class="fas fa-eye me-2"></i>View All Vaccinations
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-syringe fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Vaccination Schedules</h5>
                                <p class="text-muted">No vaccination schedules found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Low Stock Medicines -->
            <div class="col-xl-6 col-lg-12 col-md-12 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header"
                        style="background: linear-gradient(135deg, #2c3e50, #34495e); color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-pills me-2"></i>Low Stock Medicines
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (mysqli_num_rows($low_stock_result) > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Medicine</th>
                                            <th>Current Stock</th>
                                            <th>Threshold</th>
                                            <th>Expiry</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($medicine = mysqli_fetch_assoc($low_stock_result)): ?>
                                            <?php
                                            $expired = strtotime($medicine['expiry_date']) < strtotime(date('Y-m-d'));
                                            $expiringSoon = !$expired && strtotime($medicine['expiry_date']) <= strtotime('+30 days');

                                            $rowClass = $expired ? 'table-danger' : ($expiringSoon ? 'table-warning' : '');
                                            ?>
                                            <tr class="<?php echo $rowClass; ?>">
                                                <td><?php echo htmlspecialchars($medicine['medicine_name']); ?></td>
                                                <td><?php echo $medicine['quantity']; ?></td>
                                                <td><?php echo $medicine['low_stock_threshold']; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($medicine['expiry_date'])); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="medicines.php?filter=low_stock" class="btn btn-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>View All Low Stock
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-pills fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Low Stock Medicines</h5>
                                <p class="text-muted">All medicines are adequately stocked.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom spacing for better UX -->
        <div style="height: 60px;"></div>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <!-- Profile Modal JS -->
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

            // Initialize - Fetch data when page loads
            document.addEventListener('DOMContentLoaded', function() {
                fetchHealthTip();
                fetchWeatherData();
            });
        </script>
</body>

</html>