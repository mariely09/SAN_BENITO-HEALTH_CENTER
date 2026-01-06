<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If it's an AJAX request, return JSON
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please log in to access your profile']);
        exit;
    }
    echo '<div class="alert alert-danger">Please log in to access your profile.</div>';
    exit;
}

$user_id = $_SESSION['user_id'];

// Process AJAX form submissions FIRST before any output
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    
    // Get user details
    $query = "SELECT * FROM users WHERE id = $user_id";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $user = mysqli_fetch_assoc($result);
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // Validate input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            echo json_encode(['success' => false, 'message' => 'Please fill in all password fields']);
            exit;
        }
        
        if (strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
            exit;
        }
        
        if ($new_password != $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }
        
        // Verify current password
        $password_correct = false;
        $stored_password = trim($user['password']); // Remove any whitespace
        
        // Log for debugging
        error_log("=== PASSWORD CHANGE DEBUG ===");
        error_log("User ID: " . $user_id);
        error_log("Username: " . $user['username']);
        error_log("Current password entered length: " . strlen($current_password));
        error_log("Stored password length: " . strlen($stored_password));
        error_log("Stored password (first 30 chars): " . substr($stored_password, 0, 30));
        error_log("Is password hashed? " . (password_get_info($stored_password)['algo'] !== null ? 'YES' : 'NO'));
        
        // Try hashed password first
        if (password_verify($current_password, $stored_password)) {
            $password_correct = true;
            error_log("✓ Password verified using password_verify (hashed)");
        }
        // If that fails, try plain text comparison (exact match)
        else if ($current_password === $stored_password) {
            $password_correct = true;
            error_log("✓ Password verified using plain text comparison (exact)");
        }
        // Try case-insensitive comparison
        else if (strcasecmp($current_password, $stored_password) === 0) {
            $password_correct = true;
            error_log("✓ Password verified using case-insensitive comparison");
        }
        else {
            error_log("✗ Password verification FAILED");
            error_log("  - password_verify result: " . (password_verify($current_password, $stored_password) ? 'true' : 'false'));
            error_log("  - Exact match: " . ($current_password === $stored_password ? 'true' : 'false'));
            error_log("  - Case-insensitive: " . (strcasecmp($current_password, $stored_password) === 0 ? 'true' : 'false'));
        }
        
        if (!$password_correct) {
            echo json_encode([
                'success' => false, 
                'message' => 'Current password is incorrect. Please check your password and try again.',
                'debug' => [
                    'entered_length' => strlen($current_password),
                    'stored_length' => strlen($stored_password),
                    'is_hashed' => password_get_info($stored_password)['algo'] !== null
                ]
            ]);
            exit;
        }
        
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password using prepared statement
        $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating password. Please try again.']);
        }
        
        mysqli_stmt_close($stmt);
        exit;
    }
    
    // Handle profile update
    if (isset($_POST['update_profile'])) {
        $username = sanitize($_POST['username']);
        $fullname = sanitize($_POST['fullname']);
        $email = sanitize($_POST['email']);
        $contact_number = sanitize($_POST['contact_number']);
        
        // Validate input
        if (empty($username)) {
            echo json_encode(['success' => false, 'message' => 'Username cannot be empty']);
            exit;
        }
        
        if (empty($fullname)) {
            echo json_encode(['success' => false, 'message' => 'Full name cannot be empty']);
            exit;
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
            exit;
        }
        
        if (!preg_match('/^09\d{9}$/', $contact_number)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid contact number (e.g., 09123456789)']);
            exit;
        }
        
        // Check if username already exists (if changed)
        if ($username != $user['username']) {
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
            mysqli_stmt_bind_param($stmt, "si", $username, $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Username is already taken by another account']);
                mysqli_stmt_close($stmt);
                exit;
            }
            mysqli_stmt_close($stmt);
        }
        
        // Check if email already exists (if provided)
        if (!empty($email)) {
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ?");
            mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                echo json_encode(['success' => false, 'message' => 'Email address is already in use by another account']);
                mysqli_stmt_close($stmt);
                exit;
            }
            mysqli_stmt_close($stmt);
        }
        
        // Update profile using prepared statement
        if (!empty($email)) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET username = ?, fullname = ?, email = ?, contact_number = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssssi", $username, $fullname, $email, $contact_number, $user_id);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET username = ?, fullname = ?, email = NULL, contact_number = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $username, $fullname, $contact_number, $user_id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['username'] = $username;
            $_SESSION['fullname'] = $fullname;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating profile. Please try again.']);
        }
        
        mysqli_stmt_close($stmt);
        exit;
    }
}

