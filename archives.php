<?php
// Prevent browser caching - force fresh page load
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Delete record from archive permanently
if (isset($_GET['delete_id'])) {
    require_once 'config/database.php';
    require_once 'config/session.php';
    require_once 'config/functions.php';
    requireAdmin();

    $id = (int) $_GET['delete_id'];
    $type = $_GET['type'] ?? 'babies';

    try {
        $table_map = [
            'babies' => 'archived_babies',
            'vaccinations' => 'archived_vaccinations',
            'medicines' => 'archived_medicines',
            'users' => 'archived_users',
            'appointments' => 'archived_appointments',
            'residents' => 'archived_residents'
        ];

        $table = $table_map[$type] ?? 'archived_babies';
        $delete_query = "DELETE FROM $table WHERE id = $id";
        
        if (mysqli_query($conn, $delete_query)) {
            header("Location: archives.php?type=$type&success=" . ucfirst($type) . " record deleted permanently");
        } else {
            throw new Exception("Failed to delete record");
        }
        exit;
    } catch (Exception $e) {
        header("Location: archives.php?type=" . $type . "&error=" . urlencode($e->getMessage()));
        exit;
    }
}

// Restore record from archive
if (isset($_GET['restore_id'])) {
    require_once 'config/database.php';
    require_once 'config/session.php';
    require_once 'config/functions.php';
    require_once 'config/archive_functions.php';
    requireAdmin();

    $id = (int) $_GET['restore_id'];
    $type = $_GET['type'] ?? 'babies';
    
    // Get the referring page to redirect back after restore
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $redirect_url = "archives.php?type=" . $type;
    
    // If referer exists and is from our domain, use it
    if (!empty($referer) && strpos($referer, $_SERVER['HTTP_HOST']) !== false) {
        // Parse the referer URL to get the page
        $referer_parts = parse_url($referer);
        $referer_page = basename($referer_parts['path']);
        
        // If coming from a different page (not archives.php), redirect back there
        if ($referer_page !== 'archives.php') {
            $redirect_url = $referer_page;
            if (!empty($referer_parts['query'])) {
                $redirect_url .= '?' . $referer_parts['query'];
            }
        }
    }

    try {
        switch ($type) {
            case 'babies':
                restoreBaby($id);
                $success_msg = "Baby record restored successfully";
                break;
            case 'vaccinations':
                restoreVaccination($id);
                $success_msg = "Vaccination record restored successfully";
                break;
            case 'medicines':
                restoreMedicine($id);
                $success_msg = "Medicine record restored successfully";
                break;
            case 'users':
                restoreUser($id);
                $success_msg = "User record restored successfully";
                break;
            case 'appointments':
                restoreAppointment($id);
                $success_msg = "Appointment record restored successfully";
                break;
            case 'residents':
                restoreResident($id);
                $success_msg = "Resident record restored successfully";
                break;
            default:
                throw new Exception("Invalid archive type");
        }
        
        // Add success message and cache buster to redirect URL
        $separator = strpos($redirect_url, '?') !== false ? '&' : '?';
        $cache_buster = time();
        header("Location: " . $redirect_url . $separator . "success=" . urlencode($success_msg) . "&_=" . $cache_buster);
        exit;
    } catch (Exception $e) {
        $separator = strpos($redirect_url, '?') !== false ? '&' : '?';
        $cache_buster = time();
        header("Location: " . $redirect_url . $separator . "error=" . urlencode($e->getMessage()) . "&_=" . $cache_buster);
        exit;
    }
}

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';
requireApproved();

// Create archive tables if they don't exist
function createArchiveTablesIfNotExist($conn)
{
    // Check and create archived_babies table
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_babies'");
    if (mysqli_num_rows($table_check) == 0) {
        $create_babies_table = "
            CREATE TABLE archived_babies (
                id INT PRIMARY KEY AUTO_INCREMENT,
                original_id INT NOT NULL,
                full_name VARCHAR(255) NOT NULL,
                date_of_birth DATE NOT NULL,
                parent_guardian_name VARCHAR(255) NOT NULL,
                contact_number VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_by INT,
                FOREIGN KEY (archived_by) REFERENCES users(id)
            )";
        mysqli_query($conn, $create_babies_table);
    }

    // Check and create archived_vaccinations table
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_vaccinations'");
    if (mysqli_num_rows($table_check) == 0) {
        $create_vaccinations_table = "
            CREATE TABLE archived_vaccinations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                original_id INT NOT NULL,
                baby_id INT NOT NULL,
                vaccine_type VARCHAR(50) NOT NULL,
                schedule_date DATE NOT NULL,
                status VARCHAR(20) NOT NULL,
                notes TEXT,
                administered_by INT,
                administered_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_by INT,
                FOREIGN KEY (archived_by) REFERENCES users(id)
            )";
        mysqli_query($conn, $create_vaccinations_table);
    }

    // Check and create archived_medicines table
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_medicines'");
    if (mysqli_num_rows($table_check) == 0) {
        $create_medicines_table = "
            CREATE TABLE archived_medicines (
                id INT PRIMARY KEY AUTO_INCREMENT,
                original_id INT NOT NULL,
                medicine_name VARCHAR(255) NOT NULL,
                dosage VARCHAR(100) DEFAULT NULL,
                quantity INT NOT NULL,
                expiry_date DATE NOT NULL,
                batch_number VARCHAR(100) NOT NULL,
                low_stock_threshold INT DEFAULT 10,
                date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archive_reason VARCHAR(255) DEFAULT 'Archived',
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_by INT,
                FOREIGN KEY (archived_by) REFERENCES users(id)
            )";
        mysqli_query($conn, $create_medicines_table);
    }

    // Check and create archived_users table
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_users'");
    if (mysqli_num_rows($table_check) == 0) {
        $create_users_table = "
            CREATE TABLE archived_users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                original_id INT NOT NULL,
                username VARCHAR(50) NOT NULL,
                fullname VARCHAR(255) NOT NULL,
                email VARCHAR(255) DEFAULT NULL,
                contact_number VARCHAR(20) DEFAULT NULL,
                role ENUM('admin', 'worker', 'resident') NOT NULL,
                status VARCHAR(20) DEFAULT 'approved',
                departure_date DATE NULL,
                archive_reason VARCHAR(255) DEFAULT 'Archived',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_by INT,
                FOREIGN KEY (archived_by) REFERENCES users(id)
            )";
        mysqli_query($conn, $create_users_table);
    }

    // Check and create archived_appointments table
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_appointments'");
    if (mysqli_num_rows($table_check) == 0) {
        $create_appointments_table = "
            CREATE TABLE archived_appointments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                original_id INT NOT NULL,
                fullname VARCHAR(255) NOT NULL,
                appointment_type VARCHAR(100) NOT NULL,
                preferred_datetime DATETIME NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_by INT,
                FOREIGN KEY (archived_by) REFERENCES users(id)
            )";
        mysqli_query($conn, $create_appointments_table);
    }

    // Check and create archived_residents table
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_residents'");
    if (mysqli_num_rows($table_check) == 0) {
        $create_residents_table = "
            CREATE TABLE archived_residents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                original_id INT NOT NULL,
                first_name VARCHAR(255) NOT NULL,
                last_name VARCHAR(255) NOT NULL,
                middle_name VARCHAR(255),
                age INT NOT NULL,
                gender ENUM('Male', 'Female') NOT NULL,
                birthday DATE NOT NULL,
                purok VARCHAR(100) NOT NULL,
                occupation VARCHAR(255),
                education VARCHAR(255),
                is_senior TINYINT(1) DEFAULT 0,
                is_pwd TINYINT(1) DEFAULT 0,
                family_planning VARCHAR(10) DEFAULT 'No',
                has_electricity TINYINT(1) DEFAULT 0,
                has_poso TINYINT(1) DEFAULT 0,
                has_nawasa TINYINT(1) DEFAULT 0,
                has_cr TINYINT(1) DEFAULT 0,
                archive_reason VARCHAR(255) DEFAULT 'Archived',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_by INT,
                FOREIGN KEY (archived_by) REFERENCES users(id)
            )";
        mysqli_query($conn, $create_residents_table);
    }
}

