<?php
/**
 * Email Configuration and Functions
 * San Benito Health Center
 */

require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Get email configuration
$email_config = getEmailConfig();

// Configure PHP mail settings only if using SMTP mode
if ($email_config['mode'] === 'smtp' && isEmailConfigured()) {
    ini_set('SMTP', $email_config['smtp_host']);
    ini_set('smtp_port', $email_config['smtp_port']);
    ini_set('sendmail_from', $email_config['from_email']);
}

/**
 * Send email notification for user approval using PHPMailer
 */
function sendApprovalEmail($user_email, $user_name, $user_role) {
    global $email_config;
    
    // Validate email
    if (empty($user_email) || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: " . $user_email);
        return false;
    }
    
    $subject = "Account Approved - San Benito Health Center";
    
    // Check email mode
    if ($email_config['mode'] === 'simulate') {
        // Simulate email for development
        $message = createSimpleApprovalEmail($user_name, $user_role);
        return simulateEmailSending($user_email, $subject, $message);
    }
    
    // Try to send real email using PHPMailer
    if ($email_config['mode'] === 'smtp' && isEmailConfigured()) {
        return sendApprovalEmailWithPHPMailer($user_email, $user_name, $user_role);
    }
    
    // Fallback to simulation if SMTP not configured
    error_log("Email not configured properly, falling back to simulation");
    $message = createSimpleApprovalEmail($user_name, $user_role);
    return simulateEmailSending($user_email, $subject, $message);
}

/**
 * Send approval email using PHPMailer (more reliable)
 */
function sendApprovalEmailWithPHPMailer($user_email, $user_name, $user_role) {
    // Check if PHPMailer is available
    if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
        error_log("PHPMailer not found, falling back to basic mail function");
        return sendApprovalEmailFallback($user_email, $user_name, $user_role);
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 0; // Disable debug output
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Timeout = 60;

        $mail->Host = SMTP_HOST;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // SSL/TLS options to fix certificate verification issues
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($user_email, $user_name);
        $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Account Approved - San Benito Health Center";
        
        // Create HTML email content
        $mail->Body = createApprovalEmailTemplate($user_name, $user_role);
        
        // Create plain text version
        $mail->AltBody = createSimpleApprovalEmail($user_name, $user_role);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ PHPMailer: Approval email sent successfully to: $user_email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ PHPMailer failed for $user_email: " . $e->getMessage());
        
        // Try fallback method
        return sendApprovalEmailFallback($user_email, $user_name, $user_role);
    }
}

/**
 * Fallback approval email function using basic PHP mail()
 */
function sendApprovalEmailFallback($user_email, $user_name, $user_role) {
    try {
        $subject = "Account Approved - San Benito Health Center";
        $message = createSimpleApprovalEmail($user_name, $user_role);
        
        $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . REPLY_TO_EMAIL . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $result = mail($user_email, $subject, $message, $headers);
        
        if ($result) {
            error_log("✅ Fallback: Approval email sent successfully to: $user_email");
        } else {
            error_log("❌ Fallback: Approval email failed for: $user_email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ Fallback approval email failed for $user_email: " . $e->getMessage());
        return false;
    }
}

/**
 * Send simple text email using PHPMailer (more reliable)
 */
function sendSimpleEmail($user_email, $subject, $message) {
    global $email_config;
    
    // Use PHPMailer for better SMTP support
    return sendEmailWithPHPMailer($user_email, $subject, $message, 'text');
}

/**
 * Send HTML email using PHPMailer
 */
function sendHtmlEmail($user_email, $subject, $message) {
    global $email_config;
    
    // Use PHPMailer for better SMTP support
    return sendEmailWithPHPMailer($user_email, $subject, $message, 'html');
}

/**
 * Send email using PHPMailer (unified function for text and HTML)
 */
function sendEmailWithPHPMailer($user_email, $subject, $message, $type = 'text') {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 0; // Disable debug output to prevent header issues
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Timeout = 60;

        $mail->Host = SMTP_HOST;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // SSL/TLS options to fix certificate verification issues
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($user_email);
        $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME);

        // Content
        if ($type === 'html') {
            $mail->isHTML(true);
            $mail->Body = $message;
            // Create plain text version for HTML emails
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
        } else {
            $mail->isHTML(false);
            $mail->Body = $message;
        }
        
        $mail->Subject = $subject;
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ PHPMailer ($type): Email sent successfully to: $user_email");
        } else {
            error_log("❌ PHPMailer ($type): Email failed to send to: $user_email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ PHPMailer ($type) error for $user_email: " . $e->getMessage());
        
        // Try fallback with basic mail() function
        return sendEmailFallback($user_email, $subject, $message, $type);
    }
}

/**
 * Fallback email function using basic PHP mail()
 */
