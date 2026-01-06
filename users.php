<?php
// Prevent browser caching - force fresh page load
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Update user status (approve or reject)
if (isset($_GET['approve_id'])) {
    require_once 'config/database.php';
    require_once 'config/session.php';
    require_once 'config/functions.php';
    requireAdmin();

    $id = (int) $_GET['approve_id'];
    
    // First, get the user's information before updating
    $get_user_query = "SELECT fullname, email, role FROM users WHERE id = $id AND status = 'pending'";
    $user_result = mysqli_query($conn, $get_user_query);
    
    if (mysqli_num_rows($user_result) > 0) {
        $user_data = mysqli_fetch_assoc($user_result);
        $user_email = $user_data['email'];
        $user_name = $user_data['fullname'];
        $user_role = $user_data['role'];
        
        // Update user status
        $update_query = "UPDATE users SET status = 'approved' WHERE id = $id";
        
        // Store current filter and search parameters
        $current_filter = isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : '';
        $current_search = isset($_GET['search']) ? (!empty($current_filter) ? '&' : '?') . 'search=' . urlencode($_GET['search']) : '';
        
        // Add success/error parameter with proper ? or & prefix
        $redirect_base = "users.php" . $current_filter . $current_search;
        $redirect_base .= (strpos($redirect_base, '?') === false) ? '?' : '&';
        
        if (mysqli_query($conn, $update_query)) {
            // Send email notification to the approved user
            require_once 'config/email.php';
            
            $email_sent = false;
            $email_message = "";
            
            if (!empty($user_email) && filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
                // Log the email attempt with more details
                error_log("=== USER APPROVAL EMAIL ATTEMPT ===");
                error_log("User: " . $user_name . " (" . $user_email . ")");
                error_log("Role: " . $user_role);
                error_log("Email Mode: " . (defined('EMAIL_MODE') ? EMAIL_MODE : 'undefined'));
                
                // Send email using the new email function
                $email_sent = sendApprovalEmail($user_email, $user_name, $user_role);
                
                if ($email_sent) {
                    $email_message = "User '" . $user_name . "' approved successfully and notification email sent to " . $user_email;
                    error_log("✅ Approval email sent successfully to: " . $user_email);
                } else {
                    $email_message = "User '" . $user_name . "' approved successfully but email notification failed to send to " . $user_email;
                    error_log("❌ Approval email failed to send to: " . $user_email);
                }
            } else {
                $email_message = "User '" . $user_name . "' approved successfully but no valid email address found";
                error_log("⚠️ No valid email address for user: " . $user_name . " (provided: " . $user_email . ")");
            }
            
            header("Location: " . $redirect_base . "show_approve_modal=1");
            exit;
        } else {
            header("Location: " . $redirect_base . "error=Failed to approve user");
            exit;
        }
    } else {
        header("Location: " . $redirect_base . "error=User not found or already approved");
        exit;
    }
}

// Archive user (instead of deleting)
if (isset($_GET['delete_id'])) {
    require_once 'config/database.php';
    require_once 'config/session.php';
    require_once 'config/functions.php';
    require_once 'config/archive_functions.php';
    requireAdmin();

    $id = (int) $_GET['delete_id'];
    
    // Store current filter and search parameters
    $current_filter = isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : '';
    $current_search = isset($_GET['search']) ? (!empty($current_filter) ? '&' : '?') . 'search=' . urlencode($_GET['search']) : '';
    
    // Add success/error parameter with proper ? or & prefix
    $redirect_base = "users.php" . $current_filter . $current_search;
    $redirect_base .= (strpos($redirect_base, '?') === false) ? '?' : '&';
    
    try {
        archiveUser($id, $_SESSION['user_id']);
        header("Location: " . $redirect_base . "show_archive_modal=1");
        exit;
    } catch (Exception $e) {
        error_log("Archive user error: " . $e->getMessage());
        header("Location: " . $redirect_base . "error=" . urlencode($e->getMessage()));
        exit;
    }
}

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';
requireAdmin();

// Filter query
$filter = $_GET['filter'] ?? '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where_clause = "";
// Always exclude admin users from the list
$where_clause = "WHERE role != 'admin'";

switch ($filter) {
    case 'pending':
        $where_clause .= " AND status = 'pending'";
        break;
    case 'resident':
        $where_clause .= " AND role = 'resident'";
        break;
    case 'worker':
        $where_clause .= " AND role = 'worker'";
        break;
}

if (!empty($search)) {
    if (!empty($where_clause)) {
        $where_clause .= " AND (fullname LIKE '%$search%' OR email LIKE '%$search%' OR contact_number LIKE '%$search%')";
    } else {
        $where_clause = "WHERE fullname LIKE '%$search%' OR email LIKE '%$search%' OR contact_number LIKE '%$search%'";
    }
}