// Auto-create tables if needed
if (isset($_GET['create_tables']) && $_GET['create_tables'] == '1') {
    createArchiveTablesIfNotExist($conn);
    header("Location: archives.php?success=Archive tables created successfully");
    exit;
}

// Get archive type
$archive_type = $_GET['type'] ?? 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sanitize search input
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
}

// Build query based on archive type - check if tables exist first
$result = false;
$all_results = []; // For storing results when showing all types

switch ($archive_type) {
    case 'all':
        // Fetch all archive types
        $archive_types_to_fetch = ['babies', 'vaccinations', 'medicines', 'users', 'appointments', 'residents'];
        foreach ($archive_types_to_fetch as $type) {
            $table_name = 'archived_' . $type;
            $table_check = mysqli_query($conn, "SHOW TABLES LIKE '$table_name'");
            if (mysqli_num_rows($table_check) > 0) {
                $type_result = null;
                switch ($type) {
                    case 'babies':
                        $where_clause = !empty($search) ? "WHERE (ab.full_name LIKE '%$search%' OR ab.parent_guardian_name LIKE '%$search%')" : "";
                        $query = "SELECT ab.*, u.fullname as archived_by_name, 'babies' as record_type 
                                  FROM archived_babies ab 
                                  LEFT JOIN users u ON ab.archived_by = u.id 
                                  $where_clause 
                                  ORDER BY ab.archived_at DESC";
                        $type_result = mysqli_query($conn, $query);
                        break;
                    case 'vaccinations':
                        $where_clause = !empty($search) ? "WHERE (COALESCE(b.full_name, ab.full_name) LIKE '%$search%' OR av.vaccine_type LIKE '%$search%')" : "";
                        $query = "SELECT av.*, 
                                  COALESCE(b.full_name, ab.full_name) as baby_name, 
                                  u.fullname as archived_by_name, 
                                  'vaccinations' as record_type,
                                  COALESCE(av.administered_date, av.schedule_date) as display_date
                                  FROM archived_vaccinations av 
                                  LEFT JOIN babies b ON av.baby_id = b.id 
                                  LEFT JOIN archived_babies ab ON av.baby_id = ab.original_id 
                                  LEFT JOIN users u ON av.archived_by = u.id 
                                  $where_clause 
                                  ORDER BY av.archived_at DESC";
                        $type_result = mysqli_query($conn, $query);
                        break;
                    case 'medicines':
                        $where_clause = !empty($search) ? "WHERE (am.medicine_name LIKE '%$search%' OR am.batch_number LIKE '%$search%')" : "";
                        $query = "SELECT am.*, u.fullname as archived_by_name, 'medicines' as record_type 
                                  FROM archived_medicines am 
                                  LEFT JOIN users u ON am.archived_by = u.id 
                                  $where_clause 
                                  ORDER BY am.archived_at DESC";
                        $type_result = mysqli_query($conn, $query);
                        break;
                    case 'users':
                        $where_clause = !empty($search) ? "WHERE (au.fullname LIKE '%$search%' OR au.username LIKE '%$search%')" : "";
                        $query = "SELECT au.*, u.fullname as archived_by_name, 'users' as record_type 
                                  FROM archived_users au 
                                  LEFT JOIN users u ON au.archived_by = u.id 
                                  $where_clause 
                                  ORDER BY au.archived_at DESC";
                        $type_result = mysqli_query($conn, $query);
                        break;
                    case 'appointments':
                        $where_clause = !empty($search) ? "WHERE (aa.fullname LIKE '%$search%' OR aa.appointment_type LIKE '%$search%')" : "";
                        $query = "SELECT aa.*, u.fullname as archived_by_name, 'appointments' as record_type 
                                  FROM archived_appointments aa 
                                  LEFT JOIN users u ON aa.archived_by = u.id 
                                  $where_clause 
                                  ORDER BY aa.archived_at DESC";
                        $type_result = mysqli_query($conn, $query);
                        break;
                    case 'residents':
                        $where_clause = !empty($search) ? "WHERE (ar.first_name LIKE '%$search%' OR ar.last_name LIKE '%$search%' OR ar.middle_name LIKE '%$search%' OR ar.purok LIKE '%$search%')" : "";
                        $query = "SELECT ar.*, u.fullname as archived_by_name, 'residents' as record_type 
                                  FROM archived_residents ar 
                                  LEFT JOIN users u ON ar.archived_by = u.id 
                                  $where_clause 
                                  ORDER BY ar.archived_at DESC";
                        $type_result = mysqli_query($conn, $query);
                        break;
                }
                if ($type_result && mysqli_num_rows($type_result) > 0) {
                    $all_results[$type] = $type_result;
                }
            }
        }
        break;
    case 'babies':
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_babies'");
        if (mysqli_num_rows($table_check) > 0) {
            $where_clause = "";
            if (!empty($search)) {
                $where_clause = "WHERE (ab.full_name LIKE '%$search%' OR ab.parent_guardian_name LIKE '%$search%')";
            }
            $query = "SELECT ab.*, u.fullname as archived_by_name 
                      FROM archived_babies ab 
                      LEFT JOIN users u ON ab.archived_by = u.id 
                      $where_clause 
                      ORDER BY ab.archived_at DESC";
            $result = mysqli_query($conn, $query);
            
            // Check for query errors
            if (!$result) {
                error_log("Archives query error for babies: " . mysqli_error($conn));
                $result = false;
            }
        }
        break;
    case 'vaccinations':
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_vaccinations'");
        if (mysqli_num_rows($table_check) > 0) {
            $where_clause = "";
            if (!empty($search)) {
                $where_clause = "WHERE (COALESCE(b.full_name, ab.full_name) LIKE '%$search%' OR av.vaccine_type LIKE '%$search%')";
            }
            $query = "SELECT av.*, 
                      COALESCE(b.full_name, ab.full_name) as baby_name,
                      u.fullname as archived_by_name,
                      COALESCE(av.administered_date, av.schedule_date) as display_date
                      FROM archived_vaccinations av 
                      LEFT JOIN babies b ON av.baby_id = b.id 
                      LEFT JOIN archived_babies ab ON av.baby_id = ab.original_id 
                      LEFT JOIN users u ON av.archived_by = u.id 
                      $where_clause 
                      ORDER BY av.archived_at DESC";
            $result = mysqli_query($conn, $query);
            
            // Check for query errors
            if (!$result) {
                error_log("Archives query error for vaccinations: " . mysqli_error($conn));
                $result = false;
            }
        }
        break;
    case 'medicines':
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_medicines'");
        if (mysqli_num_rows($table_check) > 0) {
            $where_clause = "";
            if (!empty($search)) {
                $where_clause = "WHERE (am.medicine_name LIKE '%$search%' OR am.batch_number LIKE '%$search%')";
            }
            $query = "SELECT am.*, u.fullname as archived_by_name 
                      FROM archived_medicines am 
                      LEFT JOIN users u ON am.archived_by = u.id 
                      $where_clause 
                      ORDER BY am.archived_at DESC";
            $result = mysqli_query($conn, $query);
            
            // Check for query errors
            if (!$result) {
                error_log("Archives query error for medicines: " . mysqli_error($conn));
                $result = false;
            }
        }
        break;
    case 'users':
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_users'");
        if (mysqli_num_rows($table_check) > 0) {
            $where_clause = "";
            if (!empty($search)) {
                $where_clause = "WHERE (au.fullname LIKE '%$search%' OR au.username LIKE '%$search%')";
            }
            $query = "SELECT au.*, u.fullname as archived_by_name 
                      FROM archived_users au 
                      LEFT JOIN users u ON au.archived_by = u.id 
                      $where_clause 
                      ORDER BY au.archived_at DESC";
            $result = mysqli_query($conn, $query);
            
            // Check for query errors
            if (!$result) {
                error_log("Archives query error for users: " . mysqli_error($conn));
                $result = false;
            }
        }
        break;
    case 'appointments':
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_appointments'");
        if (mysqli_num_rows($table_check) > 0) {
            $where_clause = "";
            if (!empty($search)) {
                $where_clause = "WHERE (aa.fullname LIKE '%$search%' OR aa.appointment_type LIKE '%$search%')";
            }
            $query = "SELECT aa.*, u.fullname as archived_by_name 
                      FROM archived_appointments aa 
                      LEFT JOIN users u ON aa.archived_by = u.id 
                      $where_clause 
                      ORDER BY aa.archived_at DESC";
            $result = mysqli_query($conn, $query);
            
            // Check for query errors
            if (!$result) {
                error_log("Archives query error for appointments: " . mysqli_error($conn));
                $result = false;
            }
        }
        break;
    case 'residents':
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_residents'");
        if (mysqli_num_rows($table_check) > 0) {
            $where_clause = "";
            if (!empty($search)) {
                $where_clause = "WHERE (ar.first_name LIKE '%$search%' OR ar.last_name LIKE '%$search%' OR ar.middle_name LIKE '%$search%' OR ar.purok LIKE '%$search%')";
            }
            $query = "SELECT ar.*, u.fullname as archived_by_name 
                      FROM archived_residents ar 
                      LEFT JOIN users u ON ar.archived_by = u.id 
                      $where_clause 
                      ORDER BY ar.archived_at DESC";
            $result = mysqli_query($conn, $query);
            
            // Check for query errors
            if (!$result) {
                error_log("Archives query error for residents: " . mysqli_error($conn));
                $result = false;
            }
        }
        break;
    default:
        // Handle invalid archive type
        $result = false;
        break;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archives</title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Navbar Styles -->
    <link rel="stylesheet" href="assets/css/navbar.css">
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/residents_dashboard.css">
    <!-- Archives Styles -->
    <link rel="stylesheet" href="assets/css/archives.css?v=<?php echo time(); ?>">
    <!-- Success/Error Messages Styles -->
    <link rel="stylesheet" href="assets/css/success-error_messages.css">
     <style>
         body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%) !important;
            min-height: 100vh !important;
        }

          .welcome-title .fas.fa-archive {
            color: #28a745;
        }
        
        /* Hover effect for archive type cards */
        .hover-shadow {
            transition: all 0.3s ease;
        }
        
        .hover-shadow:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .welcome-subtitle {
                display: none !important;
            }
            
            .card-header h5 {
                display: flex !important;
                align-items: center !important;
                white-space: nowrap !important;
            }
            
            .card-header h5 i {
                margin-right: 0.5rem !important;
                flex-shrink: 0 !important;
            }
            
            /* Table headers on one line */
            .table thead th {
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Archive Header -->
        <div class="archive-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">
                        <i class="fas fa-archive me-2"></i>
                        Archive Management
                    </h1>
                    <p class="welcome-subtitle">View and restore archived records from the system.</p>
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

        <!-- Success/Error Modal -->
        <?php if (isset($_GET['success'])): ?>
            <div class="message-modal show message-modal-success" id="messageModal">
                <div class="message-modal-content">
                    <div class="message-modal-header">
                        <button class="message-modal-close" onclick="closeModal()">&times;</button>
                        <div class="message-modal-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <h3 class="message-modal-title">Success!</h3>
                    </div>
                    <div class="message-modal-body">
                        <p class="message-modal-message"><?php echo htmlspecialchars($_GET['success']); ?></p>
                        <div class="message-modal-actions">
                            <button class="message-modal-btn message-modal-btn-primary" onclick="closeModal()">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="message-modal show message-modal-error" id="messageModal">
                <div class="message-modal-content">
                    <div class="message-modal-header">
                        <button class="message-modal-close" onclick="closeModal()">&times;</button>
                        <div class="message-modal-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="message-modal-title">Error!</h3>
                    </div>
                    <div class="message-modal-body">
                        <p class="message-modal-message"><?php echo htmlspecialchars($_GET['error']); ?></p>
                        <div class="message-modal-actions">
                            <button class="message-modal-btn message-modal-btn-primary" onclick="closeModal()">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // Define titles for archive types
        $titles = [
            'babies' => 'Patient Records',
            'vaccinations' => 'Vaccination Records',
            'medicines' => 'Medicine Records',
            'users' => 'User Records',
            'appointments' => 'Appointment Records',
            'residents' => 'Resident Records'
        ];
        
        // Check if archive tables exist for current type
        $archive_tables = [
            'babies' => 'archived_babies',
            'vaccinations' => 'archived_vaccinations', 
            'medicines' => 'archived_medicines',
            'users' => 'archived_users',
            'appointments' => 'archived_appointments',
            'residents' => 'archived_residents'
        ];
        
        $current_table = $archive_tables[$archive_type] ?? 'archived_babies';
        $table_exists = mysqli_num_rows(mysqli_query($conn, "SHOW TABLES LIKE '$current_table'")) > 0;

        if (!$table_exists): ?>
            <!-- Archive Setup Notice -->
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div class="flex-grow-1">
                        <h5 class="alert-heading mb-1">Archive Table Not Found</h5>
                        <p class="mb-2">The archive table for <?php echo $titles[$archive_type] ?? ucfirst($archive_type); ?> is not yet created in the database. Click the button below to set up the archive system.</p>
                        <a href="archives.php?create_tables=1" class="btn btn-warning btn-sm">
                            <i class="fas fa-database me-1"></i>Create Archive Tables
                        </a>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>



        <!-- Archive List -->
        <section class="archive-list-section">
            <div class="card table-card">
                <div class="card-header py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <button onclick="history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </button>
                    </div>
                </div>

                <!-- Filters inside table card -->
                <div class="card-body border-bottom">
                    <form action="" method="GET" class="row g-3">
                        <div class="<?php echo $archive_type == 'all' ? 'col-md-8' : 'col-md-4'; ?>">
                            <label for="type" class="form-label fw-semibold">Archive Type</label>
                            <select class="form-select" id="type" name="type" onchange="this.form.submit()">
                                <option value="all" <?php echo $archive_type == 'all' ? 'selected' : ''; ?>>
                                    All Types
                                </option>
                                <option value="babies" <?php echo $archive_type == 'babies' ? 'selected' : ''; ?>>
                                    Baby Records
                                </option>
                                <option value="vaccinations" <?php echo $archive_type == 'vaccinations' ? 'selected' : ''; ?>>
                                    Vaccination Records
                                </option>
                                <option value="medicines" <?php echo $archive_type == 'medicines' ? 'selected' : ''; ?>>
                                    Medicine Records
                                </option>
                                <option value="users" <?php echo $archive_type == 'users' ? 'selected' : ''; ?>>
                                    User Records
                                </option>
                                <option value="appointments" <?php echo $archive_type == 'appointments' ? 'selected' : ''; ?>>
                                    Appointment Records
                                </option>
                                <option value="residents" <?php echo $archive_type == 'residents' ? 'selected' : ''; ?>>
                                    Resident Records
                                </option>
                            </select>
                        </div>
                        <?php if ($archive_type != 'all'): ?>
                        <div class="col-md-8">
                            <label for="search" class="form-label fw-semibold">
                                <i class="fas fa-search me-1"></i>Search Archives
                            </label>
                            <input type="text" class="form-control" id="search" name="search"
                                placeholder="Search in archives..." value="<?php echo htmlspecialchars($search); ?>"
                                oninput="clearTimeout(this.searchTimeout); this.searchTimeout = setTimeout(() => this.form.submit(), 500);">
                        </div>
                        <?php else: ?>
                        <div class="col-md-4 d-flex align-items-center " style="padding-top: 2rem;">
                            <a href="archives.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-1"></i>Reset
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card-body">
                    <?php if ($archive_type == 'all'): ?>
                        <div class='bg-white text-center py-5' style='border-radius: 8px;'>
                            <i class='fas fa-archive fa-3x mb-3'></i>
                            <h4>View All Archive Types</h4>
                            <p class='mb-4'>Select a specific archive type from the dropdown above to view archived records.</p>
                            <div class='row g-3'>
                                <?php
                                $quick_links = [
                                    'appointments' => ['icon' => 'calendar-check', 'title' => 'Appointment Records', 'gradient' => 'linear-gradient(135deg, #3498db, #2980b9)'],
                                    'medicines' => ['icon' => 'pills', 'title' => 'Medicine Records', 'gradient' => 'linear-gradient(135deg, #27ae60, #2ecc71)'],
                                    'vaccinations' => ['icon' => 'syringe', 'title' => 'Vaccination Records', 'gradient' => 'linear-gradient(135deg, #f39c12, #e67e22)'],
                                    'babies' => ['icon' => 'baby', 'title' => 'Baby Records', 'gradient' => 'linear-gradient(135deg, #e91e63, #c2185b)'],
                                    'residents' => ['icon' => 'users', 'title' => 'Resident Records', 'gradient' => 'linear-gradient(135deg, #9c27b0, #7b1fa2)'],
                                    'users' => ['icon' => 'user-cog', 'title' => 'User Records', 'gradient' => 'linear-gradient(135deg, #607d8b, #455a64)']
                                ];
                                foreach ($quick_links as $type => $info):
                                    // Count records for each type
                                    $table_name = 'archived_' . $type;
                                    $count_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM $table_name");
                                    $count = 0;
                                    if ($count_query) {
                                        $count_row = mysqli_fetch_assoc($count_query);
                                        $count = $count_row['count'];
                                    }
                                ?>
                                    <div class='col-lg-2 col-md-4 col-6 mb-3'>
                                        <a href='archives.php?type=<?php echo $type; ?>' class='text-decoration-none'>
                                            <div class='card h-100 hover-shadow' style='border: none; border-radius: 16px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); overflow: hidden; position: relative;'>
                                                <div style='height: 4px; background: <?php echo $info['gradient']; ?>;'></div>
                                                <div class='card-body text-center py-4'>
                                                    <div class='mb-3' style='width: 60px; height: 60px; margin: 0 auto; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: <?php echo $info['gradient']; ?>; box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);'>
                                                        <i class='fas fa-<?php echo $info['icon']; ?> fa-2x' style='color: white;'></i>
                                                    </div>
                                                    <h6 class='card-title mb-2' style='color: #2c3e50; font-weight: 600;'><?php echo $info['title']; ?></h6>
                                                    <p class='card-text'><span class='badge' style='background: <?php echo $info['gradient']; ?>; color: white; padding: 0.5rem 1rem; border-radius: 20px;'><?php echo $count; ?> records</span></p>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <?php 
                        // Check if we have a valid result or if there was an error
                        if ($result === false && !empty($search)) {
                            echo "<div class='alert alert-warning mb-3' role='alert'>
                                    <i class='fas fa-exclamation-triangle me-2'></i>
                                    <strong>Search Error:</strong> There was an issue processing your search. Please try with different keywords or contact the administrator.
                                  </div>";
                        }
                        
                        switch ($archive_type) {
                            case 'babies': ?>
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-user me-1"></i>Name</th>
                                            <th><i class="fas fa-birthday-cake me-1"></i>Date of Birth</th>
                                            <th><i class="fas fa-users me-1"></i>Parent/Guardian</th>
                                            <th><i class="fas fa-phone me-1"></i>Contact</th>
                                            <th><i class="fas fa-calendar me-1"></i>Archived Date</th>
                                            <th><i class="fas fa-user-shield me-1"></i>Archived By</th>
                                            <th><i class="fas fa-cogs me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($result && mysqli_num_rows($result) > 0) {
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                echo "<tr>";
                                                echo "<td><strong>" . htmlspecialchars($row['full_name']) . "</strong></td>";
                                                echo "<td>" . date('M d, Y', strtotime($row['date_of_birth'])) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['parent_guardian_name']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
                                                echo "<td><small>" . date('M d, Y', strtotime($row['archived_at'])) . "</small></td>";
                                                echo "<td><small>" . htmlspecialchars($row['archived_by_name']) . "</small></td>";
                                                echo "<td>";
                                                
                                                // Only admins can restore and delete baby records
                                                if (isAdmin()) {
                                                    echo "<div class='btn-group' role='group'>
                                                        <a href='archives.php?type=babies&restore_id={$row['id']}' 
                                                           class='btn btn-sm btn-restore' 
                                                           onclick='return confirm(\"Are you sure you want to restore this baby record?\")'>
                                                            <i class='fas fa-undo me-1'></i>
                                                        </a>
                                                        <a href='archives.php?type=babies&delete_id={$row['id']}' 
                                                           class='btn btn-sm btn-danger' 
                                                           onclick='return confirm(\"Are you sure you want to permanently delete this baby record? This action cannot be undone!\")'>
                                                            <i class='fas fa-trash'></i>
                                                        </a>
                                                    </div>";
                                                } else {
                                                    echo "<div class='btn-group' role='group'>
                                                        <button class='btn btn-sm btn-secondary' disabled title='Admin access required'>
                                                            <i class='fas fa-undo me-1'></i>
                                                        </button>
                                                        <button class='btn btn-sm btn-secondary' disabled title='Admin access required'>
                                                            <i class='fas fa-trash'></i>
                                                        </button>
                                                    </div>";
                                                }
                                                
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            if ($result === false) {
                                                $message = "Archive tables are not yet created or there was an error accessing the data. Please contact your system administrator.";
                                            } else {
                                                $message = !empty($search) ? 
                                                    "No baby records found matching your search criteria. Try different keywords." :
                                                    "There are no baby records in the archive.";
                                            }
                                            echo "<tr><td colspan='7' class='text-center py-4'>
                                            <i class='fas fa-inbox fa-3x text-muted mb-3'></i>
                                            <h5 class='text-muted'>No archived baby records found</h5>
                                            <p class='text-muted'>$message</p>
                                        </td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <?php break;
                            case 'medicines': ?>
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-pills me-1"></i>Medicine Name</th>
                                            <th><i class="fas fa-prescription-bottle me-1"></i>Dosage</th>
                                            <th><i class="fas fa-calendar-times me-1"></i>Expiry Date</th>
                                            <th><i class="fas fa-hashtag me-1"></i>Batch Number</th>
                                            <th><i class="fas fa-boxes me-1"></i>Quantity</th>
                                            <th><i class="fas fa-calendar me-1"></i>Archived Date</th>
                                            <th><i class="fas fa-user-shield me-1"></i>Archived By</th>
                                            <th><i class="fas fa-cogs me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($result && mysqli_num_rows($result) > 0) {
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                $expired = strtotime($row['expiry_date']) < strtotime(date('Y-m-d'));
                                                echo "<tr>";
                                                echo "<td><strong>" . htmlspecialchars($row['medicine_name']) . "</strong></td>";
                                                echo "<td>" . htmlspecialchars($row['dosage'] ?? 'Not specified') . "</td>";
                                                echo "<td>" . date('M d, Y', strtotime($row['expiry_date'])) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['batch_number']) . "</td>";
                                                echo "<td>" . $row['quantity'] . (isset($row['unit']) ? " " . htmlspecialchars($row['unit']) : "") . "</td>";
                                                echo "<td><small>" . date('M d, Y', strtotime($row['archived_at'])) . "</small></td>";
                                                echo "<td><small>" . htmlspecialchars($row['archived_by_name']) . "</small></td>";
                                                echo "<td>";
                                                
                                                // Only admins can restore and delete medicine records
                                                if (isAdmin()) {
                                                    echo "<div class='btn-group' role='group'>
                                                        <a href='archives.php?type=medicines&restore_id={$row['id']}' 
                                                           class='btn btn-sm btn-restore' 
                                                           onclick='return confirm(\"Are you sure you want to restore this medicine record?\")'>
                                                            <i class='fas fa-undo me-1'></i>
                                                        </a>
                                                        <a href='archives.php?type=medicines&delete_id={$row['id']}' 
                                                           class='btn btn-sm btn-danger' 
                                                           onclick='return confirm(\"Are you sure you want to permanently delete this medicine record? This action cannot be undone!\")'>
                                                            <i class='fas fa-trash'></i>
                                                        </a>
                                                    </div>";
                                                } else {
                                                    echo "<div class='btn-group' role='group'>
                                                        <button class='btn btn-sm btn-secondary' disabled title='Admin access required'>
                                                            <i class='fas fa-undo me-1'></i>
                                                        </button>
                                                        <button class='btn btn-sm btn-secondary' disabled title='Admin access required'>
                                                            <i class='fas fa-trash'></i>
                                                        </button>
                                                    </div>";
                                                }
                                                
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            $message = $result === false ?
                                                "Archive tables are not yet created. Please contact your system administrator." :
                                                "There are no medicine records in the archive.";
                                            echo "<tr><td colspan='7' class='text-center py-4'>
                                            <i class='fas fa-inbox fa-3x text-muted mb-3'></i>
                                            <h5 class='text-muted'>No archived medicine records found</h5>
                                            <p class='text-muted'>$message</p>
                                        </td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <?php break;
                            case 'users': ?>
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-user me-1"></i>Full Name</th>
                                            <th><i class="fas fa-user-tag me-1"></i>Username</th>
                                            <th><i class="fas fa-envelope me-1"></i>Email</th>
                                            <th><i class="fas fa-phone me-1"></i>Contact</th>
                                            <th><i class="fas fa-user-shield me-1"></i>Role</th>
                                            <th><i class="fas fa-calendar me-1"></i>Archived Date</th>
                                            <th><i class="fas fa-user-shield me-1"></i>Archived By</th>
                                            <th><i class="fas fa-cogs me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($result && mysqli_num_rows($result) > 0) {
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                echo "<tr>";
                                                echo "<td><strong>" . htmlspecialchars($row['fullname']) . "</strong></td>";
                                                echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['email'] ?? '-') . "</td>";
                                                echo "<td>" . htmlspecialchars($row['contact_number'] ?? '-') . "</td>";
                                                echo "<td><span class='badge bg-" . ($row['role'] == 'admin' ? 'danger' : ($row['role'] == 'worker' ? 'warning' : 'info')) . "'>" . ucfirst($row['role']) . "</span></td>";
                                                echo "<td><small>" . date('M d, Y', strtotime($row['archived_at'])) . "</small></td>";
                                                echo "<td><small>" . htmlspecialchars($row['archived_by_name']) . "</small></td>";
                                                echo "<td>
                                                <div class='btn-group' role='group'>
                                                    <a href='archives.php?type=users&restore_id={$row['id']}' 
                                                       class='btn btn-sm btn-restore' 
                                                       onclick='return confirm(\"Are you sure you want to restore this user record?\")'>
                                                        <i class='fas fa-undo me-1'></i>
                                                    </a>
                                                    <a href='archives.php?type=users&delete_id={$row['id']}' 
                                                       class='btn btn-sm btn-danger' 
                                                       onclick='return confirm(\"Are you sure you want to permanently delete this user record? This action cannot be undone!\")'>
                                                        <i class='fas fa-trash'></i>
                                                    </a>
                                                </div>
                                            </td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            $message = $result === false ?
                                                "Archive tables are not yet created. Please contact your system administrator." :
                                                "There are no user records in the archive.";
                                            echo "<tr><td colspan='8' class='text-center py-4'>
                                            <i class='fas fa-inbox fa-3x text-muted mb-3'></i>
                                            <h5 class='text-muted'>No archived user records found</h5>
                                            <p class='text-muted'>$message</p>
                                        </td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <?php break;
                            case 'appointments': ?>
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-user me-1"></i>Patient Name</th>
                                            <th><i class="fas fa-medical-kit me-1"></i>Type</th>
                                            <th><i class="fas fa-calendar-alt me-1"></i>Appointment Date</th>
                                            <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                            <th><i class="fas fa-sticky-note me-1"></i>Notes</th>
                                            <th><i class="fas fa-calendar me-1"></i>Archived Date</th>
                                            <th><i class="fas fa-user-shield me-1"></i>Archived By</th>
                                            <th><i class="fas fa-cogs me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($result && mysqli_num_rows($result) > 0) {
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                echo "<tr>";
                                                echo "<td><strong>" . htmlspecialchars($row['fullname']) . "</strong></td>";
                                                echo "<td><span class='badge bg-" . ($row['appointment_type'] == 'Vaccination' ? 'primary' : 'info') . "'>" . htmlspecialchars($row['appointment_type']) . "</span></td>";
                                                echo "<td>" . date('M d, Y g:i A', strtotime($row['preferred_datetime'])) . "</td>";
                                                echo "<td><span class='badge bg-" . ($row['status'] == 'completed' ? 'success' : ($row['status'] == 'cancelled' ? 'danger' : 'warning')) . "'>" . ucfirst($row['status']) . "</span></td>";
                                                
                                                // Display notes with cancellation reason if cancelled
                                                if ($row['status'] == 'cancelled') {
                                                    echo "<td>";
                                                    
                                                    // Check who cancelled based on cancelled_by_role column
                                                    if (isset($row['cancelled_by_role'])) {
                                                        $role = $row['cancelled_by_role'];
                                                        
                                                        if ($role == 'resident') {
                                                            echo "<strong class='text-danger'>Cancelled by resident</strong>";
                                                        } elseif ($role == 'worker') {
                                                            echo "<strong class='text-warning'>Cancelled by worker</strong>";
                                                            if (!empty($row['cancellation_reason']) && $row['cancellation_reason'] != 'Cancelled by resident') {
                                                                echo "<br><small class='text-muted'>Reason: " . htmlspecialchars($row['cancellation_reason']) . "</small>";
                                                            }
                                                        } elseif ($role == 'admin') {
                                                            echo "<strong class='text-primary'>Cancelled by admin</strong>";
                                                            if (!empty($row['cancellation_reason']) && $row['cancellation_reason'] != 'Cancelled by resident') {
                                                                echo "<br><small class='text-muted'>Reason: " . htmlspecialchars($row['cancellation_reason']) . "</small>";
                                                            }
                                                        } else {
                                                            echo "<strong class='text-secondary'>Cancelled</strong>";
                                                        }
                                                    } else {
                                                        // Old records without role tracking - try to guess from reason
                                                        if (!empty($row['cancellation_reason'])) {
                                                            if ($row['cancellation_reason'] == 'Cancelled by resident') {
                                                                echo "<strong class='text-danger'>Cancelled by resident</strong>";
                                                            } else {
                                                                echo "<strong class='text-warning'>Cancelled by worker/admin</strong>";
                                                                echo "<br><small class='text-muted'>Reason: " . htmlspecialchars($row['cancellation_reason']) . "</small>";
                                                            }
                                                        } else {
                                                            echo "<strong class='text-secondary'>Cancelled</strong>";
                                                        }
                                                    }
                                                    
                                                    // Show original notes if they exist
                                                    if (!empty($row['notes']) && $row['notes'] != 'Cancelled by resident') {
                                                        echo "<br><small class='text-muted'>Original: " . htmlspecialchars($row['notes']) . "</small>";
                                                    }
                                                    
                                                    echo "</td>";
                                                } else {
                                                    // Regular notes display for non-cancelled appointments
                                                    $notes_display = htmlspecialchars($row['notes']);
                                                    echo "<td>" . (strlen($notes_display) > 100 ? substr($notes_display, 0, 100) . '...' : ($notes_display ?: '<em class="text-muted">No notes</em>')) . "</td>";
                                                }
                                                echo "<td><small>" . date('M d, Y', strtotime($row['archived_at'])) . "</small></td>";
                                                echo "<td><small>" . htmlspecialchars($row['archived_by_name']) . "</small></td>";
                                                echo "<td>";
                                                
                                                // Only admins can restore and delete appointment records
                                                if (isAdmin()) {
                                                    echo "<div class='btn-group' role='group'>
                                                        <a href='archives.php?type=appointments&restore_id={$row['id']}' 
                                                           class='btn btn-sm btn-restore' 
                                                           onclick='return confirm(\"Are you sure you want to restore this appointment record?\")'>
                                                            <i class='fas fa-undo me-1'></i>
                                                        </a>
                                                        <a href='archives.php?type=appointments&delete_id={$row['id']}' 
                                                           class='btn btn-sm btn-danger' 
                                                           onclick='return confirm(\"Are you sure you want to permanently delete this appointment record? This action cannot be undone!\")'>
                                                            <i class='fas fa-trash'></i>
                                                        </a>
                                                    </div>";
                                                } else {
                                                    echo "<div class='btn-group' role='group'>
                                                        <button class='btn btn-sm btn-secondary' disabled title='Admin access required'>
                                                            <i class='fas fa-undo me-1'></i>
                                                        </button>
                                                        <button class='btn btn-sm btn-secondary' disabled title='Admin access required'>
                                                            <i class='fas fa-trash'></i>
                                                        </button>
                                                    </div>";
                                                }
                                                
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            $message = $result === false ?
                                                "Archive tables are not yet created. Please contact your system administrator." :
                                                "There are no appointment records in the archive.";
                                            echo "<tr><td colspan='8' class='text-center py-4'>
                                            <i class='fas fa-inbox fa-3x text-muted mb-3'></i>
                                            <h5 class='text-muted'>No archived appointment records found</h5>
                                            <p class='text-muted'>$message</p>
                                        </td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <?php break;
                            case 'vaccinations': ?>
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-baby me-1"></i>Baby Name</th>
                                            <th><i class="fas fa-syringe me-1"></i>Vaccine Type</th>
                                            <th><i class="fas fa-calendar-alt me-1"></i>Schedule Date</th>
                                            <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                            <th><i class="fas fa-calendar me-1"></i>Date</th>
                                            <th><i class="fas fa-user-shield me-1"></i>Archived By</th>
                                            <th><i class="fas fa-cogs me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($result && mysqli_num_rows($result) > 0) {
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                echo "<tr>";
                                                echo "<td><strong>" . htmlspecialchars($row['baby_name']) . "</strong></td>";
                                                echo "<td>" . htmlspecialchars($row['vaccine_type']) . "</td>";
                                                echo "<td>" . date('M d, Y', strtotime($row['schedule_date'])) . "</td>";
                                                echo "<td><span class='badge " . ($row['status'] == 'completed' ? 'bg-success' : ($row['status'] == 'cancelled' ? 'bg-danger' : 'bg-warning')) . "'>" . ucfirst($row['status']) . "</span></td>";
                                                
                                                // Show appropriate date based on status
                                                if ($row['status'] == 'completed' && !empty($row['administered_date'])) {
                                                    $display_date = $row['administered_date'];
                                                    $date_label = 'Completed';
                                                } elseif ($row['status'] == 'cancelled') {
                                                    $display_date = $row['archived_at'];
                                                    $date_label = 'Cancelled';
                                                } else {
                                                    $display_date = $row['schedule_date'];
                                                    $date_label = 'Scheduled';
                                                }
                                                echo "<td><small class='text-muted'>" . $date_label . ":</small><br>" . date('M d, Y', strtotime($display_date)) . "</td>";
                                                
                                                echo "<td><small>" . htmlspecialchars($row['archived_by_name']) . "</small></td>";
                                                echo "<td>";
                                                
                                                // Only admins can restore and delete vaccination records
                                                if (isAdmin()) {
                                                    echo "<div class='btn-group' role='group'>
                                                        <a href='archives.php?type=vaccinations&restore_id={$row['id']}' 
                                                           class='btn btn-sm btn-restore' 
                                                           onclick='return confirm(\"Are you sure you want to restore this vaccination record?\")'>
                                                            <i class='fas fa-undo me-1'></i>
                                                        </a>
                                                        <a href='archives.php?type=vaccinations&delete_id={$row['id']}' 
                                                           class='btn btn-sm btn-danger' 
                                                           onclick='return confirm(\"Are you sure you want to permanently delete this vaccination record? This action cannot be undone!\")'>
                                                            <i class='fas fa-trash'></i>
                                                        </a>
                                                    </div>";
                                                } else {
                                                    echo "<div class='btn-group' role='group'>
                                                        <button class='btn btn-sm btn-secondary' disabled title='Admin access required'>
                                                            <i class='fas fa-undo me-1'></i>
                                                        </button>
                                                        <button class='btn btn-sm btn-secondary' disabled title='Admin access required'>
                                                            <i class='fas fa-trash'></i>
                                                        </button>
                                                    </div>";
                                                }
                                                
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            $message = $result === false ?
                                                "Archive tables are not yet created. Please contact your system administrator." :
                                                "There are no vaccination records in the archive.";
                                            echo "<tr><td colspan='7' class='text-center py-4'>
                                            <i class='fas fa-inbox fa-3x text-muted mb-3'></i>
                                            <h5 class='text-muted'>No archived vaccination records found</h5>
                                            <p class='text-muted'>$message</p>
                                        </td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <?php break;
                            case 'residents': ?>
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-user me-1"></i>Full Name</th>
                                            <th><i class="fas fa-venus-mars me-1"></i>Gender</th>
                                            <th><i class="fas fa-birthday-cake me-1"></i>Age</th>
                                            <th><i class="fas fa-map-marker-alt me-1"></i>Purok</th>
                                            <th><i class="fas fa-briefcase me-1"></i>Occupation</th>
                                            <th><i class="fas fa-graduation-cap me-1"></i>Education</th>
                                            <th><i class="fas fa-calendar me-1"></i>Archived Date</th>
                                            <th><i class="fas fa-user-shield me-1"></i>Archived By</th>
                                            <th><i class="fas fa-cogs me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($result && mysqli_num_rows($result) > 0) {
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                echo "<tr>";
                                                echo "<td><strong>" . htmlspecialchars($row['last_name'] . ', ' . $row['first_name']) . "</strong>";
                                                if (!empty($row['middle_name'])) {
                                                    echo "<br><small class='text-muted'>" . htmlspecialchars($row['middle_name']) . "</small>";
                                                }
                                                echo "</td>";
                                                echo "<td><i class='fas fa-" . ($row['gender'] === 'Male' ? 'mars text-primary' : 'venus text-danger') . "'></i> " . htmlspecialchars($row['gender']) . "</td>";
                                                echo "<td>" . $row['age'] . " years<br><small class='text-muted'>" . date('M d, Y', strtotime($row['birthday'])) . "</small></td>";
                                                echo "<td><span class='badge bg-info'>" . htmlspecialchars($row['purok']) . "</span></td>";
                                                echo "<td>" . htmlspecialchars($row['occupation'] ?: '-') . "</td>";
                                                echo "<td>" . htmlspecialchars($row['education'] ?: '-') . "</td>";
                                                echo "<td><small>" . date('M d, Y', strtotime($row['archived_at'])) . "</small></td>";
                                                echo "<td><small>" . htmlspecialchars($row['archived_by_name']) . "</small></td>";
                                                echo "<td>";
                                                
                                                // Only admins can restore and delete resident records
                                                if (isAdmin()) {
                                                    echo "<div class='btn-group' role='group'>
                                                        <a href='archives.php?type=residents&restore_id={$row['id']}' 
                                                           class='btn btn-sm btn-restore' 
                                                           onclick='return confirm(\"Are you sure you want to restore this resident record?\")'>
                                                            <i class='fas fa-undo me-1'></i>
                                                        </a>
                                                        <a href='archives.php?type=residents&delete_id={$row['id']}' 
                                                           class='btn btn-sm btn-danger' 
                                                           onclick='return confirm(\"Are you sure you want to permanently delete this resident record? This action cannot be undone!\")'>
                                                            <i class='fas fa-trash'></i>
                                                        </a>
                                                    </div>";
                                                } else {
                                                    echo "<div class='btn-group' role='group'>
                                                        <button class='btn btn-sm btn-secondary' disabled title='Admin access required'>
                                                            <i class='fas fa-undo me-1'></i>
                                                        </button>
                                                        <button class='btn btn-sm btn-secondary' disabled title='Admin access required'>
                                                            <i class='fas fa-trash'></i>
                                                        </button>
                                                    </div>";
                                                }
                                                
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            $message = $result === false ?
                                                "Archive tables are not yet created. Please contact your system administrator." :
                                                "There are no resident records in the archive.";
                                            echo "<tr><td colspan='9' class='text-center py-4'>
                                            <i class='fas fa-inbox fa-3x text-muted mb-3'></i>
                                            <h5 class='text-muted'>No archived resident records found</h5>
                                            <p class='text-muted'>$message</p>
                                        </td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <?php break;
                        } ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <!-- Bottom spacing for better UX -->
    <div style="height: 60px;"></div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Modal Script -->
    <script>
        // Force page reload on back button to prevent cached data
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                // Page was loaded from cache (back button), force reload
                window.location.reload();
            }
        });

        function closeModal() {
            const modal = document.getElementById('messageModal');
            if (modal) {
                modal.classList.add('hiding');
                setTimeout(() => {
                    modal.classList.remove('show', 'hiding');
                    // Remove query parameters from URL
                    const url = new URL(window.location);
                    url.searchParams.delete('success');
                    url.searchParams.delete('error');
                    window.history.replaceState({}, '', url);
                }, 300);
            }
        }

        // Auto close modal after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('messageModal');
            if (modal) {
                setTimeout(() => {
                    closeModal();
                }, 5000);
            }
        });

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('messageModal');
            if (modal && event.target === modal) {
                closeModal();
            }
        });
    </script>
</body>

</html>