function sendEmailFallback($user_email, $subject, $message, $type = 'text') {
    try {
        $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . REPLY_TO_EMAIL . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        if ($type === 'html') {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }

        $result = mail($user_email, $subject, $message, $headers);
        
        if ($result) {
            error_log("✅ Fallback ($type): Email sent successfully to: $user_email");
        } else {
            error_log("❌ Fallback ($type): Email failed to send to: $user_email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ Fallback ($type) email failed for $user_email: " . $e->getMessage());
        return false;
    }
}

/**
 * Create simple text email for approval
 */
function createSimpleApprovalEmail($user_name, $user_role) {
    $login_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/login.php";
    
    return "
ACCOUNT APPROVED - San Benito Health Center

Dear " . $user_name . ",

Congratulations! Your account has been successfully approved by our administrator.

Account Details:
- Name: " . $user_name . "
- Role: " . ucfirst($user_role) . "
- Status: Approved
- Approval Date: " . date('F j, Y') . "

You can now access all features available to your role in the San Benito Health Center system.

Login to your account: " . $login_url . "

If you have any questions or need assistance, please contact our support team at: " . REPLY_TO_EMAIL . "

Welcome to San Benito Health Center!

---
This is an automated message from San Benito Health Center.
Please do not reply to this email.
";
}

/**
 * Alternative email sending method using sendmail
 */
function sendEmailAlternative($to, $subject, $message) {
    try {
        // Create temporary file for email content
        $temp_file = tempnam(sys_get_temp_dir(), 'email_');
        
        $email_content = "To: $to\r\n";
        $email_content .= "Subject: $subject\r\n";
        $email_content .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
        $email_content .= "Content-Type: text/html; charset=UTF-8\r\n";
        $email_content .= "MIME-Version: 1.0\r\n\r\n";
        $email_content .= $message;
        
        file_put_contents($temp_file, $email_content);
        
        // Try to send using sendmail if available
        if (function_exists('exec')) {
            $result = exec("sendmail -t < $temp_file 2>&1", $output, $return_code);
            unlink($temp_file);
            return $return_code === 0;
        }
        
        unlink($temp_file);
        return false;
        
    } catch (Exception $e) {
        error_log("Alternative email method error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create HTML email template for approval notification
 */
function createApprovalEmailTemplate($user_name, $user_role) {
    $login_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/login.php";
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Account Approved</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4; 
            }
            .email-container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 10px; 
                overflow: hidden; 
                box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #2c3e50, #34495e); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 28px; 
                font-weight: 600; 
            }
            .content { 
                padding: 40px 30px; 
                background: white; 
            }
            .success-badge { 
                background: #28a745; 
                color: white; 
                padding: 10px 20px; 
                border-radius: 25px; 
                display: inline-block; 
                margin: 15px 0; 
                font-weight: 600; 
                font-size: 14px; 
            }
            .user-details { 
                background: #f8f9fa; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0; 
                border-left: 4px solid #28a745; 
            }
            .user-details h3 { 
                margin-top: 0; 
                color: #2c3e50; 
            }
            .detail-item { 
                margin: 8px 0; 
                font-size: 15px; 
            }
            .detail-label { 
                font-weight: 600; 
                color: #555; 
            }
            .login-btn { 
                display: inline-block; 
                background: #007bff; 
                color: white; 
                padding: 15px 30px; 
                text-decoration: none; 
                border-radius: 8px; 
                margin: 20px 0; 
                font-weight: 600; 
                transition: background 0.3s; 
            }
            .login-btn:hover { 
                background: #0056b3; 
                color: white; 
            }
            .footer { 
                background: #f8f9fa; 
                padding: 20px; 
                text-align: center; 
                color: #666; 
                font-size: 14px; 
                border-top: 1px solid #eee; 
            }
            .welcome-message { 
                font-size: 16px; 
                line-height: 1.8; 
                color: #555; 
            }
            .highlight { 
                color: #28a745; 
                font-weight: 600; 
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>🎉 Account Approved!</h1>
            </div>
            
            <div class='content'>
                <p class='welcome-message'>Dear <strong>" . htmlspecialchars($user_name) . "</strong>,</p>
                
                <div class='success-badge'>✅ Account Successfully Approved</div>
                
                <p class='welcome-message'>
                    Congratulations! Your account has been <span class='highlight'>successfully approved</span> 
                    by our administrator. You now have full access to the San Benito Health Center system.
                </p>
                
                <div class='user-details'>
                    <h3>📋 Your Account Details</h3>
                    <div class='detail-item'>
                        <span class='detail-label'>Full Name:</span> " . htmlspecialchars($user_name) . "
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Role:</span> " . ucfirst(htmlspecialchars($user_role)) . "
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Status:</span> <span class='highlight'>Approved</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Approval Date:</span> " . date('F j, Y') . "
                    </div>
                </div>
                
                <p class='welcome-message'>
                    You can now access all features and services available to your role. 
                    Click the button below to log in to your account:
                </p>
                
                <div style='text-align: center;'>
                    <a href='$login_url' class='login-btn'>🔐 Login to Your Account</a>
                </div>
                
                <p class='welcome-message'>
                    If you have any questions or need assistance getting started, 
                    please don't hesitate to contact our support team.
                </p>
                
                <p class='welcome-message'>
                    <strong>Welcome to San Benito Health Center!</strong><br>
                    We're excited to have you as part of our community.
                </p>
            </div>
            
            <div class='footer'>
                <p>
                    <strong>San Benito Health Center</strong><br>
                    This is an automated message. Please do not reply to this email.<br>
                    For support, contact us at: <a href='mailto:" . REPLY_TO_EMAIL . "'>" . REPLY_TO_EMAIL . "</a>
                </p>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Check if running on local development environment
 */
function isLocalDevelopment() {
    $local_hosts = ['localhost', '127.0.0.1', '::1'];
    $server_name = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
    
    return in_array($server_name, $local_hosts) || 
           strpos($server_name, 'xampp') !== false || 
           strpos($server_name, 'wamp') !== false ||
           strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'Apache') !== false;
}

/**
 * Simulate email sending for local development
 */
function simulateEmailSending($user_email, $subject, $message, $type = 'text') {
    // Create emails directory if it doesn't exist
    $email_dir = __DIR__ . '/../emails';
    if (!file_exists($email_dir)) {
        mkdir($email_dir, 0777, true);
    }
    
    // Create email file
    $timestamp = date('Y-m-d_H-i-s');
    $safe_email = preg_replace('/[^a-zA-Z0-9@._-]/', '_', $user_email);
    $filename = $email_dir . "/email_{$timestamp}_{$safe_email}.txt";
    
    $email_content = "=== EMAIL SIMULATION ===\n";
    $email_content .= "To: $user_email\n";
    $email_content .= "Subject: $subject\n";
    $email_content .= "Type: $type\n";
    $email_content .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $email_content .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\n";
    $email_content .= "Reply-To: " . REPLY_TO_EMAIL . "\n";
    $email_content .= "\n--- MESSAGE CONTENT ---\n";
    $email_content .= $message;
    $email_content .= "\n\n=== END EMAIL ===\n";
    
    // Save email to file
    $saved = file_put_contents($filename, $email_content);
    
    if ($saved) {
        error_log("EMAIL SIMULATED: Saved to $filename for $user_email");
        return true;
    } else {
        error_log("EMAIL SIMULATION FAILED: Could not save file for $user_email");
        return false;
    }
}

/**
 * Send appointment notification email to workers
 */
function sendAppointmentNotificationToWorkers($conn, $appointment_data) {
    global $email_config;
    
    // Get all approved workers and admins with email addresses
    $workers_query = "SELECT email, fullname FROM users 
                      WHERE role IN ('worker', 'admin') 
                      AND status = 'approved' 
                      AND email IS NOT NULL 
                      AND email != ''";
    $workers_result = mysqli_query($conn, $workers_query);
    
    if (!$workers_result || mysqli_num_rows($workers_result) === 0) {
        error_log("No workers/admins found to notify about appointment");
        return false;
    }
    
    $success_count = 0;
    $total_count = 0;
    
    // Send email to each worker/admin
    while ($worker = mysqli_fetch_assoc($workers_result)) {
        $total_count++;
        $worker_email = $worker['email'];
        $worker_name = $worker['fullname'];
        
        // Validate email
        if (!filter_var($worker_email, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid worker email: " . $worker_email);
            continue;
        }
        
        $subject = "New Appointment Request - San Benito Health Center";
        
        // Check email mode
        if ($email_config['mode'] === 'simulate') {
            // Simulate email for development
            $message = createAppointmentNotificationText($appointment_data, $worker_name);
            if (simulateEmailSending($worker_email, $subject, $message)) {
                $success_count++;
            }
        } else if ($email_config['mode'] === 'smtp' && isEmailConfigured()) {
            // Send real email using PHPMailer
            if (sendAppointmentNotificationEmail($worker_email, $worker_name, $appointment_data)) {
                $success_count++;
            }
        } else {
            // Fallback to simulation
            $message = createAppointmentNotificationText($appointment_data, $worker_name);
            if (simulateEmailSending($worker_email, $subject, $message)) {
                $success_count++;
            }
        }
    }
    
    error_log("Appointment notification sent to $success_count out of $total_count workers/admins");
    return $success_count > 0;
}

/**
 * Send appointment notification email using PHPMailer
 */
function sendAppointmentNotificationEmail($worker_email, $worker_name, $appointment_data) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Timeout = 60;

        $mail->Host = SMTP_HOST;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // SSL/TLS options
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($worker_email, $worker_name);
        $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "New Appointment Request - San Benito Health Center";
        
        // Create HTML email content
        $mail->Body = createAppointmentNotificationHTML($appointment_data, $worker_name);
        
        // Create plain text version
        $mail->AltBody = createAppointmentNotificationText($appointment_data, $worker_name);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ Appointment notification sent to worker: $worker_email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ Failed to send appointment notification to $worker_email: " . $e->getMessage());
        return false;
    }
}

/**
 * Create plain text appointment notification
 */
function createAppointmentNotificationText($appointment_data, $worker_name) {
    $dashboard_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/appointments.php";
    
    $message = "NEW APPOINTMENT REQUEST - San Benito Health Center\n\n";
    $message .= "Dear " . $worker_name . ",\n\n";
    $message .= "A new appointment request has been submitted and requires your attention.\n\n";
    $message .= "APPOINTMENT DETAILS:\n";
    $message .= "-------------------\n";
    $message .= "Patient Name: " . $appointment_data['patient_name'] . "\n";
    $message .= "Appointment Type: " . $appointment_data['appointment_type'] . "\n";
    $message .= "Preferred Date: " . date('F j, Y', strtotime($appointment_data['preferred_datetime'])) . "\n";
    $message .= "Preferred Time: " . date('g:i A', strtotime($appointment_data['preferred_datetime'])) . "\n";
    $message .= "Status: Pending\n";
    
    if (!empty($appointment_data['notes'])) {
        $message .= "\nAdditional Notes:\n" . $appointment_data['notes'] . "\n";
    }
    
    $message .= "\n-------------------\n\n";
    $message .= "Please log in to the system to review and process this appointment request:\n";
    $message .= $dashboard_url . "\n\n";
    $message .= "Thank you for your prompt attention to this matter.\n\n";
    $message .= "---\n";
    $message .= "This is an automated notification from San Benito Health Center.\n";
    $message .= "Please do not reply to this email.\n";
    
    return $message;
}

/**
 * Create HTML appointment notification
 */
function createAppointmentNotificationHTML($appointment_data, $worker_name) {
    $dashboard_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/appointments.php";
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>New Appointment Request</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4; 
            }
            .email-container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 10px; 
                overflow: hidden; 
                box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #007bff, #0056b3); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 26px; 
                font-weight: 600; 
            }
            .content { 
                padding: 40px 30px; 
                background: white; 
            }
            .alert-badge { 
                background: #ffc107; 
                color: #000; 
                padding: 10px 20px; 
                border-radius: 25px; 
                display: inline-block; 
                margin: 15px 0; 
                font-weight: 600; 
                font-size: 14px; 
            }
            .appointment-details { 
                background: #f8f9fa; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0; 
                border-left: 4px solid #007bff; 
            }
            .appointment-details h3 { 
                margin-top: 0; 
                color: #2c3e50; 
            }
            .detail-item { 
                margin: 10px 0; 
                font-size: 15px; 
            }
            .detail-label { 
                font-weight: 600; 
                color: #555; 
                display: inline-block;
                min-width: 140px;
            }
            .detail-value {
                color: #333;
            }
            .notes-section {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 15px 0;
                border-radius: 5px;
            }
            .notes-section h4 {
                margin-top: 0;
                color: #856404;
            }
            .action-btn { 
                display: inline-block; 
                background: #007bff; 
                color: white; 
                padding: 15px 30px; 
                text-decoration: none; 
                border-radius: 8px; 
                margin: 20px 0; 
                font-weight: 600; 
                transition: background 0.3s; 
            }
            .action-btn:hover { 
                background: #0056b3; 
                color: white; 
            }
            .footer { 
                background: #f8f9fa; 
                padding: 20px; 
                text-align: center; 
                color: #666; 
                font-size: 14px; 
                border-top: 1px solid #eee; 
            }
            .message-text { 
                font-size: 16px; 
                line-height: 1.8; 
                color: #555; 
            }
            .highlight { 
                color: #007bff; 
                font-weight: 600; 
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>🔔 New Appointment Request</h1>
            </div>
            
            <div class='content'>
                <p class='message-text'>Dear <strong>" . htmlspecialchars($worker_name) . "</strong>,</p>
                
                <div class='alert-badge'>⚠️ Action Required</div>
                
                <p class='message-text'>
                    A new appointment request has been submitted and requires your attention.
                </p>
                
                <div class='appointment-details'>
                    <h3>📋 Appointment Details</h3>
                    <div class='detail-item'>
                        <span class='detail-label'>Patient Name:</span>
                        <span class='detail-value'>" . htmlspecialchars($appointment_data['patient_name']) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Appointment Type:</span>
                        <span class='detail-value'>" . htmlspecialchars($appointment_data['appointment_type']) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Preferred Date:</span>
                        <span class='detail-value'>" . date('F j, Y', strtotime($appointment_data['preferred_datetime'])) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Preferred Time:</span>
                        <span class='detail-value'>" . date('g:i A', strtotime($appointment_data['preferred_datetime'])) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Status:</span>
                        <span class='detail-value'><span style='color: #ffc107; font-weight: 600;'>Pending</span></span>
                    </div>
                </div>
                
                " . (!empty($appointment_data['notes']) ? "
                <div class='notes-section'>
                    <h4>📝 Additional Notes:</h4>
                    <p style='margin: 5px 0; color: #856404;'>" . nl2br(htmlspecialchars($appointment_data['notes'])) . "</p>
                </div>
                " : "") . "
                
                <p class='message-text'>
                    Please log in to the system to review and process this appointment request.
                </p>
                
                <div style='text-align: center;'>
                    <a href='$dashboard_url' class='action-btn'>📅 View Appointment</a>
                </div>
                
                <p class='message-text'>
                    Thank you for your prompt attention to this matter.
                </p>
            </div>
            
            <div class='footer'>
                <p>
                    <strong>San Benito Health Center</strong><br>
                    This is an automated notification. Please do not reply to this email.<br>
                    For support, contact us at: <a href='mailto:" . REPLY_TO_EMAIL . "'>" . REPLY_TO_EMAIL . "</a>
                </p>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Get simulated emails (for testing)
 */
function getSimulatedEmails() {
    $email_dir = __DIR__ . '/../emails';
    $emails = [];
    
    if (file_exists($email_dir)) {
        $files = glob($email_dir . '/email_*.txt');
        rsort($files); // Most recent first
        
        foreach (array_slice($files, 0, 10) as $file) { // Last 10 emails
            $content = file_get_contents($file);
            $emails[] = [
                'file' => basename($file),
                'content' => $content,
                'time' => filemtime($file)
            ];
        }
    }
    
    return $emails;
}

/**
 * Test email configuration
 */
function testEmailConfiguration() {
    if (isLocalDevelopment()) {
        return simulateEmailSending("test@example.com", "Test Email", "This is a test email from San Benito Health Center system.");
    }
    
    $test_email = "test@example.com";
    $test_message = "This is a test email from San Benito Health Center system.";
    
    $headers = array();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/plain; charset=UTF-8";
    $headers[] = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">";
    
    $headers_string = implode("\r\n", $headers);
    
    return mail($test_email, "Test Email", $test_message, $headers_string);
}

/**
 * Configure XAMPP for Gmail SMTP (instructions)
 */
function getXamppEmailInstructions() {
    return [
        'step1' => 'Enable 2-Factor Authentication on your Gmail account',
        'step2' => 'Generate an App Password for your Gmail account',
        'step3' => 'Update SMTP_USERNAME and SMTP_PASSWORD in config/email.php',
        'step4' => 'Install and configure a local SMTP server like hMailServer or Mercury',
        'step5' => 'Or use the email simulation feature for development testing'
    ];
}

/**
 * Send appointment status update notification to resident
 */
function sendAppointmentStatusUpdateToResident($conn, $appointment_id, $new_status, $cancellation_reason = null) {
    global $email_config;
    
    // Get appointment and user details
    $query = "SELECT a.*, u.email, u.fullname as user_fullname 
              FROM appointments a 
              LEFT JOIN users u ON a.user_id = u.id 
              WHERE a.id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $appointment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $appointment = mysqli_fetch_assoc($result);
    
    if (!$appointment || empty($appointment['email'])) {
        error_log("No appointment or email found for appointment ID: $appointment_id");
        return false;
    }
    
    $resident_email = $appointment['email'];
    $resident_name = $appointment['user_fullname'];
    
    // Validate email
    if (!filter_var($resident_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid resident email: " . $resident_email);
        return false;
    }
    
    // Prepare appointment data
    $appointment_data = [
        'patient_name' => $appointment['fullname'],
        'appointment_type' => $appointment['appointment_type'],
        'preferred_datetime' => $appointment['preferred_datetime'],
        'notes' => $appointment['notes'],
        'status' => $new_status,
        'cancellation_reason' => $cancellation_reason
    ];
    
    $subject = "Appointment " . ucfirst($new_status) . " - San Benito Health Center";
    
    // Check email mode
    if ($email_config['mode'] === 'simulate') {
        // Simulate email for development
        $message = createAppointmentStatusUpdateText($appointment_data, $resident_name, $new_status);
        return simulateEmailSending($resident_email, $subject, $message);
    } else if ($email_config['mode'] === 'smtp' && isEmailConfigured()) {
        // Send real email using PHPMailer
        return sendAppointmentStatusUpdateEmail($resident_email, $resident_name, $appointment_data, $new_status);
    } else {
        // Fallback to simulation
        $message = createAppointmentStatusUpdateText($appointment_data, $resident_name, $new_status);
        return simulateEmailSending($resident_email, $subject, $message);
    }
}

/**
 * Send appointment status update email using PHPMailer
 */
function sendAppointmentStatusUpdateEmail($resident_email, $resident_name, $appointment_data, $new_status) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Timeout = 60;

        $mail->Host = SMTP_HOST;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // SSL/TLS options
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($resident_email, $resident_name);
        $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Appointment " . ucfirst($new_status) . " - San Benito Health Center";
        
        // Create HTML email content
        $mail->Body = createAppointmentStatusUpdateHTML($appointment_data, $resident_name, $new_status);
        
        // Create plain text version
        $mail->AltBody = createAppointmentStatusUpdateText($appointment_data, $resident_name, $new_status);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ Appointment status update sent to resident: $resident_email (Status: $new_status)");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ Failed to send appointment status update to $resident_email: " . $e->getMessage());
        return false;
    }
}

