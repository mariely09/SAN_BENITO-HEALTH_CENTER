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

// Only approved users can view
requireApproved();

// Check if barangay_residents table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'barangay_residents'");
$table_exists = mysqli_num_rows($table_check) > 0;

if (!$table_exists) {
    // Redirect to setup page if table doesn't exist
    header("Location: setup_residents_table.php");
    exit;
}

// Handle AJAX requests for adding/updating residents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_resident') {
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $birthday = mysqli_real_escape_string($conn, $_POST['birthday']);
        
        // Calculate age from birthday
        $age = 0;
        if (!empty($birthday)) {
            $birthDate = new DateTime($birthday);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
        }
        
        $purok = mysqli_real_escape_string($conn, $_POST['purok']);
        $occupation = mysqli_real_escape_string($conn, $_POST['occupation']);
        $education = mysqli_real_escape_string($conn, $_POST['education']);
        $is_senior = isset($_POST['is_senior']) ? 1 : 0;
        $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
        $family_planning = isset($_POST['family_planning']) ? 'Yes' : 'No';
        $has_electricity = isset($_POST['has_electricity']) ? 1 : 0;
        $has_poso = isset($_POST['has_poso']) ? 1 : 0;
        $has_nawasa = isset($_POST['has_nawasa']) ? 1 : 0;
        $has_cr = isset($_POST['has_cr']) ? 1 : 0;
        
        $query = "INSERT INTO barangay_residents (first_name, last_name, middle_name, age, gender, birthday, purok, occupation, education, is_senior, is_pwd, family_planning, has_electricity, has_poso, has_nawasa, has_cr) 
                  VALUES ('$first_name', '$last_name', '$middle_name', $age, '$gender', '$birthday', '$purok', '$occupation', '$education', $is_senior, $is_pwd, '$family_planning', $has_electricity, $has_poso, $has_nawasa, $has_cr)";
        
        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Resident added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding resident: ' . mysqli_error($conn)]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'update_resident') {
        $id = intval($_POST['id']);
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name']);
        $gender = mysqli_real_escape_string($conn, $_POST['gender']);
        $birthday = mysqli_real_escape_string($conn, $_POST['birthday']);
        
        // Calculate age from birthday
        $age = 0;
        if (!empty($birthday)) {
            $birthDate = new DateTime($birthday);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
        }
        
        $purok = mysqli_real_escape_string($conn, $_POST['purok']);
        $occupation = mysqli_real_escape_string($conn, $_POST['occupation']);
        $education = mysqli_real_escape_string($conn, $_POST['education']);
        $is_senior = isset($_POST['is_senior']) ? 1 : 0;
        $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
        $family_planning = isset($_POST['family_planning']) ? 'Yes' : 'No';
        $has_electricity = isset($_POST['has_electricity']) ? 1 : 0;
        $has_poso = isset($_POST['has_poso']) ? 1 : 0;
        $has_nawasa = isset($_POST['has_nawasa']) ? 1 : 0;
        $has_cr = isset($_POST['has_cr']) ? 1 : 0;
        
        $query = "UPDATE barangay_residents SET 
                  first_name='$first_name', last_name='$last_name', middle_name='$middle_name', 
                  age=$age, gender='$gender', birthday='$birthday', purok='$purok', 
                  occupation='$occupation', education='$education', is_senior=$is_senior, 
                  is_pwd=$is_pwd, family_planning='$family_planning', has_electricity=$has_electricity, 
                  has_poso=$has_poso, has_nawasa=$has_nawasa, has_cr=$has_cr 
                  WHERE id=$id";
        
        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Resident updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating resident: ' . mysqli_error($conn)]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'delete_resident') {
        $id = intval($_POST['id']);
        $query = "DELETE FROM barangay_residents WHERE id=$id";
        
        if (mysqli_query($conn, $query)) {
            echo json_encode(['success' => true, 'message' => 'Resident deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting resident: ' . mysqli_error($conn)]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'archive_resident') {
        $id = intval($_POST['id']);
        
        // First, get the resident data
        $get_query = "SELECT * FROM barangay_residents WHERE id=$id";
        $get_result = mysqli_query($conn, $get_query);
        
        if ($get_result && mysqli_num_rows($get_result) > 0) {
            $resident = mysqli_fetch_assoc($get_result);
            
            // Create archived_residents table if it doesn't exist
            $create_table_query = "CREATE TABLE IF NOT EXISTS archived_residents (
                id INT PRIMARY KEY AUTO_INCREMENT,
                original_id INT NOT NULL,
                first_name VARCHAR(255) NOT NULL,
                last_name VARCHAR(255) NOT NULL,
                middle_name VARCHAR(255),
                age INT NOT NULL,
                gender ENUM('Male', 'Female') NOT NULL,
                birthday DATE NOT NULL,
                purok VARCHAR(50) NOT NULL,
                occupation VARCHAR(255),
                education VARCHAR(255),
                is_senior TINYINT(1) DEFAULT 0,
                is_pwd TINYINT(1) DEFAULT 0,
                family_planning VARCHAR(10) DEFAULT 'No',
                has_electricity TINYINT(1) DEFAULT 0,
                has_poso TINYINT(1) DEFAULT 0,
                has_nawasa TINYINT(1) DEFAULT 0,
                has_cr TINYINT(1) DEFAULT 0,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_by INT,
                archive_reason VARCHAR(255) DEFAULT 'Archived',
                FOREIGN KEY (archived_by) REFERENCES users(id)
            )";
            mysqli_query($conn, $create_table_query);
            
            // Insert into archive table
            $archive_query = "INSERT INTO archived_residents (
                original_id, first_name, last_name, middle_name, age, gender, birthday, purok, 
                occupation, education, is_senior, is_pwd, family_planning, has_electricity, 
                has_poso, has_nawasa, has_cr, archived_by
            ) VALUES (
                {$resident['id']}, '{$resident['first_name']}', '{$resident['last_name']}', 
                '{$resident['middle_name']}', {$resident['age']}, '{$resident['gender']}', 
                '{$resident['birthday']}', '{$resident['purok']}', '{$resident['occupation']}', 
                '{$resident['education']}', {$resident['is_senior']}, {$resident['is_pwd']}, 
                '{$resident['family_planning']}', {$resident['has_electricity']}, {$resident['has_poso']}, 
                {$resident['has_nawasa']}, {$resident['has_cr']}, {$_SESSION['user_id']}
            )";
            
            if (mysqli_query($conn, $archive_query)) {
                // Delete from main table
                $delete_query = "DELETE FROM barangay_residents WHERE id=$id";
                if (mysqli_query($conn, $delete_query)) {
                    echo json_encode(['success' => true, 'message' => 'Resident archived successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error removing resident from main table: ' . mysqli_error($conn)]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Error archiving resident: ' . mysqli_error($conn)]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Resident not found']);
        }
        exit;
    }
}

