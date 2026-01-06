<?php
// Database setup script
$host = "localhost";
$username = "root";
$password = "";
$database_name = "san_benito_health";

echo "<h2>Database Setup</h2>";

// First, connect without selecting a database
$conn = mysqli_connect($host, $username, $password);

if (!$conn) {
    die("<p style='color: red;'>Connection failed: " . mysqli_connect_error() . "</p>");
}

echo "<p style='color: green;'>✓ Connected to MySQL server</p>";

// Drop database if it exists (for clean setup)
mysqli_query($conn, "DROP DATABASE IF EXISTS $database_name");
echo "<p style='color: blue;'>ℹ Dropped existing database (if any)</p>";

// Create database
if (mysqli_query($conn, "CREATE DATABASE $database_name")) {
    echo "<p style='color: green;'>✓ Database '$database_name' created successfully</p>";
} else {
    die("<p style='color: red;'>Error creating database: " . mysqli_error($conn) . "</p>");
}

// Select the database
if (mysqli_select_db($conn, $database_name)) {
    echo "<p style='color: green;'>✓ Selected database '$database_name'</p>";
} else {
    die("<p style='color: red;'>Error selecting database: " . mysqli_error($conn) . "</p>");
}

// Read and execute the SQL file
$sql_file = 'database.sql';
if (file_exists($sql_file)) {
    $sql_content = file_get_contents($sql_file);
    
    // Split SQL commands by semicolon
    $sql_commands = explode(';', $sql_content);
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($sql_commands as $command) {
        $command = trim($command);
        if (!empty($command) && !preg_match('/^--/', $command) && !preg_match('/^\/\*/', $command)) {
            if (mysqli_query($conn, $command)) {
                $success_count++;
            } else {
                $error = mysqli_error($conn);
                if (!empty($error)) {
                    echo "<p style='color: orange;'>Warning: " . $error . "</p>";
                    $error_count++;
                }
            }
        }
    }
    
    echo "<p style='color: green;'>✓ Executed $success_count SQL commands successfully</p>";
    if ($error_count > 0) {
        echo "<p style='color: orange;'>⚠ $error_count warnings (likely duplicate table creation attempts)</p>";
    }
} else {
    echo "<p style='color: red;'>✗ SQL file 'database.sql' not found</p>";
}

// Test the connection with the new database
require_once 'config/database.php';

if ($conn) {
    echo "<p style='color: green;'>✓ Database connection test successful</p>";
    
    // Check tables
    $tables = ['users', 'medicines', 'babies', 'vaccinations', 'appointments'];
    echo "<h3>Table Status:</h3>";
    
    foreach ($tables as $table) {
        $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if (mysqli_num_rows($result) > 0) {
            $count_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM $table");
            $count = mysqli_fetch_assoc($count_result)['count'];
            echo "<p>✓ Table '$table': $count records</p>";
        } else {
            echo "<p style='color: red;'>✗ Table '$table' not found</p>";
        }
    }
    
    echo "<h3>Next Steps:</h3>";
    echo "<p>1. <a href='add_sample_data.php'>Add Sample Data</a> (for testing)</p>";
    echo "<p>2. <a href='login.php'>Go to Login Page</a></p>";
    echo "<p>3. Use credentials: admin / admin123</p>";
    
} else {
    echo "<p style='color: red;'>✗ Database connection test failed</p>";
}

mysqli_close($conn);
?>