/**
 * Create plain text appointment status update notification
 */
function createAppointmentStatusUpdateText($appointment_data, $resident_name, $new_status) {
    $dashboard_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/resident_dashboard.php";
    
    $status_messages = [
        'confirmed' => [
            'title' => 'APPOINTMENT CONFIRMED',
            'message' => 'Great news! Your appointment has been confirmed by our health center staff.',
            'action' => 'Your appointment is now scheduled. Please arrive 10 minutes before your appointment time.'
        ],
        'completed' => [
            'title' => 'APPOINTMENT COMPLETED',
            'message' => 'Your appointment has been successfully completed.',
            'action' => 'Thank you for visiting San Benito Health Center. We hope you had a positive experience.'
        ],
        'cancelled' => [
            'title' => 'APPOINTMENT CANCELLED',
            'message' => 'Your appointment has been cancelled.',
            'action' => 'If you need to reschedule, please submit a new appointment request through your dashboard.'
        ]
    ];
    
    $status_info = $status_messages[$new_status] ?? $status_messages['confirmed'];
    
    $message = $status_info['title'] . " - San Benito Health Center\n\n";
    $message .= "Dear " . $resident_name . ",\n\n";
    $message .= $status_info['message'] . "\n\n";
    
    // Add cancellation reason if provided
    if ($new_status === 'cancelled' && !empty($appointment_data['cancellation_reason'])) {
        $message .= "REASON FOR CANCELLATION:\n";
        $message .= $appointment_data['cancellation_reason'] . "\n\n";
    }
    
    $message .= "APPOINTMENT DETAILS:\n";
    $message .= "-------------------\n";
    $message .= "Patient Name: " . $appointment_data['patient_name'] . "\n";
    $message .= "Appointment Type: " . $appointment_data['appointment_type'] . "\n";
    $message .= "Date: " . date('F j, Y', strtotime($appointment_data['preferred_datetime'])) . "\n";
    $message .= "Time: " . date('g:i A', strtotime($appointment_data['preferred_datetime'])) . "\n";
    $message .= "Status: " . ucfirst($new_status) . "\n";
    
    if (!empty($appointment_data['notes'])) {
        $message .= "\nNotes:\n" . $appointment_data['notes'] . "\n";
    }
    
    $message .= "\n-------------------\n\n";
    $message .= $status_info['action'] . "\n\n";
    $message .= "View your appointments: " . $dashboard_url . "\n\n";
    
    if ($new_status === 'confirmed') {
        $message .= "IMPORTANT REMINDERS:\n";
        $message .= "• Please arrive 10 minutes before your scheduled time\n";
        $message .= "• Bring a valid ID and any relevant medical documents\n";
        $message .= "• If you need to cancel or reschedule, please inform us as soon as possible\n\n";
    }
    
    $message .= "If you have any questions, please contact us at: " . REPLY_TO_EMAIL . "\n\n";
    $message .= "---\n";
    $message .= "This is an automated notification from San Benito Health Center.\n";
    $message .= "Please do not reply to this email.\n";
    
    return $message;
}

