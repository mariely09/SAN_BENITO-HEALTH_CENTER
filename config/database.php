<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "san_benito_health";

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set timezone to Philippine Time
mysqli_query($conn, "SET time_zone = '+08:00'");

// Auto-create default admin account if it doesn't exist
function createDefaultAdmin($conn) {
    // Check if admin account already exists
    $check_admin = "SELECT id FROM users WHERE username = 'admin' AND role = 'admin'";
    $result = mysqli_query($conn, $check_admin);
    
    if (mysqli_num_rows($result) == 0) {
        // Create default admin account
        $admin_username = 'admin';
        $admin_password = 'admin123';
        $admin_fullname = 'System Administrator';
        $admin_email = 'sanbenitohealthcenter0123@gmail.com';
        $admin_role = 'admin';
        $admin_status = 'approved';
        $created_at = date('Y-m-d H:i:s');
        
        $insert_admin = "INSERT INTO users (username, password, fullname, email, role, status, created_at) 
                        VALUES ('$admin_username', '$admin_password', '$admin_fullname', '$admin_email', '$admin_role', '$admin_status', '$created_at')";
        
        if (mysqli_query($conn, $insert_admin)) {
            error_log("Default admin account created successfully");
        } else {
            error_log("Error creating default admin account: " . mysqli_error($conn));
        }
    }
}


// Check if users table exists before creating default accounts
$table_check = "SHOW TABLES LIKE 'users'";
$table_result = mysqli_query($conn, $table_check);

if (mysqli_num_rows($table_result) > 0) {
    // Create default accounts
    createDefaultAdmin($conn);
}