// Handle filtering
$filter_purok = isset($_GET['filter_purok']) ? mysqli_real_escape_string($conn, $_GET['filter_purok']) : '';
$filter_age_group = isset($_GET['filter_age_group']) ? $_GET['filter_age_group'] : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Handle utility checkboxes
$has_electricity_filter = isset($_GET['has_electricity']);
$has_water_filter = isset($_GET['has_water']);
$has_toilet_filter = isset($_GET['has_toilet']);

// Handle health priority group checkboxes
$filter_seniors = isset($_GET['filter_seniors']);
$filter_pwd = isset($_GET['filter_pwd']);
$filter_fp = isset($_GET['filter_fp']);

// Build WHERE clause
$where_clauses = [];

if (!empty($filter_purok)) {
    $where_clauses[] = "purok = '$filter_purok'";
}



if ($filter_age_group === '0-12') {
    $where_clauses[] = "age BETWEEN 0 AND 12";
} elseif ($filter_age_group === '13-72') {
    $where_clauses[] = "age BETWEEN 13 AND 72";
}

// Handle utility checkbox filters
if ($has_electricity_filter) {
    $where_clauses[] = "has_electricity = 1";
}

if ($has_water_filter) {
    $where_clauses[] = "(has_poso = 1 OR has_nawasa = 1)";
}

if ($has_toilet_filter) {
    $where_clauses[] = "has_cr = 1";
}

// Handle health priority group checkbox filters
$priority_conditions = [];
if ($filter_seniors) {
    $priority_conditions[] = "is_senior = 1";
}
if ($filter_pwd) {
    $priority_conditions[] = "is_pwd = 1";
}
if ($filter_fp) {
    $priority_conditions[] = "family_planning = 'Yes'";
}

// If any priority group filters are selected, combine them with OR logic
if (!empty($priority_conditions)) {
    $where_clauses[] = "(" . implode(" OR ", $priority_conditions) . ")";
}

if (!empty($search)) {
    $where_clauses[] = "(first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR middle_name LIKE '%$search%' OR occupation LIKE '%$search%')";
}

$where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count
$count_query = "SELECT COUNT(*) as total FROM barangay_residents $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_residents = mysqli_fetch_assoc($count_result)['total'];

// Get residents with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$query = "SELECT * FROM barangay_residents $where_clause ORDER BY last_name, first_name LIMIT $per_page OFFSET $offset";
$result = mysqli_query($conn, $query);

// Get unique puroks for filter dropdown
$puroks = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6'];

