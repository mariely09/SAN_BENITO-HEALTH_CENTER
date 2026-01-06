<?php
/**
 * Email Configuration Settings
 * San Benito Health Center
 * 
 * Instructions for XAMPP Users:
 * 1. For development: Keep EMAIL_MODE as 'simulate'
 * 2. For production: Change EMAIL_MODE to 'smtp' and configure SMTP settings
 * 3. For Gmail SMTP: Enable 2FA and create App Password
 */

// Email Mode: 'simulate' for development, 'smtp' for production
define('EMAIL_MODE', 'smtp'); // Change to 'smtp' for real emails

// SMTP Configuration (only used when EMAIL_MODE = 'smtp')
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your email username
define('SMTP_PASSWORD', 'your-app-password'); // Your email password or app password
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

// Email Addresses
define('FROM_EMAIL', 'your-email@gmail.com');
define('FROM_NAME', 'San Benito Health Center');
define('REPLY_TO_EMAIL', 'your-email@gmail.com');

// Development Settings
define('SIMULATE_EMAIL_DIR', __DIR__ . '/../emails');
define('LOG_EMAIL_ATTEMPTS', true);

/**
 * Get email configuration based on mode
 */
function getEmailConfig() {
    return [
        'mode' => EMAIL_MODE,
        'smtp_host' => SMTP_HOST,
        'smtp_port' => SMTP_PORT,
        'smtp_username' => SMTP_USERNAME,
        'smtp_password' => SMTP_PASSWORD,
        'smtp_secure' => SMTP_SECURE,
        'from_email' => FROM_EMAIL,
        'from_name' => FROM_NAME,
        'reply_to' => REPLY_TO_EMAIL,
        'simulate_dir' => SIMULATE_EMAIL_DIR,
        'log_attempts' => LOG_EMAIL_ATTEMPTS
    ];
}

/**
 * Check if email is properly configured
 */
function isEmailConfigured() {
    if (EMAIL_MODE === 'simulate') {
        return true; // Simulation is always available
    }
    
    if (EMAIL_MODE === 'smtp') {
        return !empty(SMTP_USERNAME) && !empty(SMTP_PASSWORD);
    }
    
    return false;
}

?>