/**
 * Create HTML appointment status update notification
 */
function createAppointmentStatusUpdateHTML($appointment_data, $resident_name, $new_status) {
    $dashboard_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/resident_dashboard.php";
    
    $status_config = [
        'confirmed' => [
            'color' => '#28a745',
            'icon' => '✅',
            'title' => 'Appointment Confirmed',
            'message' => 'Great news! Your appointment has been <span class="highlight">confirmed</span> by our health center staff.',
            'action' => 'Your appointment is now scheduled. Please arrive 10 minutes before your appointment time.',
            'show_reminders' => true
        ],
        'completed' => [
            'color' => '#17a2b8',
            'icon' => '✔️',
            'title' => 'Appointment Completed',
            'message' => 'Your appointment has been successfully completed.',
            'action' => 'Thank you for visiting San Benito Health Center. We hope you had a positive experience.',
            'show_reminders' => false
        ],
        'cancelled' => [
            'color' => '#dc3545',
            'icon' => '❌',
            'title' => 'Appointment Cancelled',
            'message' => 'Your appointment has been cancelled.',
            'action' => 'If you need to reschedule, please submit a new appointment request through your dashboard.',
            'show_reminders' => false
        ]
    ];
    
    $config = $status_config[$new_status] ?? $status_config['confirmed'];
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Appointment " . ucfirst($new_status) . "</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4; 
            }
            .email-container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 10px; 
                overflow: hidden; 
                box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, " . $config['color'] . ", " . $config['color'] . "dd); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 26px; 
                font-weight: 600; 
            }
            .content { 
                padding: 40px 30px; 
                background: white; 
            }
            .status-badge { 
                background: " . $config['color'] . "; 
                color: white; 
                padding: 10px 20px; 
                border-radius: 25px; 
                display: inline-block; 
                margin: 15px 0; 
                font-weight: 600; 
                font-size: 14px; 
            }
            .appointment-details { 
                background: #f8f9fa; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0; 
                border-left: 4px solid " . $config['color'] . "; 
            }
            .appointment-details h3 { 
                margin-top: 0; 
                color: #2c3e50; 
            }
            .detail-item { 
                margin: 10px 0; 
                font-size: 15px; 
            }
            .detail-label { 
                font-weight: 600; 
                color: #555; 
                display: inline-block;
                min-width: 140px;
            }
            .detail-value {
                color: #333;
            }
            .notes-section {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 15px 0;
                border-radius: 5px;
            }
            .notes-section h4 {
                margin-top: 0;
                color: #856404;
            }
            .reminders-section {
                background: #d1ecf1;
                border-left: 4px solid #17a2b8;
                padding: 15px;
                margin: 15px 0;
                border-radius: 5px;
            }
            .reminders-section h4 {
                margin-top: 0;
                color: #0c5460;
            }
            .reminders-section ul {
                margin: 10px 0;
                padding-left: 20px;
            }
            .reminders-section li {
                margin: 5px 0;
                color: #0c5460;
            }
            .action-btn { 
                display: inline-block; 
                background: " . $config['color'] . "; 
                color: white; 
                padding: 15px 30px; 
                text-decoration: none; 
                border-radius: 8px; 
                margin: 20px 0; 
                font-weight: 600; 
                transition: background 0.3s; 
            }
            .action-btn:hover { 
                background: " . $config['color'] . "dd; 
                color: white; 
            }
            .footer { 
                background: #f8f9fa; 
                padding: 20px; 
                text-align: center; 
                color: #666; 
                font-size: 14px; 
                border-top: 1px solid #eee; 
            }
            .message-text { 
                font-size: 16px; 
                line-height: 1.8; 
                color: #555; 
            }
            .highlight { 
                color: " . $config['color'] . "; 
                font-weight: 600; 
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>" . $config['icon'] . " " . $config['title'] . "</h1>
            </div>
            
            <div class='content'>
                <p class='message-text'>Dear <strong>" . htmlspecialchars($resident_name) . "</strong>,</p>
                
                <div class='status-badge'>" . $config['icon'] . " " . ucfirst($new_status) . "</div>
                
                <p class='message-text'>
                    " . $config['message'] . "
                </p>
                
                " . ($new_status === 'cancelled' && !empty($appointment_data['cancellation_reason']) ? "
                <div class='notes-section' style='background: #f8d7da; border-left: 4px solid #dc3545;'>
                    <h4 style='color: #721c24;'>📋 Reason for Cancellation:</h4>
                    <p style='margin: 5px 0; color: #721c24;'>" . nl2br(htmlspecialchars($appointment_data['cancellation_reason'])) . "</p>
                </div>
                " : "") . "
                
                <div class='appointment-details'>
                    <h3>📋 Appointment Details</h3>
                    <div class='detail-item'>
                        <span class='detail-label'>Patient Name:</span>
                        <span class='detail-value'>" . htmlspecialchars($appointment_data['patient_name']) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Appointment Type:</span>
                        <span class='detail-value'>" . htmlspecialchars($appointment_data['appointment_type']) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Date:</span>
                        <span class='detail-value'>" . date('F j, Y', strtotime($appointment_data['preferred_datetime'])) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Time:</span>
                        <span class='detail-value'>" . date('g:i A', strtotime($appointment_data['preferred_datetime'])) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Status:</span>
                        <span class='detail-value'><span style='color: " . $config['color'] . "; font-weight: 600;'>" . ucfirst($new_status) . "</span></span>
                    </div>
                </div>
                
                " . (!empty($appointment_data['notes']) ? "
                <div class='notes-section'>
                    <h4>📝 Notes:</h4>
                    <p style='margin: 5px 0; color: #856404;'>" . nl2br(htmlspecialchars($appointment_data['notes'])) . "</p>
                </div>
                " : "") . "
                
                " . ($config['show_reminders'] ? "
                <div class='reminders-section'>
                    <h4>⚠️ Important Reminders:</h4>
                    <ul>
                        <li>Please arrive 10 minutes before your scheduled time</li>
                        <li>Bring a valid ID and any relevant medical documents</li>
                        <li>If you need to cancel or reschedule, please inform us as soon as possible</li>
                    </ul>
                </div>
                " : "") . "
                
                <p class='message-text'>
                    " . $config['action'] . "
                </p>
                
                <div style='text-align: center;'>
                    <a href='$dashboard_url' class='action-btn'>📅 View My Appointments</a>
                </div>
                
                <p class='message-text'>
                    If you have any questions, please don't hesitate to contact us.
                </p>
            </div>
            
            <div class='footer'>
                <p>
                    <strong>San Benito Health Center</strong><br>
                    This is an automated notification. Please do not reply to this email.<br>
                    For support, contact us at: <a href='mailto:" . REPLY_TO_EMAIL . "'>" . REPLY_TO_EMAIL . "</a>
                </p>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Send vaccination schedule notification to parent/guardian
 */
function sendVaccinationScheduleNotification($parent_email, $parent_name, $vaccination_data) {
    global $email_config;
    
    // Validate email
    if (empty($parent_email) || !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid parent email: " . $parent_email);
        return false;
    }
    
    $subject = "Vaccination Scheduled - San Benito Health Center";
    
    // Check email mode
    if ($email_config['mode'] === 'simulate') {
        // Simulate email for development
        $message = createVaccinationScheduleText($vaccination_data, $parent_name);
        return simulateEmailSending($parent_email, $subject, $message);
    } else if ($email_config['mode'] === 'smtp' && isEmailConfigured()) {
        // Send real email using PHPMailer
        return sendVaccinationScheduleEmail($parent_email, $parent_name, $vaccination_data);
    } else {
        // Fallback to simulation
        $message = createVaccinationScheduleText($vaccination_data, $parent_name);
        return simulateEmailSending($parent_email, $subject, $message);
    }
}

/**
 * Send vaccination schedule email using PHPMailer
 */
function sendVaccinationScheduleEmail($parent_email, $parent_name, $vaccination_data) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Timeout = 60;

        $mail->Host = SMTP_HOST;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // SSL/TLS options
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($parent_email, $parent_name);
        $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Vaccination Scheduled - San Benito Health Center";
        
        // Create HTML email content
        $mail->Body = createVaccinationScheduleHTML($vaccination_data, $parent_name);
        
        // Create plain text version
        $mail->AltBody = createVaccinationScheduleText($vaccination_data, $parent_name);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ Vaccination schedule notification sent to parent: $parent_email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ Failed to send vaccination schedule notification to $parent_email: " . $e->getMessage());
        return false;
    }
}

