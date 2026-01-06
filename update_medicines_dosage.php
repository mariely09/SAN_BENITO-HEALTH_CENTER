<?php
// Simple script to add dosage column to medicines table
// Run this file once to fix the database structure

require_once 'config/database.php';

// Add dosage column to medicines table
$query = "ALTER TABLE medicines ADD COLUMN dosage VARCHAR(100) DEFAULT NULL AFTER medicine_name";

if (mysqli_query($conn, $query)) {
    echo "SUCCESS: Dosage column added to medicines table!";
} else {
    $error = mysqli_error($conn);
    if (strpos($error, 'Duplicate column name') !== false) {
        echo "INFO: Dosage column already exists in medicines table.";
    } else {
        echo "ERROR: " . $error;
    }
}

mysqli_close($conn);
?>