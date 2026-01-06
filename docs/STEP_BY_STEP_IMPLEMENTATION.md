# Step-by-Step Implementation Guide
## Google Calendar Integration for Barangay Health Center System

---

## 🎯 Overview

This guide will walk you through implementing Google Calendar integration in **under 3 hours**. Follow each step carefully.

**Total Time**: 2-3 hours  
**Difficulty**: Intermediate  
**Prerequisites**: Basic PHP, MySQL, JavaScript knowledge

---

## 📋 Pre-Implementation Checklist

Before starting, ensure you have:
- [ ] Access to Google Cloud Console
- [ ] Admin access to your web server
- [ ] MySQL database access
- [ ] Text editor or IDE
- [ ] Web browser for testing
- [ ] PHP 7.4+ with curl, json, mysqli extensions

---

## Step 1: Google Cloud Setup (15 minutes)

### 1.1 Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click "Select a project" → "New Project"
3. Enter project name: **"Barangay Health Center"**
4. Click "Create"
5. Wait for project creation (30 seconds)

### 1.2 Enable Google Calendar API

1. In the left sidebar, go to **"APIs & Services"** → **"Library"**
2. Search for **"Google Calendar API"**
3. Click on it
4. Click **"Enable"**
5. Wait for activation (10 seconds)

### 1.3 Configure OAuth Consent Screen

1. Go to **"APIs & Services"** → **"OAuth consent screen"**
2. Select **"External"** user type
3. Click **"Create"**
4. Fill in the form:
   - **App name**: Barangay Health Center System
   - **User support email**: Your email
   - **Developer contact**: Your email
5. Click **"Save and Continue"**
6. On "Scopes" page, click **"Add or Remove Scopes"**
7. Search and select:
   - `https://www.googleapis.com/auth/calendar`
   - `https://www.googleapis.com/auth/calendar.events`
8. Click **"Update"** → **"Save and Continue"**
9. On "Test users" page, click **"Save and Continue"**
10. Review and click **"Back to Dashboard"**

### 1.4 Create OAuth Credentials

1. Go to **"APIs & Services"** → **"Credentials"**
2. Click **"Create Credentials"** → **"OAuth client ID"**
3. Select **"Web application"**
4. Enter name: **"Health Center Calendar Integration"**
5. Under **"Authorized redirect URIs"**, click **"Add URI"**
6. Enter your callback URL:
   ```
   http://localhost/google_calendar_callback.php
   ```
   (Replace `localhost` with your domain in production)
7. Click **"Create"**
8. **IMPORTANT**: Copy the **Client ID** and **Client Secret**
9. Click **"OK"**

✅ **Checkpoint**: You should now have Client ID and Client Secret

---

## Step 2: Database Setup (5 minutes)

### 2.1 Run SQL Script

1. Open your MySQL client (phpMyAdmin, MySQL Workbench, or command line)
2. Select your database
3. Run the SQL script:

```bash
# Command line method:
mysql -u your_username -p your_database < sql/google_calendar_tables.sql

# Or copy-paste the SQL from the file into phpMyAdmin
```

### 2.2 Verify Tables Created

Run this query to verify:
```sql
SHOW TABLES LIKE '%google%';
```

You should see:
- `user_google_tokens`
- `appointment_calendar_sync`

✅ **Checkpoint**: Database tables created successfully

---

## Step 3: Configuration (5 minutes)

### 3.1 Update Google Calendar Config

1. Open `config/google_calendar_config.php`
2. Replace the placeholder values:

```php
// Replace these lines:
define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID_HERE.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET_HERE');
define('GOOGLE_REDIRECT_URI', 'http://localhost/google_calendar_callback.php');

// With your actual values:
define('GOOGLE_CLIENT_ID', '123456789-abc.apps.googleusercontent.com'); // Your Client ID
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-your_secret_here'); // Your Client Secret
define('GOOGLE_REDIRECT_URI', 'http://yourdomain.com/google_calendar_callback.php'); // Your domain
```