$total_pages = ceil($total_residents / $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Residents Information</title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Navbar Styles -->
    <link rel="stylesheet" href="assets/css/navbar.css">
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/residents_dashboard.css">
    <!-- Users Styles -->
    <link rel="stylesheet" href="assets/css/users.css">
    <!-- Responsive Styles -->
    <link rel="stylesheet" href="assets/css/responsive.css?v=<?php echo time(); ?>">
    <!-- Residents Styles -->
    <link rel="stylesheet" href="assets/css/residents.css?v=<?php echo time(); ?>">
</head>
<body class="residents-page">
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid mt-4" style="max-width: 95%; padding-left: 20px; padding-right: 20px;">
        <!-- Residents Management Header -->
        <div class="user-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">
                        <i class="fas fa-users me-2"></i>
                        Barangay Residents Information
                    </h1>
                    <p class="welcome-subtitle">Comprehensive database of all barangay residents with detailed information and filtering capabilities.</p>
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

        <!-- Residents Statistics -->
        <section class="statistics-section mb-4">
            <h2 class="section-title">
                <i class="fas fa-chart-bar me-2"></i>Residents Statistics
            </h2>
            <div class="row justify-content-center">
                <div class="col-6 col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="card user-stats-card">
                        <div class="card-body text-center">
                            <div class="user-stats-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="user-stats-number"><?php echo $total_residents; ?></h3>
                            <p class="user-stats-label">Total Residents</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="card user-stats-card">
                        <div class="card-body text-center">
                            <div class="user-stats-icon">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <h3 class="user-stats-number">
                                <?php 
                                $senior_query = "SELECT COUNT(*) as count FROM barangay_residents WHERE is_senior = 1";
                                if (!empty($where_clauses)) {
                                    $senior_query .= " AND " . implode(" AND ", $where_clauses);
                                }
                                $senior_result = mysqli_query($conn, $senior_query);
                                echo mysqli_fetch_assoc($senior_result)['count'];
                                ?>
                            </h3>
                            <p class="user-stats-label">Senior Citizens</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="card user-stats-card">
                        <div class="card-body text-center">
                            <div class="user-stats-icon">
                                <i class="fas fa-wheelchair"></i>
                            </div>
                            <h3 class="user-stats-number">
                                <?php 
                                $pwd_query = "SELECT COUNT(*) as count FROM barangay_residents WHERE is_pwd = 1";
                                if (!empty($where_clauses)) {
                                    $pwd_query .= " AND " . implode(" AND ", $where_clauses);
                                }
                                $pwd_result = mysqli_query($conn, $pwd_query);
                                echo mysqli_fetch_assoc($pwd_result)['count'];
                                ?>
                            </h3>
                            <p class="user-stats-label">PWD Residents</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="card user-stats-card">
                        <div class="card-body text-center">
                            <div class="user-stats-icon">
                                <i class="fas fa-baby"></i>
                            </div>
                            <h3 class="user-stats-number">
                                <?php 
                                $children_query = "SELECT COUNT(*) as count FROM barangay_residents WHERE age BETWEEN 0 AND 12";
                                if (!empty($where_clauses)) {
                                    $children_query .= " AND " . implode(" AND ", $where_clauses);
                                }
                                $children_result = mysqli_query($conn, $children_query);
                                echo mysqli_fetch_assoc($children_result)['count'];
                                ?>
                            </h3>
                            <p class="user-stats-label">Children (0-12)</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>



        <!-- Residents List -->
        <section class="residents-list-section">
            <div class="card table-card">
                <div class="card-header d-flex flex-nowrap justify-content-start align-items-center">
                    <div class="d-flex flex-nowrap residents-header-buttons">
                        <button type="button" class="btn btn-secondary btn-sm residents-btn" onclick="window.history.back()" title="Go Back">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </button>
                        <a href="archives.php?type=residents" class="btn btn-warning btn-sm residents-btn" title="View Archives">
                            <i class="fas fa-archive me-1"></i> Archives
                        </a>
                        <button type="button" class="btn btn-primary btn-sm residents-btn" data-bs-toggle="modal" data-bs-target="#addResidentModal" title="Add New Resident">
                            <i class="fas fa-plus me-1"></i> Add Resident
                        </button>
                    </div>
                </div>

                <!-- Filters inside table card -->
                <div class="card-body border-bottom">
                    <form method="GET" class="mb-0">
                        <!-- Main Search and Purok Filter Row -->
                        <div class="row g-3 mb-3">
                            <div class="col-lg-5 col-md-6 col-12">
                                <label for="search" class="form-label fw-semibold mb-1">
                                    <i class="fas fa-search me-1"></i>Search Residents
                                </label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Name, occupation..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-lg-3 col-md-6 col-12">
                                <label for="filter_purok" class="form-label fw-semibold mb-1">
                                    <i class="fas fa-map-marker-alt me-1"></i>Purok
                                </label>
                                <select class="form-select" id="filter_purok" name="filter_purok">
                                    <option value="">All Puroks</option>
                                    <?php foreach ($puroks as $purok): ?>
                                        <option value="<?php echo htmlspecialchars($purok); ?>" 
                                                <?php echo $filter_purok === $purok ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($purok); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-6 col-12">
                                <label for="filter_age_group" class="form-label fw-semibold mb-1">Age Group</label>
                                <select class="form-select" id="filter_age_group" name="filter_age_group">
                                    <option value="">All Ages</option>
                                    <option value="0-12" <?php echo $filter_age_group === '0-12' ? 'selected' : ''; ?>>0-12 years</option>
                                    <option value="13-72" <?php echo $filter_age_group === '13-72' ? 'selected' : ''; ?>>13-72 years</option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-6 col-12 d-flex align-items-end gap-2">
                                <a href="residents.php" class="btn btn-outline-secondary w-100" title="Reset Filters">
                                    <i class="fas fa-redo me-1"></i>Reset
                                </a>
                            </div>
                        </div>

                        <!-- Additional Filters Row -->
                        <div class="row g-3">
                            <div class="col-lg-6 col-md-6 col-12">
                                <label class="form-label fw-semibold mb-2">
                                    <i class="fas fa-heartbeat me-1"></i>Health Priority Groups
                                </label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="filter_seniors" name="filter_seniors" 
                                               <?php echo (isset($_GET['filter_seniors'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="filter_seniors">Seniors</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="filter_pwd" name="filter_pwd" 
                                               <?php echo (isset($_GET['filter_pwd'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="filter_pwd">PWD</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="filter_fp" name="filter_fp" 
                                               <?php echo (isset($_GET['filter_fp'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="filter_fp">Family Planning</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-12">
                                <label class="form-label fw-semibold mb-2">
                                    <i class="fas fa-home me-1"></i>Utilities
                                </label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="has_electricity" name="has_electricity" 
                                               <?php echo (isset($_GET['has_electricity'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="has_electricity">Electricity</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="has_water" name="has_water" 
                                               <?php echo (isset($_GET['has_water'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="has_water">Water</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="has_toilet" name="has_toilet" 
                                               <?php echo (isset($_GET['has_toilet'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="has_toilet">Toilet</label>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-user me-1"></i>Full Name</th>
                                    <th><i class="fas fa-venus-mars me-1"></i>M/F</th>
                                    <th><i class="fas fa-birthday-cake me-1"></i>Age</th>
                                    <th><i class="fas fa-map-marker-alt me-1"></i>Purok</th>
                                    <th><i class="fas fa-briefcase me-1"></i>Occupation</th>
                                    <th><i class="fas fa-graduation-cap me-1"></i>Education</th>
                                    <th><i class="fas fa-heartbeat me-1"></i>Health Priority Groups</th>
                                    <th><i class="fas fa-home me-1"></i>Utilities</th>
                                    <th><i class="fas fa-cogs me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td title="<?php echo htmlspecialchars($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']); ?>">
                                                <div class="fw-bold">
                                                    <?php echo htmlspecialchars($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <i class="fas fa-<?php echo $row['gender'] === 'Male' ? 'mars text-primary' : 'venus text-danger'; ?>" title="<?php echo $row['gender']; ?>"></i>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?php echo $row['age']; ?> years</div>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($row['birthday'])); ?></small>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo htmlspecialchars($row['purok']); ?></span></td>
                                            <td title="<?php echo htmlspecialchars($row['occupation'] ?: 'N/A'); ?>">
                                                <div class="text-truncate">
                                                    <?php echo htmlspecialchars($row['occupation'] ?: '-'); ?>
                                                </div>
                                            </td>
                                            <td title="<?php echo htmlspecialchars($row['education'] ?: 'N/A'); ?>">
                                                <div class="text-truncate">
                                                    <?php echo htmlspecialchars($row['education'] ?: '-'); ?>
                                                </div>
                                            </td>
                                            <td class="allow-wrap">
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php if ($row['is_senior']): ?>
                                                        <span class="badge bg-warning text-dark fw-normal" title="Senior Citizen (60+ years old)">Senior</span>
                                                    <?php endif; ?>
                                                    <?php if ($row['is_pwd']): ?>
                                                        <span class="badge bg-info" title="Person with Disability">PWD</span>
                                                    <?php endif; ?>
                                                    <?php if ($row['family_planning'] === 'Yes'): ?>
                                                        <span class="badge bg-success" title="Participates in Family Planning Program">Family Planning</span>
                                                    <?php endif; ?>
                                                    <?php if (!$row['is_senior'] && !$row['is_pwd'] && $row['family_planning'] !== 'Yes'): ?>
                                                        <span class="text-muted small">Regular</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="allow-wrap">
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php if ($row['has_electricity']): ?>
                                                        <span class="badge bg-success" title="Has Electricity Connection" style="min-width: 45px;">Elec</span>
                                                    <?php endif; ?>
                                                    <?php if ($row['has_poso']): ?>
                                                        <span class="badge bg-primary" title="Has Water Well/Poso" style="min-width: 45px;">Well</span>
                                                    <?php endif; ?>
                                                    <?php if ($row['has_nawasa']): ?>
                                                        <span class="badge bg-info" title="Has Water District Connection (NAWASA)" style="min-width: 45px;">Water</span>
                                                    <?php endif; ?>
                                                    <?php if ($row['has_cr']): ?>
                                                        <span class="badge bg-warning text-dark" title="Has Sanitation Facility" style="min-width: 45px;">Toilet</span>
                                                    <?php endif; ?>
                                                    <?php if (!$row['has_electricity'] && !$row['has_poso'] && !$row['has_nawasa'] && !$row['has_cr']): ?>
                                                        <span class="text-muted small">None</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="resident-actions-container">
                                                    <button type="button" class="btn btn-sm resident-btn-view" 
                                                            onclick="viewResident(<?php echo $row['id']; ?>)" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm resident-btn-edit" 
                                                            onclick="editResident(<?php echo $row['id']; ?>)" title="Edit Resident">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm resident-btn-archive" 
                                                            onclick="archiveResident(<?php echo $row['id']; ?>)" title="Archive Resident">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No residents found</h5>
                                            <p class="text-muted">Try adjusting your filters or add new residents.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <?php
            // Build query string without page parameter
            $query_params = $_GET;
            unset($query_params['page']);
            $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
            ?>
            <div class="pagination-wrapper mb-5">
                <nav aria-label="Residents pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $query_string; ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        // Show first page
                        if ($page > 3): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo $query_string; ?>">1</a>
                            </li>
                            <?php if ($page > 4): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php 
                        // Show pages around current page
                        for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $query_string; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php 
                        // Show last page
                        if ($page < $total_pages - 2): ?>
                            <?php if ($page < $total_pages - 3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $query_string; ?>"><?php echo $total_pages; ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $query_string; ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <div class="text-center mt-2">
                        <small class="text-muted">
                            Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total_residents); ?> of <?php echo $total_residents; ?> residents
                        </small>
                    </div>
                </nav>
            </div>
        <?php endif; ?>
        
        <!-- Bottom Spacing -->
        <div class="mb-5 pb-4"></div>
    </div>

    <!-- Add Resident Modal -->
    <div class="modal fade" id="addResidentModal" tabindex="-1" aria-labelledby="addResidentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addResidentModalLabel">
                        <i class="fas fa-user-plus"></i> Add New Resident
                    </h5>
                </div>
                <form id="addResidentForm">
                    <div class="modal-body">
                        <!-- Personal Information Section -->
                        <div class="mb-4">
                            <h6 class="fw-semibold text-muted mb-3">Personal Information</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="first_name" class="form-label fw-semibold">
                                        <i class="fas fa-user me-1"></i>First Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="last_name" class="form-label fw-semibold">
                                        <i class="fas fa-user me-1"></i>Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="middle_name" class="form-label fw-semibold">
                                        <i class="fas fa-user me-1"></i>Middle Name
                                    </label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name">
                                </div>
                                <div class="col-md-4">
                                    <label for="gender" class="form-label fw-semibold">
                                        <i class="fas fa-venus-mars me-1"></i>Gender <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="birthday" class="form-label fw-semibold">
                                        <i class="fas fa-birthday-cake me-1"></i>Birthday <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="birthday" name="birthday" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="purok" class="form-label fw-semibold">
                                        <i class="fas fa-map-marker-alt me-1"></i>Purok <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="purok" name="purok" required>
                                        <option value="">Select Purok</option>
                                        <?php foreach ($puroks as $purok): ?>
                                            <option value="<?php echo htmlspecialchars($purok); ?>">
                                                <?php echo htmlspecialchars($purok); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Professional Information Section -->
                        <div class="mb-4">
                            <h6 class="fw-semibold text-muted mb-3">Professional Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="occupation" class="form-label fw-semibold">
                                        <i class="fas fa-briefcase me-1"></i>Occupation
                                    </label>
                                    <input type="text" class="form-control" id="occupation" name="occupation">
                                </div>
                                <div class="col-md-6">
                                    <label for="education" class="form-label fw-semibold">
                                        <i class="fas fa-graduation-cap me-1"></i>Education
                                    </label>
                                    <input type="text" class="form-control" id="education" name="education">
                                </div>
                            </div>
                        </div>

                        <!-- Health Priority Groups Section -->
                        <div class="mb-4">
                            <h6 class="fw-semibold text-muted mb-3">Health Priority Groups</h6>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_family_planning" name="family_planning" value="Yes">
                                    <label class="form-check-label" for="add_family_planning">
                                        <i class="fas fa-heart me-1"></i>Family Planning
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_is_senior" name="is_senior">
                                    <label class="form-check-label" for="add_is_senior">
                                        <i class="fas fa-user-friends me-1"></i>Senior Citizen
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_is_pwd" name="is_pwd">
                                    <label class="form-check-label" for="add_is_pwd">
                                        <i class="fas fa-wheelchair me-1"></i>Person with Disability (PWD)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Utilities & Facilities Section -->
                        <div class="mb-4">
                            <h6 class="fw-semibold text-muted mb-3">Utilities & Facilities</h6>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_has_electricity" name="has_electricity">
                                    <label class="form-check-label" for="add_has_electricity">
                                        <i class="fas fa-bolt me-1"></i>Electricity
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_has_poso" name="has_poso">
                                    <label class="form-check-label" for="add_has_poso">
                                        <i class="fas fa-water me-1"></i>Water Well (Poso)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_has_nawasa" name="has_nawasa">
                                    <label class="form-check-label" for="add_has_nawasa">
                                        <i class="fas fa-tint me-1"></i>Water District (NAWASA)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="add_has_cr" name="has_cr">
                                    <label class="form-check-label" for="add_has_cr">
                                        <i class="fas fa-toilet me-1"></i>Sanitation Facility (CR)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Save Resident
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Resident Modal -->
    <div class="modal fade" id="viewResidentModal" tabindex="-1" aria-labelledby="viewResidentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewResidentModalLabel">
                        <i class="fas fa-eye me-2"></i>Resident Details - <span id="viewResidentName"></span>
                    </h5>
                </div>
                <div class="modal-body">
                    <!-- Personal Information Section -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-primary">
                                        <i class="fas fa-user me-2"></i>Personal Information
                                    </h6>
                                    <div class="mb-2">
                                        <strong><i class="fas fa-id-card me-2 text-muted"></i>Full Name:</strong>
                                        <span id="view_full_name" class="ms-2"></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong><i class="fas fa-venus-mars me-2 text-muted"></i>Gender:</strong>
                                        <span id="view_gender" class="ms-2"></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong><i class="fas fa-birthday-cake me-2 text-muted"></i>Birthday:</strong>
                                        <span id="view_birthday" class="ms-2"></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong><i class="fas fa-calendar-alt me-2 text-muted"></i>Age:</strong>
                                        <span id="view_age" class="ms-2 badge bg-info"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-success">
                                        <i class="fas fa-briefcase me-2"></i>Professional Information
                                    </h6>
                                    <div class="mb-2">
                                        <strong><i class="fas fa-user-tie me-2 text-muted"></i>Occupation:</strong>
                                        <span id="view_occupation" class="ms-2"></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong><i class="fas fa-graduation-cap me-2 text-muted"></i>Education:</strong>
                                        <span id="view_education" class="ms-2"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Location Section -->
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title text-warning">
                                        <i class="fas fa-map-marker-alt me-2"></i>Location Information
                                    </h6>
                                    <div class="mb-2">
                                        <strong><i class="fas fa-home me-2 text-muted"></i>Purok:</strong>
                                        <span id="view_purok" class="ms-2"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Health Priority Groups Section -->
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title text-danger">
                                        <i class="fas fa-heartbeat me-2"></i>Health Priority Groups
                                    </h6>
                                    <div id="view_health_groups"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Utilities & Facilities Section -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title text-info">
                                        <i class="fas fa-home me-2"></i>Utilities & Facilities
                                    </h6>
                                    <div id="view_utilities"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Close
                    </button>
                    <button type="button" class="btn btn-primary" id="editFromView">
                        <i class="fas fa-edit me-2"></i> Edit Record
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Resident Modal -->
    <div class="modal fade" id="editResidentModal" tabindex="-1" aria-labelledby="editResidentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editResidentModalLabel">
                        <i class="fas fa-user-edit"></i> Edit Resident
                    </h5>
                </div>
                <form id="editResidentForm">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-body">
                        <!-- Personal Information Section -->
                        <div class="mb-4">
                            <h6 class="fw-semibold text-muted mb-3">Personal Information</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="edit_first_name" class="form-label fw-semibold">
                                        <i class="fas fa-user me-1"></i>First Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit_last_name" class="form-label fw-semibold">
                                        <i class="fas fa-user me-1"></i>Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit_middle_name" class="form-label fw-semibold">
                                        <i class="fas fa-user me-1"></i>Middle Name
                                    </label>
                                    <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
                                </div>
                                <div class="col-md-4">
                                    <label for="edit_gender" class="form-label fw-semibold">
                                        <i class="fas fa-venus-mars me-1"></i>Gender <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="edit_gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit_birthday" class="form-label fw-semibold">
                                        <i class="fas fa-birthday-cake me-1"></i>Birthday <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="edit_birthday" name="birthday" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="edit_purok" class="form-label fw-semibold">
                                        <i class="fas fa-map-marker-alt me-1"></i>Purok <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="edit_purok" name="purok" required>
                                        <option value="">Select Purok</option>
                                        <?php foreach ($puroks as $purok): ?>
                                            <option value="<?php echo htmlspecialchars($purok); ?>">
                                                <?php echo htmlspecialchars($purok); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Professional Information Section -->
                        <div class="mb-4">
                            <h6 class="fw-semibold text-muted mb-3">Professional Information</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="edit_occupation" class="form-label fw-semibold">
                                        <i class="fas fa-briefcase me-1"></i>Occupation
                                    </label>
                                    <input type="text" class="form-control" id="edit_occupation" name="occupation">
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_education" class="form-label fw-semibold">
                                        <i class="fas fa-graduation-cap me-1"></i>Education
                                    </label>
                                    <input type="text" class="form-control" id="edit_education" name="education">
                                </div>
                            </div>
                        </div>

                        <!-- Health Priority Groups Section -->
                        <div class="mb-4">
                            <h6 class="fw-semibold text-muted mb-3">Health Priority Groups</h6>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_family_planning" name="family_planning" value="Yes">
                                    <label class="form-check-label" for="edit_family_planning">
                                        <i class="fas fa-heart me-1"></i>Family Planning
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_senior" name="is_senior">
                                    <label class="form-check-label" for="edit_is_senior">
                                        <i class="fas fa-user-friends me-1"></i>Senior Citizen
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_is_pwd" name="is_pwd">
                                    <label class="form-check-label" for="edit_is_pwd">
                                        <i class="fas fa-wheelchair me-1"></i>Person with Disability (PWD)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Utilities & Facilities Section -->
                        <div class="mb-4">
                            <h6 class="fw-semibold text-muted mb-3">Utilities & Facilities</h6>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_has_electricity" name="has_electricity">
                                    <label class="form-check-label" for="edit_has_electricity">
                                        <i class="fas fa-bolt me-1"></i>Electricity
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_has_poso" name="has_poso">
                                    <label class="form-check-label" for="edit_has_poso">
                                        <i class="fas fa-water me-1"></i>Water Well (Poso)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_has_nawasa" name="has_nawasa">
                                    <label class="form-check-label" for="edit_has_nawasa">
                                        <i class="fas fa-tint me-1"></i>Water District (NAWASA)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="edit_has_cr" name="has_cr">
                                    <label class="form-check-label" for="edit_has_cr">
                                        <i class="fas fa-toilet me-1"></i>Sanitation Facility (CR)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Update Resident
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Force page reload on back button to prevent cached data
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                // Page was loaded from cache (back button), force reload
                window.location.reload();
            }
        });

        // Preserve scroll position when filters are applied
        function preserveScrollPosition() {
            sessionStorage.setItem('scrollPosition', window.scrollY);
        }

        // Restore scroll position after page load
        window.addEventListener('load', function() {
            const scrollPosition = sessionStorage.getItem('scrollPosition');
            if (scrollPosition) {
                window.scrollTo(0, parseInt(scrollPosition));
                sessionStorage.removeItem('scrollPosition');
            }
        });

        // Handle checkbox filtering with proper uncheck behavior
        function handleCheckboxChange(checkbox) {
            preserveScrollPosition();
            
            // Get current URL parameters
            const url = new URL(window.location);
            const params = new URLSearchParams(url.search);
            
            if (checkbox.checked) {
                // Add the parameter when checked
                params.set(checkbox.name, '1');
            } else {
                // Remove the parameter when unchecked
                params.delete(checkbox.name);
            }
            
            // Navigate to the new URL
            url.search = params.toString();
            window.location.href = url.toString();
        }

        // Add scroll preservation to all filter elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add custom handler to filter checkboxes (exclude modal checkboxes)
            const filterCheckboxes = document.querySelectorAll('input[type="checkbox"][name^="has_"]:not([id^="add_"]):not([id^="edit_"]), input[type="checkbox"][name^="filter_"]');
            filterCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    handleCheckboxChange(this);
                });
            });

            // Auto-submit for search input (with debounce)
            const searchInput = document.getElementById('search');
            let searchTimeout;
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        preserveScrollPosition();
                        searchInput.form.submit();
                    }, 500); // Wait 500ms after user stops typing
                });
            }

            // Auto-submit for purok and age group select dropdowns
            const filterPurok = document.getElementById('filter_purok');
            const filterAgeGroup = document.getElementById('filter_age_group');
            
            if (filterPurok) {
                filterPurok.addEventListener('change', function() {
                    preserveScrollPosition();
                    this.form.submit();
                });
            }
            
            if (filterAgeGroup) {
                filterAgeGroup.addEventListener('change', function() {
                    preserveScrollPosition();
                    this.form.submit();
                });
            }

            // Add to form submissions
            const forms = document.querySelectorAll('form[method="GET"]');
            forms.forEach(form => {
                form.addEventListener('submit', preserveScrollPosition);
            });
        });

        // Add resident form submission
        document.getElementById('addResidentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add_resident');
            
            fetch('residents.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Resident added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the resident.');
            });
        });

        // Edit resident form submission
        document.getElementById('editResidentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_resident');
            
            fetch('residents.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Resident updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the resident.');
            });
        });

        // View resident function
        function viewResident(id) {
            // Fetch resident data and populate view modal
            fetch(`get_resident.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const resident = data.resident;
                    
                    // Set modal title with resident name
                    const fullName = `${resident.first_name} ${resident.middle_name ? resident.middle_name + ' ' : ''}${resident.last_name}`;
                    document.getElementById('viewResidentName').textContent = fullName;
                    
                    // Populate personal information
                    document.getElementById('view_full_name').textContent = fullName;
                    document.getElementById('view_gender').textContent = resident.gender;
                    document.getElementById('view_birthday').textContent = new Date(resident.birthday).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                    document.getElementById('view_age').textContent = resident.age + ' years old';
                    
                    // Populate location
                    document.getElementById('view_purok').textContent = resident.purok;
                    
                    // Populate professional information
                    document.getElementById('view_occupation').textContent = resident.occupation || 'N/A';
                    document.getElementById('view_education').textContent = resident.education || 'N/A';
                    
                    // Populate health priority groups
                    let healthGroups = '';
                    if (resident.is_senior == 1) {
                        healthGroups += '<span class="badge bg-warning text-dark me-2 mb-2"><i class="fas fa-user-friends me-1"></i>Senior Citizen</span>';
                    }
                    if (resident.is_pwd == 1) {
                        healthGroups += '<span class="badge bg-info me-2 mb-2"><i class="fas fa-wheelchair me-1"></i>Person with Disability (PWD)</span>';
                    }
                    if (resident.family_planning === 'Yes') {
                        healthGroups += '<span class="badge bg-success me-2 mb-2"><i class="fas fa-heart me-1"></i>Family Planning</span>';
                    }
                    if (!healthGroups) {
                        healthGroups = '<span class="text-muted"><i class="fas fa-info-circle me-1"></i>None</span>';
                    }
                    document.getElementById('view_health_groups').innerHTML = healthGroups;
                    
                    // Populate utilities
                    let utilities = '';
                    if (resident.has_electricity == 1) {
                        utilities += '<span class="badge bg-success me-2 mb-2"><i class="fas fa-bolt me-1"></i>Electricity</span>';
                    }
                    if (resident.has_poso == 1) {
                        utilities += '<span class="badge bg-primary me-2 mb-2"><i class="fas fa-water me-1"></i>Water Well (Poso)</span>';
                    }
                    if (resident.has_nawasa == 1) {
                        utilities += '<span class="badge bg-info me-2 mb-2"><i class="fas fa-tint me-1"></i>Water District (NAWASA)</span>';
                    }
                    if (resident.has_cr == 1) {
                        utilities += '<span class="badge bg-warning text-dark me-2 mb-2"><i class="fas fa-toilet me-1"></i>Sanitation Facility (CR)</span>';
                    }
                    if (!utilities) {
                        utilities = '<span class="text-muted"><i class="fas fa-info-circle me-1"></i>None</span>';
                    }
                    document.getElementById('view_utilities').innerHTML = utilities;
                    
                    // Store resident ID for edit button
                    document.getElementById('editFromView').setAttribute('data-resident-id', resident.id);
                    
                    // Show modal
                    new bootstrap.Modal(document.getElementById('viewResidentModal')).show();
                } else {
                    alert('Error loading resident data: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while loading resident data.');
            });
        }
        
        // Handle edit from view modal
        document.addEventListener('DOMContentLoaded', function() {
            const editFromViewBtn = document.getElementById('editFromView');
            if (editFromViewBtn) {
                editFromViewBtn.addEventListener('click', function() {
                    const residentId = this.getAttribute('data-resident-id');
                    
                    // Close view modal
                    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewResidentModal'));
                    if (viewModal) {
                        viewModal.hide();
                    }
                    
                    // Wait for view modal to close, then open edit modal
                    setTimeout(function() {
                        editResident(residentId);
                    }, 300);
                });
            }
        });

        // Edit resident function
        function editResident(id) {
            // Fetch resident data and populate edit modal
            fetch(`get_resident.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const resident = data.resident;
                    
                    // Populate form fields
                    document.getElementById('edit_id').value = resident.id;
                    document.getElementById('edit_first_name').value = resident.first_name;
                    document.getElementById('edit_last_name').value = resident.last_name;
                    document.getElementById('edit_middle_name').value = resident.middle_name || '';
                    document.getElementById('edit_gender').value = resident.gender;
                    document.getElementById('edit_birthday').value = resident.birthday;
                    document.getElementById('edit_purok').value = resident.purok;
                    document.getElementById('edit_occupation').value = resident.occupation || '';
                    document.getElementById('edit_education').value = resident.education || '';
                    
                    // Set checkboxes
                    document.getElementById('edit_family_planning').checked = resident.family_planning === 'Yes';
                    document.getElementById('edit_is_senior').checked = resident.is_senior == 1;
                    document.getElementById('edit_is_pwd').checked = resident.is_pwd == 1;
                    document.getElementById('edit_has_electricity').checked = resident.has_electricity == 1;
                    document.getElementById('edit_has_poso').checked = resident.has_poso == 1;
                    document.getElementById('edit_has_nawasa').checked = resident.has_nawasa == 1;
                    document.getElementById('edit_has_cr').checked = resident.has_cr == 1;
                    
                    // Show modal
                    new bootstrap.Modal(document.getElementById('editResidentModal')).show();
                } else {
                    alert('Error loading resident data: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while loading resident data.');
            });
        }

        // Archive resident function
        function archiveResident(id) {
            if (confirm('Are you sure you want to archive this resident? The resident will be moved to the archive and can be restored later.')) {
                const formData = new FormData();
                formData.append('action', 'archive_resident');
                formData.append('id', id);
                
                fetch('residents.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Resident archived successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while archiving the resident.');
                });
            }
        }

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