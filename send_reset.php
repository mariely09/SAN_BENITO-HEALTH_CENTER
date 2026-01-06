<?php
session_start();

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

require ('vendor/autoload.php'); 
require_once('config/database.php');
require_once('config/email.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function send_password_reset_otp($get_name, $get_email, $otp)
{
    try {
        $mail = new PHPMailer(true);
        
        // Disable debug output to prevent header issues
        $mail->SMTPDebug = 0;
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

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($get_email);

        $mail->isHTML(true);
        $mail->Subject = "Password Reset OTP - San Benito Health Center";

        $email_template = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset OTP</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='text-align: center; margin-bottom: 30px; background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; padding: 20px; border-radius: 8px;'>
                    <h1 style='margin: 0; font-size: 24px;'>San Benito Health Center</h1>
                    <p style='margin: 5px 0 0 0; opacity: 0.9;'>Password Reset Request</p>
                </div>
                
                <h2 style='color: #2c3e50;'>Hello " . htmlspecialchars($get_name) . ",</h2>
                
                <p>You have requested to reset your password for your San Benito Health Center account.</p>
                
                <p>Please use the following One-Time Password (OTP) to reset your password:</p>
                
                <div style='text-align: center; margin: 30px 0; background: white; border: 2px solid #27ae60; padding: 20px; border-radius: 8px;'>
                    <p style='margin: 0 0 10px 0; font-size: 14px; color: #666;'>Your OTP Code:</p>
                    <div style='font-size: 32px; font-weight: bold; color: #27ae60; letter-spacing: 8px; margin: 10px 0;'>" . $otp . "</div>
                </div>
                
                <div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                    <p style='margin: 0; font-weight: bold;'>Important:</p>
                    <ul style='margin: 10px 0 0 0; padding-left: 20px;'>
                        <li>This OTP will expire in <strong>1 hour</strong></li>
                        <li>Do not share this code with anyone</li>
                        <li>If you didn't request this, please ignore this email</li>
                    </ul>
                </div>
                
                <p>To reset your password, go to the password reset page and enter this OTP along with your new password.</p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
                
                <p style='font-size: 12px; color: #666; text-align: center;'>
                    This email was sent from San Benito Health Center<br>
                    Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>
        ";

        $mail->Body = $email_template;
        
        // Set plain text version
        $mail->AltBody = "Hello $get_name,\n\nYou have requested to reset your password.\n\nYour OTP Code: $otp\n\nThis OTP will expire in 10 minutes.\n\nIf you did not request this, please ignore this email.\n\nThank you!\nSan Benito Health Center";
        
        $result = $mail->send();
        
        if($result) {
            error_log("Password reset OTP sent successfully to: $get_email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("PHPMailer SMTP failed for $get_email: " . $e->getMessage());
        
        // Try fallback with PHP's built-in mail() function
        return send_password_reset_otp_fallback($get_name, $get_email, $otp);
    }
}

// Fallback function using PHP's built-in mail()
function send_password_reset_otp_fallback($get_name, $get_email, $otp) {
    try {
        $subject = "Password Reset OTP - San Benito Health Center";
        
        $message = "Hello " . htmlspecialchars($get_name) . ",\n\n";
        $message .= "You have requested to reset your password for your San Benito Health Center account.\n\n";
        $message .= "Your OTP Code: $otp\n\n";
        $message .= "This OTP will expire in 1 hour for security reasons.\n\n";
        $message .= "To reset your password, go to the password reset page and enter this OTP along with your new password.\n\n";
        $message .= "If you did not request this password reset, please ignore this email.\n\n";
        $message .= "Thank you!\n";
        $message .= "San Benito Health Center";

        $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $result = mail($get_email, $subject, $message, $headers);
        
        if($result) {
            error_log("Fallback OTP email sent successfully to: $get_email");
        } else {
            error_log("Fallback OTP email also failed for: $get_email");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Fallback OTP email failed for $get_email: " . $e->getMessage());
        return false;
    }
}

if(isset($_POST['reset-button']))
{
    // Check database connection
    if(!$conn) {
        $_SESSION['error_message'] = "Database connection failed. Please try again later.";
        header("Location: forgot_password.php");
        exit(0);
    }

    // Validate and sanitize email input
    $email = trim($_POST['email']);
    
    if(empty($email)) {
        $_SESSION['error_message'] = "Please enter your email address.";
        header("Location: forgot_password.php");
        exit(0);
    }
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Please enter a valid email address.";
        header("Location: forgot_password.php");
        exit(0);
    }

    $email = mysqli_real_escape_string($conn, $email);
    
    // Generate a 6-digit OTP
    $otp = sprintf("%06d", mt_rand(100000, 999999));

    // Check if email exists in database
    $check_email = "SELECT id, username, email, fullname FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($check_email);
    
    if(!$stmt) {
        $_SESSION['error_message'] = "System error. Please try again later.";
        header("Location: forgot_password.php");
        exit(0);
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0)
    {
        $row = $result->fetch_assoc();
        $get_name = $row['fullname'] ? $row['fullname'] : $row['username'];
        $get_email = $row['email'];

        // Set OTP expiry to 1 hour from now (generous time)
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update user with reset OTP
        $update_otp = "UPDATE users SET reset_otp = ?, reset_otp_expiry = ? WHERE email = ? LIMIT 1";
        $update_stmt = $conn->prepare($update_otp);
        
        if(!$update_stmt) {
            $_SESSION['error_message'] = "System error. Please try again later.";
            header("Location: forgot_password.php");
            exit(0);
        }
        
        $update_stmt->bind_param('sss', $otp, $expiry, $get_email);
        $update_token_run = $update_stmt->execute();
        
        // Log OTP generation for debugging
        if($update_token_run && isLocalDevelopment()) {
            error_log("Reset OTP generated for $get_email: $otp (expires: $expiry)");
            error_log("Current server time: " . date('Y-m-d H:i:s'));
            error_log("Server timezone: " . date_default_timezone_get());
        }

        if($update_token_run) {
            // Try to send OTP email
            $email_sent = send_password_reset_otp($get_name, $get_email, $otp);
            
            // Log the attempt for debugging
            error_log("Password reset attempt for email: $get_email, OTP generated: $otp, Email sent: " . ($email_sent ? 'Yes' : 'No'));
            
            if($email_sent) {
                $_SESSION['success_message'] = "A 6-digit OTP has been sent to your email address. Please check your inbox and spam folder.";
                $_SESSION['reset_email'] = $get_email; // Store email for OTP verification
                header("Location: verify_otp.php");
                exit(0);
            } else {
                // Clear the token if email failed to send
                $clear_token = "UPDATE users SET reset_otp = NULL, reset_otp_expiry = NULL WHERE email = ? LIMIT 1";
                $clear_stmt = $conn->prepare($clear_token);
                if($clear_stmt) {
                    $clear_stmt->bind_param('s', $get_email);
                    $clear_stmt->execute();
                }
                
                if(isLocalDevelopment()) {
                    $_SESSION['error_message'] = "Failed to send email. Please check your email configuration. You can test email functionality at <a href='test_email.php'>test_email.php</a>";
                } else {
                    $_SESSION['error_message'] = "Failed to send email. Please check your email address and try again. If the problem persists, contact support.";
                }
                header("Location: forgot_password.php");
                exit(0);
            }
        } else {
            $_SESSION['error_message'] = "System error. Please try again later.";
            header("Location: forgot_password.php");
            exit(0);
        }
    }
    else
    {
        // For security reasons, don't reveal if email exists or not
        $_SESSION['success_message'] = "If an account with this email exists, a password reset link has been sent.";
        $_SESSION['keep_message'] = true;
        header("Location: forgot_password.php");
        exit(0);
    }
} else {
    // Redirect if accessed directly
    header("Location: forgot_password.php");
    exit(0);
}
?>