/**
 * Create plain text vaccination schedule notification
 */
function createVaccinationScheduleText($vaccination_data, $parent_name) {
    $dashboard_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/resident_dashboard.php";
    
    $message = "VACCINATION SCHEDULED - San Benito Health Center\n\n";
    $message .= "Dear " . $parent_name . ",\n\n";
    $message .= "A vaccination has been scheduled for your child by our health center staff.\n\n";
    $message .= "VACCINATION DETAILS:\n";
    $message .= "-------------------\n";
    $message .= "Child's Name: " . $vaccination_data['baby_name'] . "\n";
    $message .= "Vaccine Type: " . $vaccination_data['vaccine_type'] . "\n";
    $message .= "Schedule Date: " . date('F j, Y', strtotime($vaccination_data['schedule_datetime'])) . "\n";
    $message .= "Schedule Time: " . date('g:i A', strtotime($vaccination_data['schedule_datetime'])) . "\n";
    $message .= "Status: " . ucfirst($vaccination_data['status']) . "\n";
    
    if (!empty($vaccination_data['notes'])) {
        $message .= "\nAdditional Notes:\n" . $vaccination_data['notes'] . "\n";
    }
    
    $message .= "\n-------------------\n\n";
    $message .= "IMPORTANT REMINDERS:\n";
    $message .= "• Please arrive 10 minutes before the scheduled time\n";
    $message .= "• Bring your child's immunization record/card\n";
    $message .= "• Ensure your child is well-rested and has eaten\n";
    $message .= "• Inform the health worker of any allergies or medical conditions\n";
    $message .= "• If you need to reschedule, please contact us as soon as possible\n\n";
    $message .= "View your appointments: " . $dashboard_url . "\n\n";
    $message .= "If you have any questions, please contact us at: " . REPLY_TO_EMAIL . "\n\n";
    $message .= "---\n";
    $message .= "This is an automated notification from San Benito Health Center.\n";
    $message .= "Please do not reply to this email.\n";
    
    return $message;
}