// Get user statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_count,
                (SELECT COUNT(*) FROM users WHERE status = 'approved') as approved_count,
                (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_count,
                (SELECT COUNT(*) FROM users WHERE role = 'worker') as worker_count,
                (SELECT COUNT(*) FROM users WHERE role = 'resident') as resident_count,
                (SELECT COUNT(*) FROM users) as total_count";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get all users with explicit column selection to avoid caching issues
$query = "SELECT id, fullname, email, contact_number, role, status, created_at 
          FROM users $where_clause ORDER BY 
          CASE WHEN status = 'pending' THEN 0 ELSE 1 END, 
          fullname ASC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Navbar Styles -->
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?php echo time(); ?>">
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/residents_dashboard.css?v=<?php echo time(); ?>">
    <!-- Users Styles -->
    <link rel="stylesheet" href="assets/css/users.css?v=<?php echo time(); ?>">
    <!-- Success/Error Messages Styles -->
    <link rel="stylesheet" href="assets/css/success-error_messages.css?v=<?php echo time(); ?>"><?php /* Cache buster */ ?>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- User Management Header -->
        <div class="user-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">
                        <i class="fas fa-users-cog me-2"></i>
                        User Management
                    </h1>
                    <p class="welcome-subtitle">Manage user accounts, approvals, and system access permissions.</p>
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

        <!-- User Statistics -->
        <section class="statistics-section mb-4">
            <h2 class="section-title">
                <i class="fas fa-chart-bar me-2"></i>User Statistics
            </h2>
            <div class="row justify-content-center">

                <div class="col-6 col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div
                        class="card user-stats-card position-relative <?php echo $stats['pending_count'] > 0 ? 'has-pending' : ''; ?>">
                        <?php if ($stats['pending_count'] > 0): ?>
                            <div class="position-absolute" style="top: 17px; right: 7px; z-index: 10;">
                                <span class="badge rounded-pill notification-badge"
                                    style="width: 12px; height: 12px; padding: 0; border-radius: 50%; display: block;"></span>
                            </div>
                        <?php endif; ?>
                        <div class="card-body text-center">
                            <div class="user-stats-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="user-stats-number"><?php echo $stats['pending_count']; ?></h3>
                            <p class="user-stats-label">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="card user-stats-card">
                        <div class="card-body text-center">
                            <div class="user-stats-icon">
                                <i class="fas fa-home"></i>
                            </div>
                            <h3 class="user-stats-number"><?php echo $stats['resident_count']; ?></h3>
                            <p class="user-stats-label">Residents</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="card user-stats-card">
                        <div class="card-body text-center">
                            <div class="user-stats-icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <h3 class="user-stats-number"><?php echo $stats['worker_count']; ?></h3>
                            <p class="user-stats-label">Workers</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="card user-stats-card">
                        <div class="card-body text-center">
                            <div class="user-stats-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="user-stats-number"><?php echo $stats['total_count']; ?></h3>
                            <p class="user-stats-label">Total Users</p>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Users List -->
        <section class="users-list-section">
            <div class="card table-card">
                <div class="card-header d-flex flex-nowrap justify-content-start align-items-center">
                    <div class="d-flex flex-nowrap users-header-buttons">
                        <button type="button" class="btn btn-secondary btn-sm users-btn" onclick="window.history.back()" title="Go Back">
                            <i class="fas fa-arrow-left me-1"></i>Back
                        </button>
                        <a href="archives.php?type=users" class="btn btn-warning btn-sm users-btn" title="View Archives">
                            <i class="fas fa-archive me-1"></i>Archives
                        </a>
                    </div>
                </div>

                <!-- Filters inside table card -->
                <div class="card-body border-bottom">
                    <form action="" method="GET" class="row g-3" id="filterForm">
                        <div class="col-md-4">
                            <label for="search" class="form-label fw-semibold">
                                <i class="fas fa-search me-1"></i>Search Users
                            </label>
                            <input type="text" class="form-control" id="search" name="search"
                                placeholder="Full name, email, or contact number..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filter" class="form-label fw-semibold">
                                <i class="fas fa-filter me-1"></i>Filter by Status
                            </label>
                            <select class="form-select" id="filter" name="filter">
                                <option value="" <?php echo $filter == '' ? 'selected' : ''; ?>>All Users</option>
                                <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                                <option value="resident" <?php echo $filter == 'resident' ? 'selected' : ''; ?>>Residents</option>
                                <option value="worker" <?php echo $filter == 'worker' ? 'selected' : ''; ?>>Health Workers</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-center" style="padding-top: 2rem;">
                            <a href="users.php" class="btn btn-outline-secondary btn-sm w-100" title="Reset Filters">
                                <i class="fas fa-redo"></i><span class="d-none d-sm-inline ms-1">Reset</span>
                            </a>
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-id-card me-1"></i>Full Name</th>
                                    <th><i class="fas fa-envelope me-1"></i>Email Address</th>
                                    <th><i class="fas fa-phone me-1"></i>Contact Number</th>
                                    <th><i class="fas fa-user-tag me-1"></i>Role</th>
                                    <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                    <th style="width: 120px;"><i class="fas fa-calendar me-1"></i>Registered</th>
                                    <th class="text-center" style="width: 120px;"><i class="fas fa-cogs me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        echo "<tr>";
                                        echo "<td><strong>" . htmlspecialchars($row['fullname']) . "</strong></td>";
                                        echo "<td>" . htmlspecialchars($row['email'] ?? 'N/A') . "</td>";
                                        echo "<td>" . htmlspecialchars($row['contact_number'] ?? 'N/A') . "</td>";
                                        echo "<td>";
                                        $role_class = $row['role'] == 'worker' ? 'info' : 'secondary';
                                        $role_icon = $row['role'] == 'worker' ? 'user-md' : 'user';
                                        echo "<span class='badge bg-$role_class'><i class='fas fa-$role_icon me-1'></i>" . ucfirst($row['role']) . "</span>";
                                        echo "</td>";
                                        echo "<td>";
                                        if ($row['status'] == 'approved') {
                                            echo '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Approved</span>';
                                        } else {
                                            echo '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Pending</span>';
                                        }
                                        echo "</td>";
                                        // Display registration date with Asia/Manila timezone
                                        $reg_date = 'N/A';
                                        if (!empty($row['created_at']) && $row['created_at'] != '0000-00-00 00:00:00') {
                                            date_default_timezone_set('Asia/Manila');
                                            $timestamp = strtotime($row['created_at']);
                                            if ($timestamp !== false) {
                                                $reg_date = date('M d, Y', $timestamp);
                                            }
                                        }
                                        echo "<td style='width: 120px;'>" . $reg_date . "</td>";
                                        echo "<td class='text-center' style='width: 120px;'>";
                                        // Since we're filtering out admins, all users can have actions
                                        echo "<div class='d-flex gap-1 justify-content-center'>";
                                        if ($row['status'] == 'pending') {
                                            echo "<a href='users.php?approve_id={$row['id']}' class='btn btn-sm btn-primary' 
                                                     onclick='return confirm(\"Are you sure you want to approve this user?\")' title='Approve User'>
                                                    <i class='fas fa-check'></i>
                                                  </a>";
                                        } else {
                                            echo "<button class='btn btn-sm btn-secondary' title='Already Approved' disabled>
                                                    <i class='fas fa-check'></i>
                                                  </button>";
                                        }
                                        echo "<a href='users.php?delete_id={$row['id']}' class='btn btn-sm btn-danger' 
                                                 onclick='return confirm(\"Are you sure you want to archive this user?\")' title='Archive User'>
                                                <i class='fas fa-trash'></i>
                                              </a>";
                                        echo "</div>";
                                        echo "</td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7' class='text-center py-4'>
                                            <i class='fas fa-users fa-3x text-muted mb-3'></i>
                                            <h5 class='text-muted'>No users found</h5>
                                            <p class='text-muted'>No users match your current filter criteria.</p>
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
                <p class="message-modal-message">User has been archived successfully.</p>
                <div class="message-modal-actions">
                    <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="closeArchiveModal()">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Success Modal -->
    <div id="approveModal" class="message-modal">
        <div class="message-modal-content message-modal-success">
            <div class="message-modal-header">
                <button type="button" class="message-modal-close" onclick="closeApproveModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="message-modal-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="message-modal-title">Approved!</h3>
            </div>
            <div class="message-modal-body">
                <p class="message-modal-message">User has been approved successfully.</p>
                <div class="message-modal-actions">
                    <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="closeApproveModal()">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Check if we should show modals
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
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
            
            // Approve modal
            if (urlParams.has('show_approve_modal')) {
                const approveModal = document.getElementById('approveModal');
                if (approveModal) {
                    approveModal.classList.add('show');
                    urlParams.delete('show_approve_modal');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    history.replaceState(null, '', newUrl);
                }
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
        });

        // Modal close functions
        function closeArchiveModal() {
            const archiveModal = document.getElementById('archiveModal');
            archiveModal.classList.remove('show');
        }

        function closeApproveModal() {
            const approveModal = document.getElementById('approveModal');
            approveModal.classList.remove('show');
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