3. Save the file

### 3.2 Verify PHP Extensions

Run this command to check:
```bash
php -m | grep -E 'curl|json|mysqli'
```

You should see all three extensions listed.

✅ **Checkpoint**: Configuration complete

---

## Step 4: Add Calendar Widget to Dashboards (30 minutes)

### 4.1 Resident Dashboard

1. Open `resident_dashboard.php`
2. Find the "Dashboard Widgets Section" (around line 200)
3. Add the calendar widget **before** the Daily Health Tip widget:

```php
<!-- Dashboard Widgets Section -->
<section class="dashboard-widgets mb-5">
    <div class="row g-4">
        <!-- Google Calendar Widget -->
        <?php include 'includes/google_calendar_widget.php'; ?>
        
        <!-- Daily Health Tip Widget -->
        <div class="col-lg-6">
            <!-- existing health tip widget code -->
        </div>
    </div>
</section>
```

4. Save the file

### 4.2 Worker Dashboard

1. Open `worker_dashboard.php`
2. Find the "Dashboard Widgets Section"
3. Add the same code as above
4. Save the file

### 4.3 Admin Dashboard

1. Open `admin_dashboard.php`
2. Find the "Dashboard Widgets Section"
3. Add the same code as above
4. Save the file

✅ **Checkpoint**: Widget added to all dashboards

---

## Step 5: Test OAuth Connection (10 minutes)

### 5.1 Test Connection Flow

1. Open your browser
2. Go to your resident dashboard: `http://yourdomain.com/resident_dashboard.php`
3. Log in as a resident user
4. You should see the Google Calendar widget
5. Click **"Connect Google Calendar"** button
6. You should be redirected to Google
7. Select your Google account
8. Click **"Allow"** to grant permissions
9. You should be redirected back to your dashboard
10. You should see a success message

### 5.2 Verify Database

Check if token was stored:
```sql
SELECT user_id, created_at FROM user_google_tokens;
```

You should see a record for your user.

### 5.3 Troubleshooting

**If redirect fails:**
- Check redirect URI in Google Console matches exactly
- Check for typos in config file
- Check PHP error logs

**If "Access denied" error:**
- Verify OAuth consent screen is configured
- Check scopes are added correctly

✅ **Checkpoint**: OAuth connection working

---

## Step 6: Implement Auto-Sync (45 minutes)

### 6.1 Resident Dashboard - Auto-Sync on Create

1. Open `resident_dashboard.php`
2. Find the appointment creation code (around line 100)
3. Add auto-sync after successful insert:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_appointment'])) {
    // ... existing validation code ...
    
    $insert = "INSERT INTO appointments (user_id, fullname, appointment_type, preferred_datetime, notes) 
               VALUES ($user_id, '$fullname_escaped', '$appointment_type', '$preferred_datetime', '$notes')";
    
    if (mysqli_query($conn, $insert)) {
        $appointmentId = mysqli_insert_id($conn);
        
        // *** ADD THIS: Auto-sync to Google Calendar ***
        require_once 'config/google_calendar_functions.php';
        syncAppointmentToCalendar($conn, $appointmentId, $user_id);
        
        header('Location: resident_dashboard.php?success=1');
        exit;
    }
}
```

4. Save the file

### 6.2 Worker Dashboard - Sync on Confirm

1. Open `worker_dashboard.php`
2. Find the appointment action handling code (around line 50)
3. Update the confirm action:

```php
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    
    // Get appointment user_id
    $appointmentQuery = "SELECT user_id FROM appointments WHERE id = $id";
    $appointmentResult = mysqli_query($conn, $appointmentQuery);
    $appointment = mysqli_fetch_assoc($appointmentResult);
    $appointmentUserId = $appointment['user_id'];
    
    if ($action === 'confirm') {
        $update = "UPDATE appointments SET status = 'confirmed' WHERE id = $id";
        
        if (mysqli_query($conn, $update)) {
            // *** ADD THIS: Sync status change ***
            require_once 'config/google_calendar_functions.php';
            syncAppointmentToCalendar($conn, $id, $appointmentUserId);
        }
    }
    // ... rest of the code ...
}
```

4. Save the file

### 6.3 Worker Dashboard - Delete on Cancel/Complete

1. In the same file, update cancel and complete actions:

```php
elseif ($action === 'complete') {
    // *** ADD THIS: Delete from calendar ***
    require_once 'config/google_calendar_functions.php';
    deleteAppointmentFromCalendar($conn, $id, $appointmentUserId);
    
    // Then archive
    $archive_query = "INSERT INTO appointments_archive (...) SELECT ... FROM appointments WHERE id = $id";
    $delete_query = "DELETE FROM appointments WHERE id = $id";
    
    mysqli_query($conn, $archive_query);
    mysqli_query($conn, $delete_query);
}

