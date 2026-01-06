<?php
// Prevent browser caching - force fresh page load
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Add new baby record
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_baby'])) {
    require_once 'config/database.php';
    require_once 'config/session.php';
    require_once 'config/functions.php';
    requireApproved();
    
    // Get form data
    $full_name = sanitize($_POST['full_name']);
    $date_of_birth = sanitize($_POST['date_of_birth']);
    $parent_guardian_name = sanitize($_POST['parent_guardian_name']);
    $contact_number = sanitize($_POST['contact_number']);
    $address = sanitize($_POST['address']);
    
    // Store current filter and search parameters
    $current_filter = isset($_POST['current_filter']) ? '?filter=' . $_POST['current_filter'] : '';
    $current_search = isset($_POST['current_search']) ? (!empty($current_filter) ? '&' : '?') . 'search=' . urlencode($_POST['current_search']) : '';
    
    // Add success/error parameter with proper ? or & prefix
    $redirect_base = "babies.php" . $current_filter . $current_search;
    $redirect_base .= (strpos($redirect_base, '?') === false) ? '?' : '&';
    
    // Validate input
    if (empty($full_name) || empty($date_of_birth) || empty($parent_guardian_name)) {
        header("Location: " . $redirect_base . "error=" . urlencode('Please fill in all required fields'));
        exit;
    } elseif (strtotime($date_of_birth) > time()) {
        header("Location: " . $redirect_base . "error=" . urlencode('Date of birth cannot be in the future'));
        exit;
    } elseif (!empty($contact_number) && strlen($contact_number) != 11) {
        header("Location: " . $redirect_base . "error=" . urlencode('Contact number must be exactly 11 digits'));
        exit;
    } elseif (!empty($contact_number) && !preg_match('/^09[0-9]{9}$/', $contact_number)) {
        header("Location: " . $redirect_base . "error=" . urlencode('Contact number must start with 09 and be 11 digits'));
        exit;
    } else {
        // Format date of birth
        $date_of_birth = formatDate($date_of_birth);
        
        // Insert into database using prepared statement
        $query = "INSERT INTO babies (full_name, date_of_birth, parent_guardian_name, contact_number, address) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssss", $full_name, $date_of_birth, $parent_guardian_name, $contact_number, $address);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: " . $redirect_base . "show_success_modal=1");
            exit;
        } else {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            header("Location: " . $redirect_base . "error=" . urlencode('Error: ' . $error));
            exit;
        }
    }
}

// Update baby record
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_baby'])) {
    require_once 'config/database.php';
    require_once 'config/session.php';
    require_once 'config/functions.php';
    requireApproved();
    
    $id = (int)$_POST['baby_id'];
    
    // Get form data
    $full_name = sanitize($_POST['full_name']);
    $date_of_birth = sanitize($_POST['date_of_birth']);
    $parent_guardian_name = sanitize($_POST['parent_guardian_name']);
    $contact_number = sanitize($_POST['contact_number']);
    $address = sanitize($_POST['address']);
    
    // Store current filter and search parameters
    $current_filter = isset($_POST['current_filter']) ? '?filter=' . $_POST['current_filter'] : '';
    $current_search = isset($_POST['current_search']) ? (!empty($current_filter) ? '&' : '?') . 'search=' . urlencode($_POST['current_search']) : '';
    
    // Add success/error parameter with proper ? or & prefix
    $redirect_base = "babies.php" . $current_filter . $current_search;
    $redirect_base .= (strpos($redirect_base, '?') === false) ? '?' : '&';
    
    // Validate input
    if (empty($full_name) || empty($date_of_birth) || empty($parent_guardian_name)) {
        header("Location: " . $redirect_base . "error=" . urlencode('Please fill in all required fields'));
        exit;
    } elseif (strtotime($date_of_birth) > time()) {
        header("Location: " . $redirect_base . "error=" . urlencode('Date of birth cannot be in the future'));
        exit;
    } elseif (!empty($contact_number) && strlen($contact_number) != 11) {
        header("Location: " . $redirect_base . "error=" . urlencode('Contact number must be exactly 11 digits'));
        exit;
    } elseif (!empty($contact_number) && !preg_match('/^09[0-9]{9}$/', $contact_number)) {
        header("Location: " . $redirect_base . "error=" . urlencode('Contact number must start with 09 and be 11 digits'));
        exit;
    } else {
        // Format date of birth
        $date_of_birth = formatDate($date_of_birth);
        
        // Update database using prepared statement
        $query = "UPDATE babies SET 
                  full_name = ?, 
                  date_of_birth = ?, 
                  parent_guardian_name = ?, 
                  contact_number = ?, 
                  address = ? 
                  WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sssssi", $full_name, $date_of_birth, $parent_guardian_name, $contact_number, $address, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: " . $redirect_base . "show_update_modal=1");
            exit;
        } else {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            header("Location: " . $redirect_base . "error=" . urlencode('Error: ' . $error));
            exit;
        }
    }
}

