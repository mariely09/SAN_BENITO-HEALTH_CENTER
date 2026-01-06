# Email Configuration Setup Guide

## Overview
The San Benito Health Center system uses email for various notifications including appointment confirmations, password resets, and system alerts. This guide covers how to configure email settings for both development and production environments.

## Configuration Files
- **Primary Config**: `config/email_config.php`
- **Email Functions**: `includes/email_functions.php`

## Email Modes

### 1. Simulate Mode (Development)
```php
define('EMAIL_MODE', 'simulate');
```
- **Purpose**: For development and testing
- **Behavior**: Emails are saved as HTML files instead of being sent
- **Location**: Saved in `emails/` directory
- **Benefits**: No SMTP setup required, safe for testing

### 2. SMTP Mode (Production)
```php
define('EMAIL_MODE', 'smtp');
```
- **Purpose**: For production environment
- **Behavior**: Emails are sent via SMTP server
- **Requirements**: Valid SMTP credentials

## SMTP Configuration

### Gmail SMTP Setup
1. **Enable 2-Factor Authentication** on your Gmail account
2. **Generate App Password**:
   - Go to Google Account settings
   - Security → 2-Step Verification → App passwords
   - Generate password for "Mail"
3. **Update Configuration**:
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password'); // 16-character app password
define('SMTP_SECURE', 'tls');
```

### Other Email Providers

#### Yahoo Mail
```php
define('SMTP_HOST', 'smtp.mail.yahoo.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
```

#### Outlook/Hotmail
```php
define('SMTP_HOST', 'smtp-mail.outlook.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
```

## Configuration Parameters

| Parameter | Description | Example |
|-----------|-------------|---------|
| `EMAIL_MODE` | Email sending mode | `'simulate'` or `'smtp'` |
| `SMTP_HOST` | SMTP server hostname | `'smtp.gmail.com'` |
| `SMTP_PORT` | SMTP server port | `587` (TLS) or `465` (SSL) |
| `SMTP_USERNAME` | Email account username | `'your-email@gmail.com'` |
| `SMTP_PASSWORD` | Email account password | App password for Gmail |
| `SMTP_SECURE` | Encryption method | `'tls'` or `'ssl'` |
| `FROM_EMAIL` | Sender email address | `'noreply@healthcenter.com'` |
| `FROM_NAME` | Sender display name | `'San Benito Health Center'` |

## Testing Email Configuration

### 1. Check Configuration Status
```php
if (isEmailConfigured()) {
    echo "Email is properly configured";
} else {
    echo "Email configuration incomplete";
}
```

### 2. Send Test Email
Use the password reset or appointment booking features to test email functionality.

### 3. Check Simulate Mode Files
When in simulate mode, check the `emails/` directory for generated HTML files.

## Troubleshooting

### Common Issues

#### 1. Gmail Authentication Failed
- **Cause**: Using regular password instead of app password
- **Solution**: Generate and use Gmail app password

#### 2. Connection Timeout
- **Cause**: Firewall blocking SMTP ports
- **Solution**: Check firewall settings, try different ports

#### 3. SSL/TLS Errors
- **Cause**: Incorrect encryption settings
- **Solution**: Try switching between `'tls'` and `'ssl'`

#### 4. Emails Not Sending
- **Cause**: Various SMTP issues
- **Solution**: Check error logs, verify credentials

### Debug Steps
1. Enable error reporting in PHP
2. Check server error logs
3. Verify SMTP credentials
4. Test with different email providers
5. Use simulate mode for debugging

## Security Best Practices

1. **Never commit passwords** to version control
2. **Use environment variables** for sensitive data
3. **Enable 2FA** on email accounts
4. **Use app passwords** instead of regular passwords
5. **Regularly rotate** email credentials
6. **Monitor email logs** for suspicious activity

## Environment Variables (Recommended)
For better security, use environment variables:

```php
define('SMTP_USERNAME', $_ENV['EMAIL_USERNAME'] ?? 'default@email.com');
define('SMTP_PASSWORD', $_ENV['EMAIL_PASSWORD'] ?? '');
```

Create `.env` file:
```
EMAIL_USERNAME=your-email@gmail.com
EMAIL_PASSWORD=your-app-password
```

## Production Deployment Checklist

- [ ] Change `EMAIL_MODE` to `'smtp'`
- [ ] Configure valid SMTP credentials
- [ ] Test email sending functionality
- [ ] Set up proper from/reply-to addresses
- [ ] Configure error logging
- [ ] Test all email features (reset password, appointments)
- [ ] Monitor email delivery rates

## Support
For additional help with email configuration, contact the system administrator or refer to your email provider's SMTP documentation.