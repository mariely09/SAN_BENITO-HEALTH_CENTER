<?php
// Prevent browser caching - force fresh page load
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';
require_once 'config/archive_functions.php';

// Archive medicine (instead of deleting)
if (isset($_GET['delete_id'])) {
    requireApproved();
    
    $id = (int)$_GET['delete_id'];
    
    // Store current filter and search parameters
    $current_filter = isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : '';
    $current_search = isset($_GET['search']) ? (!empty($current_filter) ? '&' : '?') . 'search=' . urlencode($_GET['search']) : '';
    
    // Add success/error parameter with proper ? or & prefix
    $redirect_base = "medicines.php" . $current_filter . $current_search;
    $redirect_base .= (strpos($redirect_base, '?') === false) ? '?' : '&';
    
    try {
        archiveMedicine($id, $_SESSION['user_id']);
        header("Location: " . $redirect_base . "success=" . urlencode("Medicine archived successfully"));
        exit;
    } catch (Exception $e) {
        error_log("Archive medicine error: " . $e->getMessage());
        header("Location: " . $redirect_base . "error=" . urlencode($e->getMessage()));
        exit;
    }
}

requireApproved();

// Ensure required columns exist in medicines table
$check_dosage_query = "SHOW COLUMNS FROM medicines LIKE 'dosage'";
$check_result = mysqli_query($conn, $check_dosage_query);
if (mysqli_num_rows($check_result) == 0) {
    $add_dosage_query = "ALTER TABLE medicines ADD COLUMN dosage VARCHAR(100) DEFAULT NULL AFTER medicine_name";
    if (mysqli_query($conn, $add_dosage_query)) {
        error_log("Added dosage column to medicines table during initialization");
    } else {
        error_log("Failed to add dosage column during initialization: " . mysqli_error($conn));
    }
}

