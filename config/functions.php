<?php
// Display success message
function showSuccess($message) {
    return '<div class="alert alert-success">' . $message . '</div>';
}

// Display error message
function showError($message) {
    return '<div class="alert alert-danger">' . $message . '</div>';
}

// Display warning message
function showWarning($message) {
    return '<div class="alert alert-warning">' . $message . '</div>';
}

// Function to sanitize input data
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// Function to format date to Y-m-d format
function formatDate($date) {
    return date('Y-m-d', strtotime($date));
}

// Function to check if medicine is low on stock
function isLowStock($quantity, $threshold) {
    return $quantity <= $threshold;
}

// Function to get age from date of birth
function getAge($dob) {
    $today = new DateTime();
    $birthdate = new DateTime($dob);
    $interval = $today->diff($birthdate);
    
    if ($interval->y > 0) {
        return $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
    } elseif ($interval->m > 0) {
        return $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
    } else {
        return $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
    }
}

// Function to get the appropriate dashboard URL based on user role
function getDashboardUrl() {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'resident') {
            return 'resident_dashboard.php';
        } elseif ($_SESSION['role'] === 'admin') {
            return 'admin_dashboard.php';
        } elseif ($_SESSION['role'] === 'worker') {
            return 'worker_dashboard.php';
        }
    }
    return 'index.php';
}
?> 