// Archive baby record
if (isset($_GET['delete_id'])) {
    require_once 'config/database.php';
    require_once 'config/session.php';
    require_once 'config/functions.php';
    require_once 'config/archive_functions.php';
    requireApproved();
    
    $id = (int)$_GET['delete_id'];
    
    // Store current filter and search parameters
    $current_filter = isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : '';
    $current_search = isset($_GET['search']) ? (!empty($current_filter) ? '&' : '?') . 'search=' . urlencode($_GET['search']) : '';
    
    // Add success/error parameter with proper ? or & prefix
    $redirect_base = "babies.php" . $current_filter . $current_search;
    $redirect_base .= (strpos($redirect_base, '?') === false) ? '?' : '&';
    
    try {
        archiveBaby($id, $_SESSION['user_id']);
        header("Location: " . $redirect_base . "show_archive_modal=1");
        exit;
    } catch (Exception $e) {
        header("Location: " . $redirect_base . "error=" . urlencode($e->getMessage()));
        exit;
    }
}

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';
requireApproved();

// Filter and search
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build query
$where_clauses = array();

if ($filter == 'infants') {
    $where_clauses[] = "TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) <= 12";
} elseif ($filter == 'toddlers') {
    $where_clauses[] = "TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) > 12";
} elseif ($filter == 'recent') {
    $where_clauses[] = "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

if (!empty($search)) {
    $where_clauses[] = "(full_name LIKE '%$search%' OR parent_guardian_name LIKE '%$search%')";
}

$where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get all babies
$query = "SELECT * FROM babies $where_clause ORDER BY full_name ASC";
$result = mysqli_query($conn, $query);

// Get baby statistics
$stats_query = "SELECT 
                COUNT(*) as total_babies,
                COUNT(CASE WHEN TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) <= 12 THEN 1 END) as infants,
                COUNT(CASE WHEN TIMESTAMPDIFF(MONTH, date_of_birth, CURDATE()) > 12 THEN 1 END) as toddlers
                FROM babies";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Baby Records Management</title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Navbar Styles -->
    <link rel="stylesheet" href="assets/css/navbar.css">
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/residents_dashboard.css">
    <!-- Babies Page Styles -->
    <link rel="stylesheet" href="assets/css/babies.css">
    <!-- Success/Error Messages Styles -->
    <link rel="stylesheet" href="assets/css/success-error_messages.css">
    
    <style>
        /* Style for booked time slots */
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
                        <i class="fas fa-baby me-2"></i>
                        Baby Records Management
                    </h1>
                    <p class="welcome-subtitle">Manage baby information, vaccination records, and health tracking for comprehensive pediatric care.</p>
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
                <i class="fas fa-chart-line me-2"></i>Baby Statistics Overview
            </h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-icon">
                                <i class="fas fa-baby"></i>
                            </div>
                            <h3 class="stats-number"><?php echo $stats['total_babies']; ?></h3>
                            <p class="stats-label">Total Babies</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-icon">
                                <i class="fas fa-baby-carriage"></i>
                            </div>
                            <h3 class="stats-number"><?php echo $stats['infants']; ?></h3>
                            <p class="stats-label">Infants (0-12 months)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stats-icon">
                                <i class="fas fa-child"></i>
                            </div>
                            <h3 class="stats-number"><?php echo $stats['toddlers']; ?></h3>
                            <p class="stats-label">Toddlers (1+ years)</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Baby Records List -->
        <section class="babies-list">
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-nowrap justify-content-start align-items-center">
                    <div class="d-flex flex-nowrap babies-header-buttons">
                        <button type="button" class="btn btn-secondary btn-sm babies-btn" onclick="window.history.back()" title="Go Back">
                            <i class="fas fa-arrow-left"></i><span class="d-none d-lg-inline ms-1"> Back</span>
                        </button>
                        <a href="archives.php?type=babies" class="btn btn-warning btn-sm babies-btn" title="View Archives">
                            <i class="fas fa-archive"></i><span class="d-none d-lg-inline ms-1"> Archives</span>
                        </a>
                        <button type="button" class="btn btn-primary btn-sm babies-btn" data-bs-toggle="modal" data-bs-target="#addBabyModal" title="Add New Baby">
                            <i class="fas fa-plus"></i><span class="d-none d-lg-inline ms-1"> Add Baby</span>
                        </button>
                    </div>
                </div>
                
                <!-- Filters inside table card -->
                <div class="card-body border-bottom">
                    <form action="babies.php" method="GET" class="row g-3" id="filterForm">
                        <div class="col-md-4">
                            <label for="search" class="form-label fw-semibold">
                                <i class="fas fa-search me-1"></i>Search Baby Records
                            </label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Baby name, parent/guardian name..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filter" class="form-label fw-semibold">
                                <i class="fas fa-filter me-1"></i>Filter by Category
                            </label>
                            <select class="form-select" id="filter" name="filter">
                                <option value="" <?php echo $filter == '' ? 'selected' : ''; ?>>All Babies</option>
                                <option value="infants" <?php echo $filter == 'infants' ? 'selected' : ''; ?>>Infants (0-12 months)</option>
                                <option value="toddlers" <?php echo $filter == 'toddlers' ? 'selected' : ''; ?>>Toddlers (1+ years)</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-center" style="padding-top: 2rem;">
                            <a href="babies.php" class="btn btn-outline-secondary btn-sm w-100" title="Reset Filters">
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
                                    <th><i class="fas fa-user me-1"></i>Name</th>
                                    <th><i class="fas fa-birthday-cake me-1"></i>Age</th>
                                    <th><i class="fas fa-calendar me-1"></i>Date of Birth</th>
                                    <th><i class="fas fa-users me-1"></i>Parent/Guardian</th>
                                    <th><i class="fas fa-phone me-1"></i>Contact</th>
                                    <th class="text-center"><i class="fas fa-cogs me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        echo "<tr class='baby-row'>";
                                        echo "<td class='py-3 px-4 align-middle'>";
                                        echo "<strong>" . htmlspecialchars($row['full_name']) . "</strong>";
                                        echo "</td>";
                                        echo "<td class='py-3 px-4 align-middle'>";
                                        echo getAge($row['date_of_birth']);
                                        echo "</td>";
                                        echo "<td class='py-3 px-4 align-middle'>";
                                        echo date('M d, Y', strtotime($row['date_of_birth']));
                                        echo "</td>";
                                        echo "<td class='py-3 px-4 align-middle'>";
                                        echo htmlspecialchars($row['parent_guardian_name']);
                                        echo "</td>";
                                        echo "<td class='py-3 px-4 align-middle'>";
                                        echo htmlspecialchars($row['contact_number']);
                                        echo "</td>";
                                        echo "<td>
                                                <div class='baby-actions-container'>
                                                    <button type='button' class='baby-btn-view' title='View Details'
                                                            data-bs-toggle='modal' data-bs-target='#viewBabyModal'
                                                            data-id='{$row['id']}'
                                                            data-name='" . htmlspecialchars($row['full_name']) . "'
                                                            data-dob='{$row['date_of_birth']}'
                                                            data-parent='" . htmlspecialchars($row['parent_guardian_name']) . "'
                                                            data-contact='" . htmlspecialchars($row['contact_number']) . "'
                                                            data-address='" . htmlspecialchars($row['address']) . "'
                                                            data-age='" . getAge($row['date_of_birth']) . "'
                                                            data-created='" . (isset($row['created_at']) ? date('M d, Y g:i A', strtotime($row['created_at'])) : 'N/A') . "'>
                                                        <i class='fas fa-eye'></i>
                                                    </button>
                                                    <button type='button' class='baby-btn-edit' title='Edit' 
                                                            data-bs-toggle='modal' data-bs-target='#editBabyModal'
                                                            data-id='{$row['id']}'
                                                            data-name='" . htmlspecialchars($row['full_name']) . "'
                                                            data-dob='{$row['date_of_birth']}'
                                                            data-parent='" . htmlspecialchars($row['parent_guardian_name']) . "'
                                                            data-contact='" . htmlspecialchars($row['contact_number']) . "'
                                                            data-address='" . htmlspecialchars($row['address']) . "'>
                                                        <i class='fas fa-edit'></i>
                                                    </button>
                                                    <a href='babies.php?delete_id={$row['id']}' class='baby-btn-delete' title='Archive' onclick='return confirm(\"Are you sure you want to archive this baby record?\")'>
                                                        <i class='fas fa-archive'></i>
                                                    </a>
                                                    <button type='button' class='baby-btn-vaccination' title='Schedule Vaccination'
                                                            data-bs-toggle='modal' data-bs-target='#addVaccinationModal'
                                                            data-baby-id='{$row['id']}'
                                                            data-baby-name='" . htmlspecialchars($row['full_name']) . "'>
                                                        <i class='fas fa-syringe'></i>
                                                    </button>
                                                </div>
                                              </td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center py-4'>
                                            <div class='text-muted'>
                                                <i class='fas fa-baby fa-3x mb-3 d-block'></i>
                                                <h5>No baby records found</h5>
                                                <p>Try adjusting your search criteria or add a new baby record.</p>
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
        
        <!-- Bottom spacing -->
        <div style="margin-bottom: 50px;"></div>
    </div>

    <!-- Add Baby Modal -->
    <div class="modal fade" id="addBabyModal" tabindex="-1" aria-labelledby="addBabyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBabyModalLabel">
                        <i class="fas fa-baby me-2"></i>Add New Baby Record
                    </h5>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="addBabyForm">
                        <input type="hidden" name="add_baby" value="1">
                        <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <input type="hidden" name="current_search" value="<?php echo htmlspecialchars($search); ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">
                                    <i class="fas fa-user me-2"></i>Baby's Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       placeholder="Enter baby's complete name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="date_of_birth" class="form-label">
                                    <i class="fas fa-birthday-cake me-2"></i>Date of Birth <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                       max="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="parent_guardian_name" class="form-label">
                                    <i class="fas fa-users me-2"></i>Parent/Guardian Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="parent_guardian_name" name="parent_guardian_name" 
                                       placeholder="Enter parent or guardian's full name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_number" class="form-label">
                                    <i class="fas fa-phone me-2"></i>Contact Number
                                </label>
                                <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                       placeholder="09XXXXXXXXX" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="11">
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>Format: 09XXXXXXXXX (11 digits)
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">
                                <i class="fas fa-map-marker-alt me-2"></i>Address
                            </label>
                            <textarea class="form-control" id="address" name="address" rows="3" 
                                      placeholder="Enter complete address (Barangay, Municipality, Province)"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" id="saveBabyBtn" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Baby Record
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Baby Modal -->
    <div class="modal fade" id="editBabyModal" tabindex="-1" aria-labelledby="editBabyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBabyModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Baby Record - <span id="editBabyName"></span>
                    </h5>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="editBabyForm">
                        <input type="hidden" name="update_baby" value="1">
                        <input type="hidden" name="baby_id" id="editBabyId">
                        <input type="hidden" name="current_filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <input type="hidden" name="current_search" value="<?php echo htmlspecialchars($search); ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_full_name" class="form-label">
                                    <i class="fas fa-user me-2"></i>Baby's Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="edit_full_name" name="full_name" 
                                       placeholder="Enter baby's complete name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_date_of_birth" class="form-label">
                                    <i class="fas fa-birthday-cake me-2"></i>Date of Birth <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="edit_date_of_birth" name="date_of_birth" 
                                       max="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_parent_guardian_name" class="form-label">
                                    <i class="fas fa-users me-2"></i>Parent/Guardian Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="edit_parent_guardian_name" name="parent_guardian_name" 
                                       placeholder="Enter parent or guardian's full name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_contact_number" class="form-label">
                                    <i class="fas fa-phone me-2"></i>Contact Number
                                </label>
                                <input type="text" class="form-control" id="edit_contact_number" name="contact_number" 
                                       placeholder="09XXXXXXXXX" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="11">
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>Format: 09XXXXXXXXX (11 digits)
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_address" class="form-label">
                                <i class="fas fa-map-marker-alt me-2"></i>Address
                            </label>
                            <textarea class="form-control" id="edit_address" name="address" rows="3" 
                                      placeholder="Enter complete address (Barangay, Municipality, Province)"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" id="updateBabyBtn" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Baby Record
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Baby Details Modal -->
    <div class="modal fade" id="viewBabyModal" tabindex="-1" aria-labelledby="viewBabyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewBabyModalLabel">
                        <i class="fas fa-eye me-2"></i>Baby Details - <span id="viewBabyName"></span>
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-primary">
                                        <i class="fas fa-user me-2"></i>Personal Information
                                    </h6>
                                    <div class="mb-2">
                                        <strong>Full Name:</strong>
                                        <span id="viewFullName" class="ms-2"></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Date of Birth:</strong>
                                        <span id="viewDateOfBirth" class="ms-2"></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Age:</strong>
                                        <span id="viewAge" class="ms-2 badge bg-info"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title text-success">
                                        <i class="fas fa-users me-2"></i>Guardian Information
                                    </h6>
                                    <div class="mb-2">
                                        <strong>Parent/Guardian:</strong>
                                        <span id="viewParentName" class="ms-2"></span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Contact Number:</strong>
                                        <span id="viewContactNumber" class="ms-2"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title text-warning">
                                        <i class="fas fa-map-marker-alt me-2"></i>Address Information
                                    </h6>
                                    <div class="mb-2">
                                        <strong>Address:</strong>
                                        <span id="viewAddress" class="ms-2"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title text-secondary">
                                        <i class="fas fa-info-circle me-2"></i>Record Information
                                    </h6>
                                    <div class="mb-2">
                                        <strong>Record Created:</strong>
                                        <span id="viewCreatedAt" class="ms-2"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" id="editFromView">
                        <i class="fas fa-edit me-2"></i>Edit Record
                    </button>
                    <button type="button" id="scheduleVaccinationFromView" class="btn btn-success"
                            data-bs-toggle="modal" data-bs-target="#addVaccinationModal">
                        <i class="fas fa-syringe me-2"></i>Schedule Vaccination
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Vaccination Modal -->
    <div class="modal fade" id="addVaccinationModal" tabindex="-1" aria-labelledby="addVaccinationModalLabel" aria-hidden="true">
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
                                    <i class="fas fa-calendar-alt me-2"></i>Schedule Date <span class="text-danger">*</span>
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
                    <button type="submit" form="addVaccinationForm" class="btn btn-primary">
                        <i class="fas fa-calendar-check me-2"></i>Schedule Vaccination
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal (Add) -->
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
                <p class="message-modal-message">Baby record has been added successfully.</p>
                <div class="message-modal-actions">
                    <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="closeSuccessModal()">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Success Modal -->
    <div id="updateSuccessModal" class="message-modal">
        <div class="message-modal-content message-modal-success">
            <div class="message-modal-header">
                <button type="button" class="message-modal-close" onclick="closeUpdateSuccessModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="message-modal-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3 class="message-modal-title">Updated!</h3>
            </div>
            <div class="message-modal-body">
                <p class="message-modal-message">Baby record has been updated successfully.</p>
                <div class="message-modal-actions">
                    <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="closeUpdateSuccessModal()">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Archive Success Modal -->
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
                <p class="message-modal-message">Baby record has been archived successfully.</p>
                <div class="message-modal-actions">
                    <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="closeArchiveModal()">
                        <i class="fas fa-check me-2"></i>OK
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
        // Maintain scroll position during search
        document.addEventListener('DOMContentLoaded', function() {
            // Store scroll position before form submission
            const searchForm = document.querySelector('form[action="babies.php"]');
            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    const searchSection = document.getElementById('search-section');
                    if (searchSection) {
                        const scrollPosition = searchSection.offsetTop - 20;
                        sessionStorage.setItem('searchScrollPosition', scrollPosition);
                    }
                });
            }
            
            // Restore scroll position after page load
            const savedPosition = sessionStorage.getItem('searchScrollPosition');
            if (savedPosition && window.location.hash === '#search-section') {
                // Remove the hash to prevent default scrolling
                history.replaceState(null, null, window.location.pathname + window.location.search);
                // Set scroll position immediately without animation
                window.scrollTo(0, parseInt(savedPosition));
                // Clear the stored position
                sessionStorage.removeItem('searchScrollPosition');
            }

            // Check if we should show success modal
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('show_success_modal')) {
                const successModal = document.getElementById('successModal');
                if (successModal) {
                    successModal.classList.add('show');
                    // Remove the parameter from URL without reloading
                    urlParams.delete('show_success_modal');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    history.replaceState(null, '', newUrl);
                }
            }

            // Check if we should show update success modal
            if (urlParams.has('show_update_modal')) {
                const updateSuccessModal = document.getElementById('updateSuccessModal');
                if (updateSuccessModal) {
                    updateSuccessModal.classList.add('show');
                    // Remove the parameter from URL without reloading
                    urlParams.delete('show_update_modal');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    history.replaceState(null, '', newUrl);
                }
            }

            // Check if we should show archive modal
            if (urlParams.has('show_archive_modal')) {
                const archiveModal = document.getElementById('archiveModal');
                if (archiveModal) {
                    archiveModal.classList.add('show');
                    // Remove the parameter from URL without reloading
                    urlParams.delete('show_archive_modal');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    history.replaceState(null, '', newUrl);
                }
            }

            // Modal form handling
            const addBabyModal = document.getElementById('addBabyModal');
            const addBabyForm = document.getElementById('addBabyForm');
            const editBabyModal = document.getElementById('editBabyModal');
            const editBabyForm = document.getElementById('editBabyForm');
            const contactInput = document.getElementById('contact_number');
            const editContactInput = document.getElementById('edit_contact_number');
            const dobInput = document.getElementById('date_of_birth');
            const editDobInput = document.getElementById('edit_date_of_birth');
            const saveBabyBtn = document.getElementById('saveBabyBtn');
            const updateBabyBtn = document.getElementById('updateBabyBtn');
            const successModal = document.getElementById('successModal');

            // Handle save baby button click
            if (saveBabyBtn) {
                saveBabyBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Get form fields
                    const fullName = document.getElementById('full_name').value.trim();
                    const dateOfBirth = document.getElementById('date_of_birth').value;
                    const parentGuardian = document.getElementById('parent_guardian_name').value.trim();
                    const contactNumber = document.getElementById('contact_number').value.trim();
                    
                    // Validate required fields
                    if (!fullName || !dateOfBirth || !parentGuardian) {
                        // Trigger HTML5 validation
                        addBabyForm.reportValidity();
                        return;
                    }
                    
                    // Validate date of birth is not in the future
                    const dobDate = new Date(dateOfBirth);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (dobDate > today) {
                        alert('Date of birth cannot be in the future');
                        return;
                    }
                    
                    // Validate contact number if provided
                    if (contactNumber) {
                        if (contactNumber.length !== 11) {
                            alert('Contact number must be exactly 11 digits');
                            return;
                        }
                        if (!contactNumber.match(/^09[0-9]{9}$/)) {
                            alert('Contact number must start with 09 and be 11 digits');
                            return;
                        }
                    }
                    
                    // All validation passed, submit the form
                    addBabyForm.submit();
                });
            }

            // Handle update baby button click
            if (updateBabyBtn) {
                updateBabyBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Get form fields
                    const fullName = document.getElementById('edit_full_name').value.trim();
                    const dateOfBirth = document.getElementById('edit_date_of_birth').value;
                    const parentGuardian = document.getElementById('edit_parent_guardian_name').value.trim();
                    const contactNumber = document.getElementById('edit_contact_number').value.trim();
                    
                    // Validate required fields
                    if (!fullName || !dateOfBirth || !parentGuardian) {
                        // Trigger HTML5 validation
                        editBabyForm.reportValidity();
                        return;
                    }
                    
                    // Validate date of birth is not in the future
                    const dobDate = new Date(dateOfBirth);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (dobDate > today) {
                        alert('Date of birth cannot be in the future');
                        return;
                    }
                    
                    // Validate contact number if provided
                    if (contactNumber) {
                        if (contactNumber.length !== 11) {
                            alert('Contact number must be exactly 11 digits');
                            return;
                        }
                        if (!contactNumber.match(/^09[0-9]{9}$/)) {
                            alert('Contact number must start with 09 and be 11 digits');
                            return;
                        }
                    }
                    
                    // All validation passed, submit the form
                    editBabyForm.submit();
                });
            }

            // Set maximum date to today for date of birth
            if (dobInput) {
                dobInput.max = new Date().toISOString().split('T')[0];
            }
            if (editDobInput) {
                editDobInput.max = new Date().toISOString().split('T')[0];
            }

            // Auto-format contact number for add form
            if (contactInput) {
                contactInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                    e.target.value = value;
                });
            }

            // Auto-format contact number for edit form
            if (editContactInput) {
                editContactInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                    e.target.value = value;
                });
            }

            // Reset form when add modal is closed
            if (addBabyModal) {
                addBabyModal.addEventListener('hidden.bs.modal', function() {
                    if (addBabyForm) {
                        addBabyForm.reset();
                    }
                });
            }

            // Handle edit modal data population
            if (editBabyModal) {
                editBabyModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    
                    // Get data from button attributes
                    const babyId = button.getAttribute('data-id');
                    const babyName = button.getAttribute('data-name');
                    const babyDob = button.getAttribute('data-dob');
                    const parentName = button.getAttribute('data-parent');
                    const contactNumber = button.getAttribute('data-contact');
                    const address = button.getAttribute('data-address');
                    
                    // Populate modal fields
                    document.getElementById('editBabyId').value = babyId;
                    document.getElementById('editBabyName').textContent = babyName;
                    document.getElementById('edit_full_name').value = babyName;
                    document.getElementById('edit_date_of_birth').value = babyDob;
                    document.getElementById('edit_parent_guardian_name').value = parentName;
                    document.getElementById('edit_contact_number').value = contactNumber || '';
                    document.getElementById('edit_address').value = address || '';
                });
            }

            // Handle view modal data population
            const viewBabyModal = document.getElementById('viewBabyModal');
            if (viewBabyModal) {
                viewBabyModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    
                    // Get data from button attributes
                    const babyId = button.getAttribute('data-id');
                    const babyName = button.getAttribute('data-name');
                    const babyDob = button.getAttribute('data-dob');
                    const parentName = button.getAttribute('data-parent');
                    const contactNumber = button.getAttribute('data-contact');
                    const address = button.getAttribute('data-address');
                    const age = button.getAttribute('data-age');
                    const createdAt = button.getAttribute('data-created');
                    
                    // Populate modal fields
                    document.getElementById('viewBabyName').textContent = babyName;
                    document.getElementById('viewFullName').textContent = babyName;
                    document.getElementById('viewDateOfBirth').textContent = new Date(babyDob).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    document.getElementById('viewAge').textContent = age;
                    document.getElementById('viewParentName').textContent = parentName;
                    document.getElementById('viewContactNumber').textContent = contactNumber || 'Not provided';
                    document.getElementById('viewAddress').textContent = address || 'Not provided';
                    document.getElementById('viewCreatedAt').textContent = createdAt;
                    
                    // Update action buttons
                    document.getElementById('editFromView').setAttribute('data-baby-id', babyId);
                    document.getElementById('editFromView').setAttribute('data-name', babyName);
                    document.getElementById('editFromView').setAttribute('data-dob', babyDob);
                    document.getElementById('editFromView').setAttribute('data-parent', parentName);
                    document.getElementById('editFromView').setAttribute('data-contact', contactNumber);
                    document.getElementById('editFromView').setAttribute('data-address', address);
                    
                    document.getElementById('scheduleVaccinationFromView').setAttribute('data-baby-id', babyId);
                    document.getElementById('scheduleVaccinationFromView').setAttribute('data-baby-name', babyName);
                });
            }

            // Handle edit from view modal
            const editFromViewBtn = document.getElementById('editFromView');
            if (editFromViewBtn) {
                editFromViewBtn.addEventListener('click', function() {
                    // Close view modal
                    const viewModal = bootstrap.Modal.getInstance(viewBabyModal);
                    viewModal.hide();
                    
                    // Wait for view modal to close, then open edit modal
                    setTimeout(function() {
                        // Get data from button attributes
                        const babyId = editFromViewBtn.getAttribute('data-baby-id');
                        const babyName = editFromViewBtn.getAttribute('data-name');
                        const babyDob = editFromViewBtn.getAttribute('data-dob');
                        const parentName = editFromViewBtn.getAttribute('data-parent');
                        const contactNumber = editFromViewBtn.getAttribute('data-contact');
                        const address = editFromViewBtn.getAttribute('data-address');
                        
                        // Populate edit modal fields
                        document.getElementById('editBabyId').value = babyId;
                        document.getElementById('editBabyName').textContent = babyName;
                        document.getElementById('edit_full_name').value = babyName;
                        document.getElementById('edit_date_of_birth').value = babyDob;
                        document.getElementById('edit_parent_guardian_name').value = parentName;
                        document.getElementById('edit_contact_number').value = contactNumber || '';
                        document.getElementById('edit_address').value = address || '';
                        
                        // Show edit modal
                        const editModal = new bootstrap.Modal(editBabyModal);
                        editModal.show();
                    }, 300);
                });
            }

            // Handle vaccination modal
            const addVaccinationModal = document.getElementById('addVaccinationModal');
            const scheduleDate = document.getElementById('schedule_date');
            
            if (addVaccinationModal) {
                addVaccinationModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    
                    // Set minimum date and default value
                    const today = new Date().toISOString().split('T')[0];
                    scheduleDate.min = today;
                    if (!scheduleDate.value) {
                        scheduleDate.value = today;
                    }
                    
                    // Pre-select baby if coming from a specific baby row
                    if (button && button.hasAttribute('data-baby-id')) {
                        const babyId = button.getAttribute('data-baby-id');
                        const babySelect = document.getElementById('baby_id');
                        if (babySelect && babyId) {
                            babySelect.value = babyId;
                        }
                    }
                });

                // Reset form when modal closes
                addVaccinationModal.addEventListener('hidden.bs.modal', function() {
                    document.getElementById('addVaccinationForm').reset();
                });
            }

            // Handle vaccination from view modal
            const scheduleVaccinationFromView = document.getElementById('scheduleVaccinationFromView');
            if (scheduleVaccinationFromView) {
                scheduleVaccinationFromView.addEventListener('click', function() {
                    // Close view modal first
                    const viewModal = bootstrap.Modal.getInstance(viewBabyModal);
                    if (viewModal) {
                        viewModal.hide();
                    }
                    
                    // Wait for view modal to close, then open vaccination modal
                    setTimeout(() => {
                        const babyId = this.getAttribute('data-baby-id');
                        
                        // Set minimum date and default value
                        const today = new Date().toISOString().split('T')[0];
                        scheduleDate.min = today;
                        scheduleDate.value = today;
                        
                        // Pre-select the baby
                        const babySelect = document.getElementById('baby_id');
                        if (babySelect && babyId) {
                            babySelect.value = babyId;
                        }
                        
                        // Open vaccination modal
                        const vaccinationModalElement = document.getElementById('addVaccinationModal');
                        let vaccinationModal = bootstrap.Modal.getInstance(vaccinationModalElement);
                        if (!vaccinationModal) {
                            vaccinationModal = new bootstrap.Modal(vaccinationModalElement);
                        }
                        vaccinationModal.show();
                        
                        // Check booked times after modal is shown
                        setTimeout(() => {
                            updateVaccinationTimeSlots();
                        }, 100);
                    }, 300);
                });
            }
            
            // Add event listener to schedule_date to update time slots when date changes
            if (scheduleDate) {
                scheduleDate.addEventListener('change', function() {
                    updateVaccinationTimeSlots();
                });
            }
        });
        
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
                        option.text = option.text.replace(' (Booked)', '');
                    }
                });
                return;
            }
            
            // Get booked times for selected date
            const bookedTimes = await checkBookedVaccinationTimes(selectedDate);
            
            // Update time options
            Array.from(timeSelect.options).forEach(option => {
                if (option.value) {
                    const isBooked = bookedTimes.includes(option.value);
                    option.disabled = isBooked;
                    
                    // Update option text
                    const baseText = option.text.replace(' (Booked)', '');
                    option.text = isBooked ? baseText + ' (Booked)' : baseText;
                }
            });
        }

        // Success modal functions
        function closeSuccessModal() {
            const successModal = document.getElementById('successModal');
            successModal.classList.remove('show');
        }

        function submitBabyForm() {
            // Just close the modal, the record is already saved
            closeSuccessModal();
        }

        function closeUpdateSuccessModal() {
            const updateSuccessModal = document.getElementById('updateSuccessModal');
            updateSuccessModal.classList.remove('show');
        }

        function closeArchiveModal() {
            const archiveModal = document.getElementById('archiveModal');
            archiveModal.classList.remove('show');
        }

        // Auto-submit for search input (with debounce)
        const searchInput = document.getElementById('search');
        let searchTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    document.getElementById('filterForm').submit();
                }, 500); // Wait 500ms after user stops typing
            });
        }

        // Auto-submit for filter dropdown
        const filterSelect = document.getElementById('filter');
        if (filterSelect) {
            filterSelect.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
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