elseif ($action === 'cancel') {
    // *** ADD THIS: Delete from calendar ***
    require_once 'config/google_calendar_functions.php';
    deleteAppointmentFromCalendar($conn, $id, $appointmentUserId);
    
    // Then update status
    $update = "UPDATE appointments SET status = 'cancelled' WHERE id = $id";
    mysqli_query($conn, $update);
}
```

2. Save the file

✅ **Checkpoint**: Auto-sync implemented

---

## Step 7: Add Manual Sync Buttons (30 minutes)

### 7.1 Add Sync Button to Appointment Table

1. Open `resident_dashboard.php`
2. Find the appointments table in the modal (around line 400)
3. Add a data attribute to each row:

```php
<tr class="appointment-row" data-appointment-id="<?php echo $appointment['id']; ?>">
    <!-- existing columns -->
    <td class="appointment-actions">
        <!-- Add this button -->
        <button class="calendar-sync-btn" onclick="syncAppointment(<?php echo $appointment['id']; ?>)">
            <i class="fas fa-calendar-plus"></i> Add to Calendar
        </button>
    </td>
</tr>
```

4. Add the JavaScript function at the bottom of the file:

```javascript
<script>
async function syncAppointment(appointmentId) {
    const button = event.target.closest('.calendar-sync-btn');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Syncing...';
    
    try {
        const response = await fetch('api/sync_appointment_calendar.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({appointment_id: appointmentId})
        });
        
        const data = await response.json();
        
        if (data.success) {
            button.innerHTML = '<i class="fas fa-check"></i> Synced';
            button.classList.add('synced');
            
            // Reload calendar widget
            if (window.calendarWidget) {
                window.calendarWidget.loadCalendarEvents();
            }
        } else if (data.needs_auth) {
            if (confirm('Google Calendar not connected. Connect now?')) {
                window.location.href = 'api/google_calendar_auth.php';
            } else {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-calendar-plus"></i> Add to Calendar';
            }
        } else {
            alert('Failed to sync: ' + data.message);
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-calendar-plus"></i> Add to Calendar';
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to sync appointment');
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-calendar-plus"></i> Add to Calendar';
    }
}
</script>
```

5. Save the file

✅ **Checkpoint**: Manual sync buttons added

---

## Step 8: Testing (1 hour)

### 8.1 Test OAuth Flow

- [ ] Click "Connect Google Calendar"
- [ ] Verify redirect to Google
- [ ] Grant permissions
- [ ] Verify redirect back
- [ ] Check success message
- [ ] Verify token in database

### 8.2 Test Auto-Sync on Create

- [ ] Create a new appointment as resident
- [ ] Check Google Calendar for event
- [ ] Verify event details match
- [ ] Check database sync record

### 8.3 Test Manual Sync

- [ ] Click "Add to Calendar" button
- [ ] Verify button changes to "Synced"
- [ ] Check Google Calendar for event
- [ ] Verify widget updates

### 8.4 Test Update Sync

- [ ] Worker confirms appointment
- [ ] Check Google Calendar event updated
- [ ] Verify status change reflected

### 8.5 Test Delete Sync

- [ ] Worker cancels appointment
- [ ] Verify event removed from Google Calendar
- [ ] Check database sync record removed

### 8.6 Test Widget Display

- [ ] Verify widget shows events
- [ ] Check event details display correctly
- [ ] Test on mobile device
- [ ] Verify responsive design

### 8.7 Test Error Handling

- [ ] Disconnect internet, try to sync
- [ ] Verify error message displays
- [ ] Reconnect, verify retry works

✅ **Checkpoint**: All tests passed

---

## Step 9: Production Deployment (30 minutes)

### 9.1 Update OAuth Redirect URI

1. Go to Google Cloud Console
2. Update redirect URI to production domain:
   ```
   https://yourdomain.com/google_calendar_callback.php
   ```
3. Update `config/google_calendar_config.php` with production URI

### 9.2 Enable HTTPS

Ensure your site uses HTTPS (required for OAuth in production)

### 9.3 Security Checklist

- [ ] HTTPS enabled
- [ ] Config file not in public directory
- [ ] Database credentials secure
- [ ] Error logging enabled
- [ ] Rate limiting considered

### 9.4 Performance Optimization

- [ ] Add database indexes (already in SQL script)
- [ ] Enable PHP opcache
- [ ] Consider Redis for token caching

✅ **Checkpoint**: Production ready

---

## Step 10: User Training (15 minutes)

### 10.1 Create User Guide

Create a simple guide for users:

**For Residents:**
1. Click "Connect Google Calendar" on dashboard
2. Sign in with Google account
3. Click "Allow" to grant permissions
4. Your appointments will now sync automatically!

**For Workers:**
1. Connect your Google Calendar (same as residents)
2. When you confirm/cancel appointments, they sync automatically
3. View daily schedule in calendar widget

### 10.2 Announce Feature

- Send email to all users
- Add announcement on dashboard
- Provide support contact

✅ **Checkpoint**: Users informed

---

## 🎉 Completion Checklist

- [ ] Google Cloud project configured
- [ ] Database tables created
- [ ] Configuration updated
- [ ] Widget added to all dashboards
- [ ] OAuth connection tested
- [ ] Auto-sync implemented
- [ ] Manual sync buttons added
- [ ] All tests passed
- [ ] Production deployed
- [ ] Users trained

---

## 📞 Support & Troubleshooting

### Common Issues

**Issue**: "Redirect URI mismatch"
**Solution**: Ensure URI in Google Console matches exactly (including http/https)

**Issue**: "Token expired"
**Solution**: Token refresh is automatic. If it fails, user needs to reconnect.

**Issue**: "Failed to create event"
**Solution**: Check error logs, verify Calendar API is enabled

**Issue**: Widget not loading
**Solution**: Check browser console for JavaScript errors

### Getting Help

1. Check error logs: `/var/log/apache2/error.log`
2. Check browser console for JavaScript errors
3. Review documentation files
4. Test with Google OAuth Playground

---

## 📚 Next Steps

After successful implementation:

1. **Monitor Usage**: Track how many users connect
2. **Gather Feedback**: Ask users about their experience
3. **Optimize**: Improve based on feedback
4. **Enhance**: Consider adding more features:
   - Batch sync for existing appointments
   - Calendar sync statistics
   - User preferences for sync settings
   - Email notifications for synced events

---

## ✅ Success!

Congratulations! You've successfully integrated Google Calendar into your Barangay Health Center System. 

Your users can now:
- ✅ Connect their Google Calendar
- ✅ Automatically sync appointments
- ✅ View upcoming events in the widget
- ✅ Manage their health appointments easily

**Total Implementation Time**: 2-3 hours  
**Maintenance Required**: Minimal (automatic token refresh)  
**User Satisfaction**: High (convenient calendar integration)

---

**Implementation Date**: _______________  
**Implemented By**: _______________  
**Status**: ☐ Complete ☐ In Progress  
**Notes**: _________________________________
