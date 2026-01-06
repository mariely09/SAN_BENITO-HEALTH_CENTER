<?php
/**
 * Google Calendar Setup Script
 * Run this once to create the necessary database tables for Google Calendar integration
 */

require_once 'config/database.php';

// Read and execute SQL file
$sqlFile = 'sql/google_calendar_tables.sql';

if (!file_exists($sqlFile)) {
    die("Error: SQL file not found at $sqlFile");
}

$sql = file_get_contents($sqlFile);

// Split SQL statements
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', $stmt);
    }
);

$success = true;
$errors = [];
$warnings = [];

foreach ($statements as $statement) {
    if (!empty($statement)) {
        if (!mysqli_query($conn, $statement)) {
            $error = mysqli_error($conn);
            
            // Check if it's just a "duplicate" or "already exists" warning
            if (stripos($error, 'duplicate') !== false || 
                stripos($error, 'already exists') !== false) {
                $warnings[] = $error;
            } else {
                $success = false;
                $errors[] = $error;
            }
        }
    }
}

// If only warnings (no real errors), consider it successful
if (empty($errors) && !empty($warnings)) {
    $success = true;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Calendar Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-<?php echo $success ? 'success' : 'danger'; ?> text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-<?php echo $success ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            Google Calendar Setup
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Success!</strong> Google Calendar integration is ready to use.
                            </div>
                            
                            <?php if (!empty($warnings)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> Some tables or indexes already exist. This is normal if you've run this setup before.
                                </div>
                            <?php endif; ?>
                            
                            <h5 class="mt-4">Database Status:</h5>
                            <ul class="list-group mb-4">
                                <?php
                                // Verify tables exist
                                $table1 = mysqli_query($conn, "SHOW TABLES LIKE 'user_google_tokens'");
                                $table2 = mysqli_query($conn, "SHOW TABLES LIKE 'appointment_calendar_sync'");
                                $table1Exists = $table1 && mysqli_num_rows($table1) > 0;
                                $table2Exists = $table2 && mysqli_num_rows($table2) > 0;
                                ?>
                                <li class="list-group-item">
                                    <i class="fas fa-<?php echo $table1Exists ? 'check-circle text-success' : 'times-circle text-danger'; ?> me-2"></i>
                                    <strong>user_google_tokens</strong> - 
                                    <?php echo $table1Exists ? 'Ready' : 'Missing'; ?>
                                    <small class="text-muted d-block">Stores Google OAuth tokens for users</small>
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-<?php echo $table2Exists ? 'check-circle text-success' : 'times-circle text-danger'; ?> me-2"></i>
                                    <strong>appointment_calendar_sync</strong> - 
                                    <?php echo $table2Exists ? 'Ready' : 'Missing'; ?>
                                    <small class="text-muted d-block">Tracks synced appointments</small>
                                </li>
                            </ul>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Next Steps:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Configure your Google OAuth credentials in <code>config/google_calendar_config.php</code></li>
                                    <li>Users can now connect their Google Calendar from their dashboard</li>
                                    <li>Appointments will automatically sync to Google Calendar</li>
                                </ol>
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="index.php" class="btn btn-primary">
                                    <i class="fas fa-home me-2"></i>Go to Dashboard
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Error!</strong> Failed to create some tables.
                            </div>
                            
                            <h5 class="mt-4">Errors:</h5>
                            <div class="alert alert-warning">
                                <?php foreach ($errors as $error): ?>
                                    <p class="mb-1"><i class="fas fa-times-circle me-2"></i><?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> If the tables already exist, this is normal. You can safely ignore this error.
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-home me-2"></i>Go to Dashboard
                                </a>
                                <button onclick="location.reload()" class="btn btn-primary">
                                    <i class="fas fa-redo me-2"></i>Try Again
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card shadow mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-cog me-2"></i>Configuration Guide
                        </h5>
                    </div>
                    <div class="card-body">
                        <h6><i class="fas fa-key me-2 text-warning"></i>Google OAuth Setup:</h6>
                        <ol>
                            <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                            <li>Create a new project or select existing one</li>
                            <li>Enable Google Calendar API</li>
                            <li>Create OAuth 2.0 credentials</li>
                            <li>Add authorized redirect URI: <code><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']); ?>/api/google_calendar_callback.php</code></li>
                            <li>Copy Client ID and Client Secret to <code>config/google_calendar_config.php</code></li>
                        </ol>
                        
                        <h6 class="mt-4"><i class="fas fa-users me-2 text-success"></i>Test Users (for unverified apps):</h6>
                        <p>If your app is not verified, add test users in Google Cloud Console:</p>
                        <ul>
                            <li>Go to OAuth consent screen</li>
                            <li>Scroll to "Test users" section</li>
                            <li>Add email addresses of users who need access</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
