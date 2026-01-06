<?php
/**
 * Email Setup Verification Script
 * San Benito Health Center
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';
require_once 'config/email.php';

// Only allow admin access
requireAdmin();

echo "<h2>Email Setup Verification</h2>";
echo "<p>Testing email configuration...</p>";

// Test 1: Check configuration
echo "<h3>1. Configuration Check</h3>";
$config = getEmailConfig();
echo "<ul>";
echo "<li>Email Mode: " . $config['mode'] . "</li>";
echo "<li>SMTP Host: " . $config['smtp_host'] . "</li>";
echo "<li>SMTP Port: " . $config['smtp_port'] . "</li>";
echo "<li>Username: " . (!empty($config['smtp_username']) ? 'Configured ✅' : 'Not configured ❌') . "</li>";
echo "<li>Password: " . (!empty($config['smtp_password']) ? 'Configured ✅' : 'Not configured ❌') . "</li>";
echo "</ul>";

// Test 2: Check if email is configured
echo "<h3>2. Email Configuration Status</h3>";
if (isEmailConfigured()) {
    echo "<p style='color: green;'>✅ Email is properly configured</p>";
} else {
    echo "<p style='color: red;'>❌ Email is not properly configured</p>";
}

// Test 3: Check PHPMailer availability
echo "<h3>3. PHPMailer Availability</h3>";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "<p style='color: green;'>✅ PHPMailer is available</p>";
    
    // Test PHPMailer classes
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo "<p style='color: green;'>✅ PHPMailer classes loaded successfully</p>";
        } else {
            echo "<p style='color: red;'>❌ PHPMailer classes not found</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ PHPMailer error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ PHPMailer not found (vendor/autoload.php missing)</p>";
}

// Test 4: Send test email
echo "<h3>4. Test Email Sending</h3>";
$test_email = "sanbenitohealthcenter0123@gmail.com"; // Send to your own email
echo "<p>Sending test approval email to: $test_email</p>";

$test_result = sendApprovalEmail($test_email, "Test User", "resident");

if ($test_result) {
    echo "<p style='color: green;'>✅ Test approval email sent successfully to $test_email</p>";
} else {
    echo "<p style='color: red;'>❌ Test approval email failed to send to $test_email</p>";
}

// Test simple email function
echo "<p>Testing simple email function...</p>";
$simple_result = sendSimpleEmail($test_email, "Simple Test Email", "This is a simple test email from San Benito Health Center.");

if ($simple_result) {
    echo "<p style='color: green;'>✅ Simple test email sent successfully</p>";
} else {
    echo "<p style='color: red;'>❌ Simple test email failed</p>";
}

// Test 5: Check PHP mail function
echo "<h3>5. PHP Mail Function Test</h3>";
if (function_exists('mail')) {
    echo "<p style='color: green;'>✅ PHP mail() function is available</p>";
} else {
    echo "<p style='color: red;'>❌ PHP mail() function is not available</p>";
}

// Test 6: Check error logs
echo "<h3>6. Recent Error Logs</h3>";
$log_file = ini_get('error_log');
if ($log_file && file_exists($log_file)) {
    $logs = file_get_contents($log_file);
    $recent_logs = array_slice(explode("\n", $logs), -10); // Last 10 lines
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 200px; overflow-y: auto;'>";
    foreach ($recent_logs as $log) {
        if (stripos($log, 'email') !== false || stripos($log, 'mail') !== false) {
            echo htmlspecialchars($log) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "<p>No error log file found or accessible</p>";
}

echo "<hr>";
echo "<p><a href='users.php'>← Back to User Management</a></p>";
echo "<p><a href='test_email.php'>Go to Full Email Test Page →</a></p>";
?>