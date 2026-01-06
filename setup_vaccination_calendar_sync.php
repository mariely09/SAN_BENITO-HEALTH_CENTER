<?php
/**
 * Setup Script: Create vaccination_calendar_sync table
 * Run this script once to create the table for vaccination Google Calendar sync
 */

require_once 'config/database.php';
require_once 'config/session.php';

// Only admins can run this setup
requireAdmin();

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Setup Vaccination Calendar Sync</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>
        <div class='card'>
            <div class='card-header bg-primary text-white'>
                <h3><i class='fas fa-database'></i> Setup Vaccination Calendar Sync</h3>
            </div>
            <div class='card-body'>";

// Create vaccination_calendar_sync table
$createTableQuery = "CREATE TABLE IF NOT EXISTS vaccination_calendar_sync (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vaccination_id INT NOT NULL,
    user_id INT NOT NULL,
    google_event_id VARCHAR(255) NOT NULL,
    last_synced_at DATETIME NOT NULL,
    UNIQUE KEY unique_vaccination_user (vaccination_id, user_id),
    KEY idx_vaccination_id (vaccination_id),
    KEY idx_user_id (user_id),
    KEY idx_google_event_id (google_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($conn, $createTableQuery)) {
    echo "<div class='alert alert-success'>
            <i class='fas fa-check-circle'></i> 
            <strong>Success!</strong> vaccination_calendar_sync table created successfully.
          </div>";
} else {
    echo "<div class='alert alert-danger'>
            <i class='fas fa-exclamation-triangle'></i> 
            <strong>Error:</strong> " . mysqli_error($conn) . "
          </div>";
}

// Add index for faster lookups
$indexQuery = "CREATE INDEX IF NOT EXISTS idx_last_synced ON vaccination_calendar_sync(last_synced_at)";
if (mysqli_query($conn, $indexQuery)) {
    echo "<div class='alert alert-success'>
            <i class='fas fa-check-circle'></i> 
            <strong>Success!</strong> Index created successfully.
          </div>";
}

// Check if table exists and show structure
$checkQuery = "SHOW TABLES LIKE 'vaccination_calendar_sync'";
$result = mysqli_query($conn, $checkQuery);

if (mysqli_num_rows($result) > 0) {
    echo "<div class='alert alert-info'>
            <h5><i class='fas fa-info-circle'></i> Table Structure:</h5>";
    
    $structureQuery = "DESCRIBE vaccination_calendar_sync";
    $structureResult = mysqli_query($conn, $structureQuery);
    
    echo "<table class='table table-bordered table-sm'>
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Type</th>
                    <th>Null</th>
                    <th>Key</th>
                    <th>Default</th>
                    <th>Extra</th>
                </tr>
            </thead>
            <tbody>";
    
    while ($row = mysqli_fetch_assoc($structureResult)) {
        echo "<tr>
                <td>{$row['Field']}</td>
                <td>{$row['Type']}</td>
                <td>{$row['Null']}</td>
                <td>{$row['Key']}</td>
                <td>{$row['Default']}</td>
                <td>{$row['Extra']}</td>
              </tr>";
    }
    
    echo "</tbody></table></div>";
}

echo "      <div class='mt-4'>
                <a href='admin_dashboard.php' class='btn btn-primary'>
                    <i class='fas fa-arrow-left'></i> Back to Dashboard
                </a>
                <a href='vaccinations.php' class='btn btn-success'>
                    <i class='fas fa-syringe'></i> Go to Vaccinations
                </a>
              </div>
            </div>
        </div>
    </div>
    
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css'>
</body>
</html>";