/**
 * Create HTML vaccination schedule notification
 */
function createVaccinationScheduleHTML($vaccination_data, $parent_name) {
    $dashboard_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/resident_dashboard.php";
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Vaccination Scheduled</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4; 
            }
            .email-container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 10px; 
                overflow: hidden; 
                box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #28a745, #20c997); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 26px; 
                font-weight: 600; 
            }
            .content { 
                padding: 40px 30px; 
                background: white; 
            }
            .status-badge { 
                background: #28a745; 
                color: white; 
                padding: 10px 20px; 
                border-radius: 25px; 
                display: inline-block; 
                margin: 15px 0; 
                font-weight: 600; 
                font-size: 14px; 
            }
            .vaccination-details { 
                background: #f8f9fa; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0; 
                border-left: 4px solid #28a745; 
            }
            .vaccination-details h3 { 
                margin-top: 0; 
                color: #2c3e50; 
            }
            .detail-item { 
                margin: 10px 0; 
                font-size: 15px; 
            }
            .detail-label { 
                font-weight: 600; 
                color: #555; 
                display: inline-block;
                min-width: 140px;
            }
            .detail-value {
                color: #333;
            }
            .notes-section {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 15px 0;
                border-radius: 5px;
            }
            .notes-section h4 {
                margin-top: 0;
                color: #856404;
            }
            .reminders-section {
                background: #d1ecf1;
                border-left: 4px solid #17a2b8;
                padding: 15px;
                margin: 15px 0;
                border-radius: 5px;
            }
            .reminders-section h4 {
                margin-top: 0;
                color: #0c5460;
            }
            .reminders-section ul {
                margin: 10px 0;
                padding-left: 20px;
            }
            .reminders-section li {
                margin: 5px 0;
                color: #0c5460;
            }
            .action-btn { 
                display: inline-block; 
                background: #28a745; 
                color: white; 
                padding: 15px 30px; 
                text-decoration: none; 
                border-radius: 8px; 
                margin: 20px 0; 
                font-weight: 600; 
                transition: background 0.3s; 
            }
            .action-btn:hover { 
                background: #218838; 
                color: white; 
            }
            .footer { 
                background: #f8f9fa; 
                padding: 20px; 
                text-align: center; 
                color: #666; 
                font-size: 14px; 
                border-top: 1px solid #eee; 
            }
            .message-text { 
                font-size: 16px; 
                line-height: 1.8; 
                color: #555; 
            }
            .highlight { 
                color: #28a745; 
                font-weight: 600; 
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>💉 Vaccination Scheduled</h1>
            </div>
            
            <div class='content'>
                <p class='message-text'>Dear <strong>" . htmlspecialchars($parent_name) . "</strong>,</p>
                
                <div class='status-badge'>✅ " . ucfirst($vaccination_data['status']) . "</div>
                
                <p class='message-text'>
                    A vaccination has been <span class='highlight'>scheduled</span> for your child by our health center staff.
                </p>
                
                <div class='vaccination-details'>
                    <h3>💉 Vaccination Details</h3>
                    <div class='detail-item'>
                        <span class='detail-label'>Child's Name:</span>
                        <span class='detail-value'>" . htmlspecialchars($vaccination_data['baby_name']) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Vaccine Type:</span>
                        <span class='detail-value'>" . htmlspecialchars($vaccination_data['vaccine_type']) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Schedule Date:</span>
                        <span class='detail-value'>" . date('F j, Y', strtotime($vaccination_data['schedule_datetime'])) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Schedule Time:</span>
                        <span class='detail-value'>" . date('g:i A', strtotime($vaccination_data['schedule_datetime'])) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Status:</span>
                        <span class='detail-value'><span style='color: #28a745; font-weight: 600;'>" . ucfirst($vaccination_data['status']) . "</span></span>
                    </div>
                </div>
                
                " . (!empty($vaccination_data['notes']) ? "
                <div class='notes-section'>
                    <h4>📝 Additional Notes:</h4>
                    <p style='margin: 5px 0; color: #856404;'>" . nl2br(htmlspecialchars($vaccination_data['notes'])) . "</p>
                </div>
                " : "") . "
                
                <div class='reminders-section'>
                    <h4>⚠️ Important Reminders:</h4>
                    <ul>
                        <li>Please arrive 10 minutes before the scheduled time</li>
                        <li>Bring your child's immunization record/card</li>
                        <li>Ensure your child is well-rested and has eaten</li>
                        <li>Inform the health worker of any allergies or medical conditions</li>
                        <li>If you need to reschedule, please contact us as soon as possible</li>
                    </ul>
                </div>
                
                <p class='message-text'>
                    You can view all your appointments and vaccination schedules through your dashboard.
                </p>
                
                <div style='text-align: center;'>
                    <a href='$dashboard_url' class='action-btn'>📅 View My Dashboard</a>
                </div>
                
                <p class='message-text'>
                    If you have any questions or concerns, please don't hesitate to contact us.
                </p>
            </div>
            
            <div class='footer'>
                <p>
                    <strong>San Benito Health Center</strong><br>
                    This is an automated notification. Please do not reply to this email.<br>
                    For inquiries, contact us at: <a href='mailto:" . REPLY_TO_EMAIL . "'>" . REPLY_TO_EMAIL . "</a>
                </p>
            </div>
        </div>
    </body>
    </html>";
}