$check_date_query = "SHOW COLUMNS FROM medicines LIKE 'date_added'";
$check_result = mysqli_query($conn, $check_date_query);
if (mysqli_num_rows($check_result) == 0) {
    $add_date_query = "ALTER TABLE medicines ADD COLUMN date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    if (mysqli_query($conn, $add_date_query)) {
        error_log("Added date_added column to medicines table during initialization");
    } else {
        error_log("Failed to add date_added column during initialization: " . mysqli_error($conn));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Inventory Management</title>
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
    <!-- Medicines Styles -->
    <link rel="stylesheet" href="assets/css/medicines.css">
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
                        <i class="fas fa-pills me-2 text-success"></i>
                        Medicine Inventory Management
                    </h1>
                    <p class="welcome-subtitle">Monitor and manage medicine stock levels, expiry dates, and inventory tracking.</p>
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

<?php

// Filter query
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where_clause = "";
$params = array();

if ($filter == 'low_stock') {
    $where_clause = "WHERE quantity <= low_stock_threshold";
} elseif ($filter == 'expired') {
    $where_clause = "WHERE expiry_date < CURDATE()";
} elseif ($filter == 'expiring_soon') {
    $where_clause = "WHERE expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}

if (!empty($search)) {
    if (!empty($where_clause)) {
        $where_clause .= " AND (medicine_name LIKE '%$search%' OR batch_number LIKE '%$search%')";
    } else {
        $where_clause = "WHERE medicine_name LIKE '%$search%' OR batch_number LIKE '%$search%'";
    }
}

// Get all medicines
$query = "SELECT * FROM medicines $where_clause ORDER BY 
          CASE WHEN quantity <= low_stock_threshold THEN 0 ELSE 1 END, 
          CASE WHEN expiry_date < CURDATE() THEN 0 
               WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 
               ELSE 2 END, 
          medicine_name ASC";
$result = mysqli_query($conn, $query);
?>

        <!-- Medicines List -->
        <section class="medicines-list">
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-nowrap justify-content-start align-items-center" style="background: linear-gradient(135deg, #2c3e50, #34495e) !important; color: white; border-bottom: 3px solid #27ae60; padding: 1.5rem 2rem;">
                    <div class="d-flex flex-nowrap medicines-header-buttons">
                        <button type="button" class="btn btn-secondary btn-sm medicines-btn" onclick="window.history.back()" title="Go Back">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </button>
                        <?php if (isAdmin() || isWorker()): ?>
                            <a href="archives.php?type=medicines" class="btn btn-warning btn-sm medicines-btn" title="View Archives">
                                <i class="fas fa-archive me-1"></i> Archives
                            </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-primary btn-sm medicines-btn" data-bs-toggle="modal" data-bs-target="#addMedicineModal" title="Add New Medicine">
                            <i class="fas fa-plus me-1"></i> Add Medicine
                        </button>
                    </div>
                </div>
            
                <!-- Filters inside table card -->
                <div class="card-body border-bottom">
                    <form action="medicines.php" method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label fw-semibold">
                                <i class="fas fa-search me-1"></i>Search Medicines
                            </label>
                            <input type="text" class="form-control" id="search" name="search"
                                placeholder="Medicine name or batch number..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filter" class="form-label fw-semibold">
                                <i class="fas fa-filter me-1"></i>Filter by Status
                            </label>
                            <select class="form-select" id="filter" name="filter">
                                <option value="" <?php echo $filter == '' ? 'selected' : ''; ?>>All Medicines</option>
                                <option value="low_stock" <?php echo $filter == 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                                <option value="expired" <?php echo $filter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="expiring_soon" <?php echo $filter == 'expiring_soon' ? 'selected' : ''; ?>>Expiring Soon</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-center" style="padding-top: 2rem;">
                            <a href="medicines.php" class="btn btn-outline-secondary btn-sm w-100" title="Reset Filters">
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
                                    <th><i class="fas fa-pills me-1"></i>Medicine Name</th>
                                    <th><i class="fas fa-prescription-bottle me-1"></i>Dosage</th>
                                    <th><i class="fas fa-boxes me-1"></i>Quantity</th>
                                    <th><i class="fas fa-barcode me-1"></i>Batch Number</th>
                                    <th><i class="fas fa-calendar-times me-1"></i>Expiry Date</th>
                                    <th><i class="fas fa-calendar-plus me-1"></i>Date Added</th>
                                    <th class="text-center"><i class="fas fa-cogs me-1"></i>Actions</th>
                                </tr>
                            </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $lowStock = $row['quantity'] <= $row['low_stock_threshold'];
                                    $expired = strtotime($row['expiry_date']) < strtotime(date('Y-m-d'));
                                    $expiringSoon = !$expired && strtotime($row['expiry_date']) <= strtotime('+30 days');
                                    
                                    $statusBadge = '';
                                    
                                    if ($expired) {
                                        $statusBadge = '<span class="badge bg-danger ms-2">Expired</span>';
                                    } elseif ($expiringSoon) {
                                        $statusBadge = '<span class="badge bg-warning text-dark ms-2">Expiring Soon</span>';
                                    } elseif ($lowStock) {
                                        $statusBadge = '<span class="badge bg-warning text-dark ms-2">Low Stock</span>';
                                    }
                                    
                                    echo "<tr class='medicine-row'>";
                                    echo "<td>";
                                    echo htmlspecialchars($row['medicine_name']);
                                    echo $statusBadge;
                                    echo "</td>";
                                    
                                    echo "<td>";
                                    echo htmlspecialchars($row['dosage'] ?? 'Not specified');
                                    echo "</td>";
                                    
                                    echo "<td>";
                                    echo $row['quantity'] . " units";
                                    echo "<br><small class='text-muted'>Threshold: " . $row['low_stock_threshold'] . "</small>";
                                    echo "</td>";
                                    
                                    echo "<td>";
                                    echo htmlspecialchars($row['batch_number']);
                                    echo "</td>";
                                    
                                    echo "<td>";
                                    echo date('M d, Y', strtotime($row['expiry_date']));
                                    $daysToExpiry = (strtotime($row['expiry_date']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);
                                    if ($daysToExpiry < 0) {
                                        echo "<br><small class='text-danger'>Expired " . abs($daysToExpiry) . " days ago</small>";
                                    } elseif ($daysToExpiry <= 30) {
                                        echo "<br><small class='text-warning'>Expires in " . $daysToExpiry . " days</small>";
                                    } else {
                                        echo "<br><small class='text-muted'>" . $daysToExpiry . " days remaining</small>";
                                    }
                                    echo "</td>";
                                    
                                    echo "<td>";
                                    echo date('m/d/Y', strtotime($row['date_added']));
                                    echo "</td>";
                                    
                                    echo "<td>";
                                    echo "<div class='medicine-actions-container'>";
                                    echo "<button type='button' class='btn btn-sm btn-info medicine-btn-view' title='View Details' 
                                          data-bs-toggle='modal' data-bs-target='#viewMedicineModal' 
                                          data-id='{$row['id']}' 
                                          data-name='" . htmlspecialchars($row['medicine_name']) . "' 
                                          data-dosage='" . htmlspecialchars($row['dosage'] ?? '') . "' 
                                          data-batch='" . htmlspecialchars($row['batch_number']) . "' 
                                          data-quantity='{$row['quantity']}' 
                                          data-expiry='{$row['expiry_date']}' 
                                          data-threshold='{$row['low_stock_threshold']}' 
                                          data-date-added='{$row['date_added']}'>
                                          <i class='fas fa-eye'></i>
                                          </button>";
                                    echo "<button type='button' class='btn btn-sm btn-primary medicine-btn-edit' title='Edit Medicine' 
                                          data-bs-toggle='modal' data-bs-target='#editMedicineModal' 
                                          data-id='{$row['id']}' 
                                          data-name='" . htmlspecialchars($row['medicine_name']) . "' 
                                          data-dosage='" . htmlspecialchars($row['dosage'] ?? '') . "' 
                                          data-batch='" . htmlspecialchars($row['batch_number']) . "' 
                                          data-quantity='{$row['quantity']}' 
                                          data-expiry='{$row['expiry_date']}' 
                                          data-threshold='{$row['low_stock_threshold']}'>
                                          <i class='fas fa-edit'></i>
                                          </button>";
                                    if (isAdmin() || isWorker()) {
                                        // Store current filter and search parameters for the delete link
                                        $current_filter = isset($_GET['filter']) ? '&filter=' . urlencode($_GET['filter']) : '';
                                        $current_search = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
                                        $delete_url = "medicines.php?delete_id={$row['id']}" . $current_filter . $current_search;
                                        
                                        echo "<a href='" . htmlspecialchars($delete_url) . "' class='btn btn-sm btn-danger medicine-btn-delete' title='Archive Medicine' onclick='return confirm(\"Are you sure you want to archive this medicine? This action cannot be undone.\")'>";
                                        echo "<i class='fas fa-archive'></i>";
                                        echo "</a>";
                                    }
                                    echo "</div>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr>";
                                echo "<td colspan='6' class='text-center py-5'>";
                                echo "<div class='text-muted'>";
                                echo "<i class='fas fa-pills fa-3x mb-3'></i>";
                                echo "<h5>No medicines found</h5>";
                                echo "<p>No medicines match your current search criteria.</p>";
                                echo "</div>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                </div>
        </div>

    </div>

    <!-- Bottom spacing for better UX -->
    <div class="medicines-bottom-spacing"></div>

    <!-- Add Medicine Modal -->
    <div class="modal fade" id="addMedicineModal" tabindex="-1" aria-labelledby="addMedicineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c3e50, #34495e); color: white;">
                    <h5 class="modal-title" id="addMedicineModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Add New Medicine
                    </h5>
                </div>
                <div class="modal-body">
                    <form id="addMedicineForm">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="medicine_name" class="form-label fw-semibold">
                                    <i class="fas fa-capsules me-1"></i>Medicine Name*
                                </label>
                                <input type="text" class="form-control" id="medicine_name" name="medicine_name" 
                                       placeholder="Enter medicine name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="dosage" class="form-label fw-semibold">
                                    <i class="fas fa-prescription-bottle me-1"></i>Dosage
                                </label>
                                <input type="text" class="form-control" id="dosage" name="dosage" 
                                       placeholder="e.g., 500mg, 10ml, 1 tablet">
                            </div>
                            <div class="col-md-4">
                                <label for="batch_number" class="form-label fw-semibold">
                                    <i class="fas fa-barcode me-1"></i>Batch Number*
                                </label>
                                <input type="text" class="form-control" id="batch_number" name="batch_number" 
                                       placeholder="Enter batch number" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="quantity" class="form-label fw-semibold">
                                    <i class="fas fa-boxes me-1"></i>Quantity*
                                </label>
                                <input type="number" class="form-control" id="quantity" name="quantity" 
                                       min="1" placeholder="Enter quantity" required>
                            </div>
                            <div class="col-md-4">
                                <label for="expiry_date" class="form-label fw-semibold">
                                    <i class="fas fa-calendar-alt me-1"></i>Expiry Date*
                                </label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                                <small class="text-muted">Must be a future date</small>
                            </div>
                            <!-- Low stock threshold is now constant at 10 -->
                            <input type="hidden" name="low_stock_threshold" value="10">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button type="submit" form="addMedicineForm" class="btn btn-success" id="saveMedicineBtn">
                        <i class="fas fa-save me-2"></i> Save Medicine
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Medicine Modal -->
    <div class="modal fade" id="editMedicineModal" tabindex="-1" aria-labelledby="editMedicineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c3e50, #34495e); color: white;">
                    <h5 class="modal-title" id="editMedicineModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Medicine
                    </h5>
                </div>
                <div class="modal-body">
                    <form id="editMedicineForm">
                        <input type="hidden" id="edit_medicine_id" name="medicine_id">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="edit_medicine_name" class="form-label fw-semibold">
                                    <i class="fas fa-capsules me-1"></i>Medicine Name*
                                </label>
                                <input type="text" class="form-control" id="edit_medicine_name" name="medicine_name" 
                                       placeholder="Enter medicine name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_dosage" class="form-label fw-semibold">
                                    <i class="fas fa-prescription-bottle me-1"></i>Dosage
                                </label>
                                <input type="text" class="form-control" id="edit_dosage" name="dosage" 
                                       placeholder="e.g., 500mg, 10ml, 1 tablet">
                            </div>
                            <div class="col-md-4">
                                <label for="edit_batch_number" class="form-label fw-semibold">
                                    <i class="fas fa-barcode me-1"></i>Batch Number*
                                </label>
                                <input type="text" class="form-control" id="edit_batch_number" name="batch_number" 
                                       placeholder="Enter batch number" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="edit_quantity" class="form-label fw-semibold">
                                    <i class="fas fa-boxes me-1"></i>Quantity*
                                </label>
                                <input type="number" class="form-control" id="edit_quantity" name="quantity" 
                                       min="0" placeholder="Enter quantity" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_expiry_date" class="form-label fw-semibold">
                                    <i class="fas fa-calendar-alt me-1"></i>Expiry Date*
                                </label>
                                <input type="date" class="form-control" id="edit_expiry_date" name="expiry_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                                <small class="text-muted">Must be a future date</small>
                            </div>
                            <!-- Low stock threshold is now constant at 10 -->
                            <input type="hidden" name="low_stock_threshold" value="10">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button type="submit" form="editMedicineForm" class="btn btn-success" id="updateMedicineBtn">
                        <i class="fas fa-save me-2"></i> Update Medicine
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Medicine Modal -->
    <div class="modal fade" id="viewMedicineModal" tabindex="-1" aria-labelledby="viewMedicineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c3e50, #34495e); color: white;">
                    <h5 class="modal-title" id="viewMedicineModalLabel">
                        <i class="fas fa-eye me-2"></i>Medicine Details
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Medicine Information -->
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="35%" class="bg-light">
                                            <i class="fas fa-capsules me-2 text-primary"></i>Medicine Name
                                        </th>
                                        <td class="fw-semibold" id="view_medicine_name"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">
                                            <i class="fas fa-prescription-bottle me-2 text-secondary"></i>Dosage
                                        </th>
                                        <td id="view_dosage"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">
                                            <i class="fas fa-barcode me-2 text-info"></i>Batch Number
                                        </th>
                                        <td id="view_batch_number"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">
                                            <i class="fas fa-boxes me-2 text-success"></i>Quantity
                                        </th>
                                        <td>
                                            <span class="fw-semibold" id="view_quantity"></span>
                                            <span id="view_stock_badge"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">
                                            <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Low Stock Threshold
                                        </th>
                                        <td id="view_threshold"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">
                                            <i class="fas fa-calendar-alt me-2 text-danger"></i>Expiry Date
                                        </th>
                                        <td>
                                            <span class="fw-semibold" id="view_expiry_date"></span>
                                            <span id="view_expiry_badge"></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">
                                            <i class="fas fa-calendar-plus me-2 text-secondary"></i>Date Added
                                        </th>
                                        <td id="view_date_added"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <!-- Status Alerts -->
                            <div id="view_status_alerts">
                                <!-- Status alerts will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Close
                    </button>
                    <button type="button" class="btn btn-success" id="editFromViewBtn">
                        <i class="fas fa-edit me-2"></i> Edit Medicine
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Message Modal -->
    <?php if (isset($_GET['success'])): ?>
        <div class="message-modal message-modal-success show" id="messageModal">
            <div class="message-modal-content">
                <div class="message-modal-header">
                    <button class="message-modal-close" onclick="closeMessageModal()">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="message-modal-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="message-modal-title">Success!</h3>
                </div>
                <div class="message-modal-body">
                    <p class="message-modal-message"><?php echo htmlspecialchars($_GET['success']); ?></p>
                    <div class="message-modal-actions">
                        <button class="message-modal-btn message-modal-btn-primary" onclick="closeMessageModal()">
                            <i class="fas fa-check me-2"></i>OK
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="message-modal message-modal-error show" id="messageModal">
            <div class="message-modal-content">
                <div class="message-modal-header">
                    <button class="message-modal-close" onclick="closeMessageModal()">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="message-modal-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="message-modal-title">Error!</h3>
                </div>
                <div class="message-modal-body">
                    <p class="message-modal-message"><?php echo htmlspecialchars($_GET['error']); ?></p>
                    <div class="message-modal-actions">
                        <button class="message-modal-btn message-modal-btn-primary" onclick="closeMessageModal()">
                            <i class="fas fa-times me-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Close message modal function
        function closeMessageModal() {
            const modal = document.getElementById('messageModal');
            if (modal) {
                modal.classList.add('hiding');
                setTimeout(() => {
                    modal.classList.remove('show', 'hiding');
                    // Remove URL parameters
                    const url = new URL(window.location);
                    url.searchParams.delete('success');
                    url.searchParams.delete('error');
                    window.history.replaceState({}, '', url);
                }, 300);
            }
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('messageModal');
            if (modal && event.target === modal) {
                closeMessageModal();
            }
        });

        // Auto-close success modal after 3 seconds
        <?php if (isset($_GET['success'])): ?>
        setTimeout(() => {
            closeMessageModal();
        }, 3000);
        <?php endif; ?>

        // Enhanced delete confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButtons = document.querySelectorAll('.medicine-btn-delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Get medicine name from the first td in the row
                    const row = this.closest('tr');
                    const firstCell = row.querySelector('td:first-child');
                    const medicineName = firstCell ? firstCell.textContent.trim().split('\n')[0] : 'this medicine';
                    
                    const confirmMessage = `Are you sure you want to archive "${medicineName}"?\n\nThis will move the medicine to the archives and it will no longer appear in the active inventory.`;
                    
                    if (confirm(confirmMessage)) {
                        // Show loading state
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                        this.style.pointerEvents = 'none';
                        
                        // Navigate to delete URL
                        window.location.href = this.href;
                    }
                });
            });
        });
    </script>
    
    <script>
        // Function to show success/error message modal
        function showMessageModal(type, message) {
            const modalHtml = `
                <div class="message-modal message-modal-${type} show" id="dynamicMessageModal">
                    <div class="message-modal-content">
                        <div class="message-modal-header">
                            <button class="message-modal-close" onclick="closeDynamicMessageModal()">
                                <i class="fas fa-times"></i>
                            </button>
                            <div class="message-modal-icon">
                                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                            </div>
                            <h3 class="message-modal-title">${type === 'success' ? 'Success!' : 'Error!'}</h3>
                        </div>
                        <div class="message-modal-body">
                            <p class="message-modal-message">${message}</p>
                            <div class="message-modal-actions">
                                <button class="message-modal-btn message-modal-btn-primary" onclick="closeDynamicMessageModal()">
                                    <i class="fas fa-${type === 'success' ? 'check' : 'times'} me-2"></i>${type === 'success' ? 'OK' : 'Close'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing dynamic modal if any
            const existingModal = document.getElementById('dynamicMessageModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add new modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Auto-close success modal after 3 seconds
            if (type === 'success') {
                setTimeout(() => {
                    closeDynamicMessageModal();
                }, 3000);
            }
        }

        function closeDynamicMessageModal() {
            const modal = document.getElementById('dynamicMessageModal');
            if (modal) {
                modal.classList.add('hiding');
                setTimeout(() => {
                    modal.remove();
                    location.reload();
                }, 300);
            }
        }

        document.getElementById('addMedicineForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate expiry date is not in the past
            const expiryDateInput = document.getElementById('expiry_date');
            const expiryDate = new Date(expiryDateInput.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time to start of day for accurate comparison
            
            if (expiryDate < today) {
                showMessageModal('error', 'Cannot add medicine with an expired date. Please select a future expiry date.');
                return;
            }
            
            const formData = new FormData(this);
            const saveBtn = document.getElementById('saveMedicineBtn');
            
            // Disable button and show loading
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            
            fetch('medicine_add_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    
                    // Close the add modal
                    bootstrap.Modal.getInstance(document.getElementById('addMedicineModal')).hide();
                    
                    // Show success or error message modal
                    if (data.success) {
                        showMessageModal('success', data.message);
                    } else {
                        showMessageModal('error', data.message);
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response text:', text);
                    bootstrap.Modal.getInstance(document.getElementById('addMedicineModal')).hide();
                    showMessageModal('error', 'Server response error. Check console for details.');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                bootstrap.Modal.getInstance(document.getElementById('addMedicineModal')).hide();
                showMessageModal('error', 'Network error: ' + error.message);
            })
            .finally(() => {
                // Re-enable button
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Medicine';
            });
        });
        
        // Reset form when modal is closed
        document.getElementById('addMedicineModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('addMedicineForm').reset();
        });

        // Handle edit medicine modal
        document.getElementById('editMedicineModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const medicineId = button.getAttribute('data-id');
            const medicineName = button.getAttribute('data-name');
            const dosage = button.getAttribute('data-dosage');
            const batchNumber = button.getAttribute('data-batch');
            const quantity = button.getAttribute('data-quantity');
            const expiryDate = button.getAttribute('data-expiry');
            const threshold = button.getAttribute('data-threshold');

            // Populate the form
            document.getElementById('edit_medicine_id').value = medicineId;
            document.getElementById('edit_medicine_name').value = medicineName;
            document.getElementById('edit_dosage').value = dosage || '';
            document.getElementById('edit_batch_number').value = batchNumber;
            document.getElementById('edit_quantity').value = quantity;
            document.getElementById('edit_expiry_date').value = expiryDate;
            // Low stock threshold is now constant at 10
        });

        // Handle edit form submission
        document.getElementById('editMedicineForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate expiry date is not in the past
            const expiryDateInput = document.getElementById('edit_expiry_date');
            const expiryDate = new Date(expiryDateInput.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time to start of day for accurate comparison
            
            if (expiryDate < today) {
                showMessageModal('error', 'Cannot update medicine with an expired date. Please select a future expiry date.');
                return;
            }
            
            const formData = new FormData(this);
            const updateBtn = document.getElementById('updateMedicineBtn');
            
            // Disable button and show loading
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            
            fetch('medicine_update_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    
                    // Close the edit modal
                    bootstrap.Modal.getInstance(document.getElementById('editMedicineModal')).hide();
                    
                    // Show success or error message modal
                    if (data.success) {
                        showMessageModal('success', data.message);
                    } else {
                        showMessageModal('error', data.message);
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response text:', text);
                    bootstrap.Modal.getInstance(document.getElementById('editMedicineModal')).hide();
                    showMessageModal('error', 'Server response error. Check console for details.');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                bootstrap.Modal.getInstance(document.getElementById('editMedicineModal')).hide();
                showMessageModal('error', 'Network error: ' + error.message);
            })
            .finally(() => {
                // Re-enable button
                updateBtn.disabled = false;
                updateBtn.innerHTML = '<i class="fas fa-save me-2"></i>Update Medicine';
            });
        });

        // Reset edit form when modal is closed
        document.getElementById('editMedicineModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('editMedicineForm').reset();
        });

        // Handle view medicine modal
        document.getElementById('viewMedicineModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const medicineId = button.getAttribute('data-id');
            const medicineName = button.getAttribute('data-name');
            const dosage = button.getAttribute('data-dosage');
            const batchNumber = button.getAttribute('data-batch');
            const quantity = parseInt(button.getAttribute('data-quantity'));
            const expiryDate = button.getAttribute('data-expiry');
            const threshold = parseInt(button.getAttribute('data-threshold'));
            const dateAdded = button.getAttribute('data-date-added');

            // Populate basic information
            document.getElementById('view_medicine_name').textContent = medicineName;
            document.getElementById('view_dosage').textContent = dosage || 'Not specified';
            document.getElementById('view_batch_number').textContent = batchNumber;
            document.getElementById('view_quantity').textContent = quantity + ' units';
            document.getElementById('view_threshold').textContent = threshold + ' units';
            
            // Format and display dates
            const expiryDateFormatted = new Date(expiryDate).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('view_expiry_date').textContent = expiryDateFormatted;
            
            const dateAddedFormatted = new Date(dateAdded).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('view_date_added').textContent = dateAddedFormatted;

            // Calculate status
            const today = new Date();
            const expiry = new Date(expiryDate);
            const daysToExpiry = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24));
            const isLowStock = quantity <= threshold;
            const isExpired = daysToExpiry < 0;
            const isExpiringSoon = !isExpired && daysToExpiry <= 30;

            // Stock status badge
            const stockBadge = document.getElementById('view_stock_badge');
            if (isLowStock) {
                stockBadge.innerHTML = '<span class="badge bg-danger ms-2">Low Stock</span>';
            } else {
                stockBadge.innerHTML = '<span class="badge bg-success ms-2">In Stock</span>';
            }

            // Expiry status badge
            const expiryBadge = document.getElementById('view_expiry_badge');
            if (isExpired) {
                expiryBadge.innerHTML = '<span class="badge bg-danger ms-2">Expired</span>';
            } else if (isExpiringSoon) {
                expiryBadge.innerHTML = '<span class="badge bg-warning text-dark ms-2">Expiring Soon</span>';
            } else {
                expiryBadge.innerHTML = '<span class="badge bg-success ms-2">Valid</span>';
            }

            // Status alerts
            const statusAlertsContainer = document.getElementById('view_status_alerts');
            let alertsHtml = '';

            // Stock status alert
            if (isLowStock) {
                alertsHtml += `
                    <div class="alert alert-danger mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Low Stock Alert:</strong> Current quantity (${quantity} units) is at or below threshold (${threshold} units).
                    </div>
                `;
            } else {
                alertsHtml += `
                    <div class="alert alert-success mb-3">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Stock Status:</strong> Current quantity (${quantity} units) is above threshold (${threshold} units).
                    </div>
                `;
            }

            // Expiry status alert
            if (isExpired) {
                alertsHtml += `
                    <div class="alert alert-danger">
                        <i class="fas fa-calendar-times me-2"></i>
                        <strong>Expired:</strong> This medicine expired on ${expiryDateFormatted}.
                    </div>
                `;
            } else if (isExpiringSoon) {
                alertsHtml += `
                    <div class="alert alert-warning">
                        <i class="fas fa-calendar-day me-2"></i>
                        <strong>Expiring Soon:</strong> This medicine expires on ${expiryDateFormatted}.
                    </div>
                `;
            } else {
                alertsHtml += `
                    <div class="alert alert-info">
                        <i class="fas fa-calendar-check me-2"></i>
                        <strong>Valid Until:</strong> ${expiryDateFormatted}.
                    </div>
                `;
            }

            statusAlertsContainer.innerHTML = alertsHtml;

            // Set up edit button to open edit modal with data
            document.getElementById('editFromViewBtn').onclick = function() {
                // Close view modal
                bootstrap.Modal.getInstance(document.getElementById('viewMedicineModal')).hide();
                
                // Open edit modal with data
                setTimeout(() => {
                    const editModal = new bootstrap.Modal(document.getElementById('editMedicineModal'));
                    
                    // Populate edit form
                    document.getElementById('edit_medicine_id').value = medicineId;
                    document.getElementById('edit_medicine_name').value = medicineName;
                    document.getElementById('edit_batch_number').value = batchNumber;
                    document.getElementById('edit_quantity').value = quantity;
                    document.getElementById('edit_expiry_date').value = expiryDate;
                    // Low stock threshold is now constant at 10
                    
                    editModal.show();
                }, 300);
            };
        });
        
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
    </script>
</body>
</html> 