// If we reach here, it's a GET request - load the profile page
// Get user details for display
$query = "SELECT * FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-danger">User not found.</div>';
    exit;
}

$user = mysqli_fetch_assoc($result);
?>

<!-- Include profile CSS -->
<link rel="stylesheet" href="assets/css/profile.css">
<link rel="stylesheet" href="assets/css/success-error_messages.css">

<!-- Message Modal Container (will be populated by JavaScript) -->
<div id="messageModalContainer"></div>

<div class="row">
    <!-- Profile Information -->
    <div class="col-lg-5">
        <div class="card profile-card shadow-sm mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Account Information</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                    </div>
                    <h5 class="profile-user-name"><?php echo htmlspecialchars($user['fullname']); ?></h5>
                    <p class="profile-user-role mb-2"><?php echo ucfirst($user['role']); ?></p>
                    <span class="status-badge bg-<?php echo $user['status'] == 'approved' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </div>
                
                <div class="profile-info-section">
                    <div class="profile-info-item">
                        <div class="profile-info-label">Username</div>
                        <div class="profile-info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Email Address</div>
                        <div class="profile-info-value"><?php echo htmlspecialchars($user['email'] ?? 'Not provided'); ?></div>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Contact Number</div>
                        <div class="profile-info-value"><?php echo htmlspecialchars($user['contact_number']); ?></div>
                    </div>
                    <div class="profile-info-item">
                        <div class="profile-info-label">Registered On</div>
                        <div class="profile-info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Update Forms -->
    <div class="col-lg-7">
        <!-- Alert Messages -->
        <div id="profileAlerts"></div>
        
        <!-- Update Profile -->
        <div class="card profile-form-card shadow-sm mb-3">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Update Profile</h6>
            </div>
            <div class="card-body">
                <form id="updateProfileForm">
                    <div class="mb-3">
                        <label for="modal_fullname" class="form-label profile-form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="modal_fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_email" class="form-label profile-form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="modal_email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="your.email@example.com">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_contact_number" class="form-label profile-form-label">Contact Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" class="form-control" id="modal_contact_number" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>" placeholder="09XXXXXXXXX" required oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="11">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_username" class="form-label profile-form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
                            <input type="text" class="form-control" id="modal_username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="btn profile-btn profile-btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="card profile-form-card shadow-sm">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">Change Password</h6>
            </div>
            <div class="card-body">
                <form id="changePasswordForm">
                    <div class="mb-3">
                        <label for="modal_current_password" class="form-label profile-form-label">Current Password</label>
                        <div class="input-group" style="position: relative;">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="modal_current_password" name="current_password" required style="padding-right: 40px;">
                            <i class="fas fa-eye" id="toggleCurrentPassword" onclick="togglePasswordVisibility('modal_current_password', 'toggleCurrentPassword')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; color: #6c757d;"></i>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_new_password" class="form-label profile-form-label">New Password</label>
                        <div class="input-group" style="position: relative;">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="modal_new_password" name="new_password" required style="padding-right: 40px;">
                            <i class="fas fa-eye" id="toggleNewPassword" onclick="togglePasswordVisibility('modal_new_password', 'toggleNewPassword')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; color: #6c757d;"></i>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_confirm_password" class="form-label profile-form-label">Confirm New Password</label>
                        <div class="input-group" style="position: relative;">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="modal_confirm_password" name="confirm_password" required style="padding-right: 40px;">
                            <i class="fas fa-eye" id="toggleConfirmPassword" onclick="togglePasswordVisibility('modal_confirm_password', 'toggleConfirmPassword')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; color: #6c757d;"></i>
                        </div>
                    </div>
                    <button type="submit" class="btn profile-btn profile-btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/profile_modal.js"></script>
<script>
// Simple password toggle function using onclick
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