/**
 * Send appointment cancellation notification to workers/admins
 * When a resident cancels their appointment
 */
function sendAppointmentCancellationNotificationToWorkers($conn, $appointment_data) {
    global $email_config;
    
    // Get all approved workers and admins with email addresses
    $workers_query = "SELECT email, fullname FROM users 
                      WHERE role IN ('worker', 'admin') 
                      AND status = 'approved' 
                      AND email IS NOT NULL 
                      AND email != ''";
    $workers_result = mysqli_query($conn, $workers_query);
    
    if (!$workers_result || mysqli_num_rows($workers_result) === 0) {
        error_log("No workers/admins found to notify about appointment cancellation");
        return false;
    }
    
    $success_count = 0;
    $total_count = 0;
    
    // Send email to each worker/admin
    while ($worker = mysqli_fetch_assoc($workers_result)) {
        $total_count++;
        $worker_email = $worker['email'];
        $worker_name = $worker['fullname'];
        
        // Validate email
        if (!filter_var($worker_email, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid worker email: " . $worker_email);
            continue;
        }
        
        $subject = "Appointment Cancelled by Resident - San Benito Health Center";
        
        // Check email mode
        if ($email_config['mode'] === 'simulate') {
            // Simulate email for development
            $message = createAppointmentCancellationNotificationText($appointment_data, $worker_name);
            if (simulateEmailSending($worker_email, $subject, $message)) {
                $success_count++;
            }
        } else if ($email_config['mode'] === 'smtp' && isEmailConfigured()) {
            // Send real email using PHPMailer
            if (sendAppointmentCancellationNotificationEmail($worker_email, $worker_name, $appointment_data)) {
                $success_count++;
            }
        } else {
            // Fallback to simulation
            $message = createAppointmentCancellationNotificationText($appointment_data, $worker_name);
            if (simulateEmailSending($worker_email, $subject, $message)) {
                $success_count++;
            }
        }
    }
    
    error_log("Appointment cancellation notification sent to $success_count out of $total_count workers/admins");
    return $success_count > 0;
}

/**
 * Send appointment cancellation notification email using PHPMailer
 */
function sendAppointmentCancellationNotificationEmail($worker_email, $worker_name, $appointment_data) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Timeout = 60;

        $mail->Host = SMTP_HOST;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // SSL/TLS options
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($worker_email, $worker_name);
        $mail->addReplyTo(REPLY_TO_EMAIL, FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Appointment Cancelled by Resident - San Benito Health Center";
        
        // Create HTML email content
        $mail->Body = createAppointmentCancellationNotificationHTML($appointment_data, $worker_name);
        
        // Create plain text version
        $mail->AltBody = createAppointmentCancellationNotificationText($appointment_data, $worker_name);
        
        // Send email
        $result = $mail->send();
        
        if ($result) {
            error_log("✅ Appointment cancellation notification sent to worker: $worker_email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("❌ Failed to send appointment cancellation notification to $worker_email: " . $e->getMessage());
        return false;
    }
}

/**
 * Create plain text appointment cancellation notification
 */
function createAppointmentCancellationNotificationText($appointment_data, $worker_name) {
    $dashboard_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/appointments.php";
    
    $message = "APPOINTMENT CANCELLED BY RESIDENT - San Benito Health Center\n\n";
    $message .= "Dear " . $worker_name . ",\n\n";
    $message .= "A resident has cancelled their appointment.\n\n";
    $message .= "APPOINTMENT DETAILS:\n";
    $message .= "-------------------\n";
    $message .= "Patient Name: " . $appointment_data['patient_name'] . "\n";
    $message .= "Appointment Type: " . $appointment_data['appointment_type'] . "\n";
    $message .= "Scheduled Date: " . date('F j, Y', strtotime($appointment_data['preferred_datetime'])) . "\n";
    $message .= "Scheduled Time: " . date('g:i A', strtotime($appointment_data['preferred_datetime'])) . "\n";
    $message .= "Cancelled By: " . $appointment_data['cancelled_by'] . "\n";
    
    // Reason removed - residents can cancel without providing reason
    
    if (!empty($appointment_data['notes'])) {
        $message .= "\nOriginal Notes:\n" . $appointment_data['notes'] . "\n";
    }
    
    $message .= "\n-------------------\n\n";
    $message .= "This appointment has been removed from the active appointments list.\n\n";
    $message .= "View appointments: " . $dashboard_url . "\n\n";
    $message .= "---\n";
    $message .= "This is an automated notification from San Benito Health Center.\n";
    $message .= "Please do not reply to this email.\n";
    
    return $message;
}

/**
 * Create HTML appointment cancellation notification
 */
function createAppointmentCancellationNotificationHTML($appointment_data, $worker_name) {
    $dashboard_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/appointments.php";
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Appointment Cancelled</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4; 
            }
            .email-container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 10px; 
                overflow: hidden; 
                box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #dc3545, #c82333); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 26px; 
                font-weight: 600; 
            }
            .content { 
                padding: 40px 30px; 
                background: white; 
            }
            .alert-badge { 
                background: #dc3545; 
                color: white; 
                padding: 10px 20px; 
                border-radius: 25px; 
                display: inline-block; 
                margin: 15px 0; 
                font-weight: 600; 
                font-size: 14px; 
            }
            .appointment-details { 
                background: #f8f9fa; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0; 
                border-left: 4px solid #dc3545; 
            }
            .appointment-details h3 { 
                margin-top: 0; 
                color: #2c3e50; 
            }
            .detail-item { 
                margin: 10px 0; 
                font-size: 15px; 
            }
            .detail-label { 
                font-weight: 600; 
                color: #555; 
                display: inline-block;
                min-width: 140px;
            }
            .detail-value {
                color: #333;
            }
            .reason-section {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 15px 0;
                border-radius: 5px;
            }
            .reason-section h4 {
                margin-top: 0;
                color: #856404;
            }
            .action-btn { 
                display: inline-block; 
                background: #007bff; 
                color: white; 
                padding: 15px 30px; 
                text-decoration: none; 
                border-radius: 8px; 
                margin: 20px 0; 
                font-weight: 600; 
                transition: background 0.3s; 
            }
            .action-btn:hover { 
                background: #0056b3; 
                color: white; 
            }
            .footer { 
                background: #f8f9fa; 
                padding: 20px; 
                text-align: center; 
                color: #666; 
                font-size: 14px; 
                border-top: 1px solid #eee; 
            }
            .message-text { 
                font-size: 16px; 
                line-height: 1.8; 
                color: #555; 
            }
            .highlight { 
                color: #dc3545; 
                font-weight: 600; 
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <h1>❌ Appointment Cancelled</h1>
            </div>
            
            <div class='content'>
                <p class='message-text'>Dear <strong>" . htmlspecialchars($worker_name) . "</strong>,</p>
                
                <div class='alert-badge'>⚠️ Cancellation Notice</div>
                
                <p class='message-text'>
                    A resident has <span class='highlight'>cancelled</span> their appointment.
                </p>
                
                <div class='appointment-details'>
                    <h3>📋 Appointment Details</h3>
                    <div class='detail-item'>
                        <span class='detail-label'>Patient Name:</span>
                        <span class='detail-value'>" . htmlspecialchars($appointment_data['patient_name']) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Appointment Type:</span>
                        <span class='detail-value'>" . htmlspecialchars($appointment_data['appointment_type']) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Scheduled Date:</span>
                        <span class='detail-value'>" . date('F j, Y', strtotime($appointment_data['preferred_datetime'])) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Scheduled Time:</span>
                        <span class='detail-value'>" . date('g:i A', strtotime($appointment_data['preferred_datetime'])) . "</span>
                    </div>
                    <div class='detail-item'>
                        <span class='detail-label'>Cancelled By:</span>
                        <span class='detail-value'>" . htmlspecialchars($appointment_data['cancelled_by']) . "</span>
                    </div>
                </div>
                
                <p class='message-text'>
                    This appointment has been removed from the active appointments list and archived.
                </p>
                
                <div style='text-align: center;'>
                    <a href='$dashboard_url' class='action-btn'>📅 View Appointments</a>
                </div>
            </div>
            
            <div class='footer'>
                <p>
                    <strong>San Benito Health Center</strong><br>
                    This is an automated notification. Please do not reply to this email.<br>
                    For inquiries, contact us at: <a href='mailto:" . REPLY_TO_EMAIL . "'>" . REPLY_TO_EMAIL . "</a>
                </p>
            </div>
        </div>
    </body>
    </html>";
}
