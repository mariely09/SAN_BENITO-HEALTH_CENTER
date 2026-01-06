<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Resident must be logged in
requireLogin();

// Only residents can access this dashboard
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'resident') {
    header('Location: index.php?error=unauthorized');
    exit;
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get babies associated with this user (exact match on parent/guardian name for security)
$fullname_escaped = mysqli_real_escape_string($conn, $fullname);
$babies_query = "SELECT id, full_name, date_of_birth FROM babies 
                 WHERE parent_guardian_name = '$fullname_escaped'
                 ORDER BY full_name ASC";
$babies_result = mysqli_query($conn, $babies_query);
$user_babies = [];
if ($babies_result) {
    while ($baby = mysqli_fetch_assoc($babies_result)) {
        $user_babies[] = $baby;
    }
}

// Handle appointment scheduling
$error = '';
$success = '';

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = 'Your appointment request has been submitted successfully. Status: pending.';
}

// Check for cancellation success
if (isset($_GET['cancel_success']) && $_GET['cancel_success'] == '1') {
    $success = 'Your appointment has been cancelled successfully. Workers have been notified.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_appointment'])) {
    // Sanitize inputs
    $appointment_type = isset($_POST['appointment_type']) && in_array($_POST['appointment_type'], ['Vaccination', 'Check-up']) ? $_POST['appointment_type'] : '';
    $preferred_date = isset($_POST['preferred_date']) ? sanitize($_POST['preferred_date']) : '';
    $preferred_time = isset($_POST['preferred_time']) ? sanitize($_POST['preferred_time']) : '';
    $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
    $selected_baby_id = isset($_POST['selected_baby']) ? (int)$_POST['selected_baby'] : 0;
    $vaccination_type = isset($_POST['vaccination_type']) ? sanitize($_POST['vaccination_type']) : '';

    if (empty($appointment_type) || empty($preferred_date) || empty($preferred_time)) {
        $error = 'Please fill in the required fields.';
    } elseif ($appointment_type === 'Vaccination' && empty($user_babies)) {
        $error = 'You must have a registered dependent (baby/child) to schedule a vaccination appointment.';
    } elseif ($appointment_type === 'Vaccination' && $selected_baby_id <= 0) {
        $error = 'Please select which baby needs vaccination.';
    } elseif ($appointment_type === 'Vaccination' && empty($vaccination_type)) {
        $error = 'Please select the vaccination type.';
    } else {
        // Combine date and time into DATETIME
        $preferred_datetime = date('Y-m-d H:i:s', strtotime($preferred_date . ' ' . $preferred_time));
        $user_id = $_SESSION['user_id'];
        $fullname_escaped = mysqli_real_escape_string($conn, $_SESSION['fullname']);
        
        // Add baby vaccination information to notes
        if ($appointment_type === 'Vaccination' && $selected_baby_id > 0) {
            // Find the selected baby's information
            $baby_info = null;
            foreach ($user_babies as $baby) {
                if ($baby['id'] == $selected_baby_id) {
                    $baby_info = $baby;
                    break;
                }
            }
            if ($baby_info) {
                $notes = "BABY VACCINATION - " . $baby_info['full_name'] . " (Age: " . getAge($baby_info['date_of_birth']) . ")\nVaccine Type: " . $vaccination_type . (!empty($notes) ? "\n\nAdditional Notes: " . $notes : "");
            }
        }

        $insert = "INSERT INTO appointments (user_id, fullname, appointment_type, preferred_datetime, notes) 
                   VALUES ($user_id, '$fullname_escaped', '$appointment_type', '$preferred_datetime', '$notes')";
        if (mysqli_query($conn, $insert)) {
            $appointmentId = mysqli_insert_id($conn);
            
            // Auto-sync to Google Calendar for all connected users
            require_once 'config/google_calendar_functions.php';
            
            // 1. Sync to resident's calendar (appointment owner)
            $tokenCheck = mysqli_query($conn, "SELECT id FROM user_google_tokens WHERE user_id = $user_id");
            if ($tokenCheck && mysqli_num_rows($tokenCheck) > 0) {
                syncAppointmentToCalendar($conn, $appointmentId, $user_id);
                error_log("Auto-synced new appointment $appointmentId to resident's calendar (user $user_id)");
            }
            
            // 2. Sync to all workers/admins who have Google Calendar connected
            $workersQuery = "SELECT DISTINCT u.id 
                            FROM users u 
                            INNER JOIN user_google_tokens t ON u.id = t.user_id 
                            WHERE u.role IN ('worker', 'admin') 
                            AND u.status = 'approved'";
            $workersResult = mysqli_query($conn, $workersQuery);
            
            if ($workersResult) {
                while ($worker = mysqli_fetch_assoc($workersResult)) {
                    $workerId = $worker['id'];
                    syncAppointmentToCalendar($conn, $appointmentId, $workerId);
                    error_log("Auto-synced new appointment $appointmentId to worker/admin calendar (user $workerId)");
                }
            }
            
            // 3. Send email notification to all workers/admins
            require_once 'config/email.php';
            $appointment_data = [
                'patient_name' => $_SESSION['fullname'],
                'appointment_type' => $appointment_type,
                'preferred_datetime' => $preferred_datetime,
                'notes' => $notes
            ];
            sendAppointmentNotificationToWorkers($conn, $appointment_data);
            error_log("Email notifications sent to workers/admins for appointment $appointmentId");
            
            // Redirect to prevent form resubmission and double display
            header('Location: resident_dashboard.php?success=1');
            exit;
        } else {
            $error = 'Failed to save appointment: ' . mysqli_error($conn);
        }
    }
}

// Get resident's active appointments (pending, approved, confirmed) from appointments table only
$appointments_query = "
    SELECT * FROM appointments 
    WHERE user_id = $user_id AND status IN ('pending', 'approved', 'confirmed') 
    ORDER BY preferred_datetime DESC 
    LIMIT 10";
$appointments_result = mysqli_query($conn, $appointments_query);

// Get recent completed appointments from archived_appointments table
// AND completed vaccinations for user's babies from archived_vaccinations table
$completed_appointments_query = "
    SELECT id, fullname as patient_name, appointment_type, archived_at, 'appointment' as record_type
    FROM archived_appointments 
    WHERE user_id = $user_id AND status = 'completed'
    UNION ALL
    SELECT v.id, b.full_name as patient_name, 'Vaccination' as appointment_type, v.administered_date as archived_at, 'vaccination' as record_type
    FROM archived_vaccinations v
    JOIN babies b ON v.baby_id = b.id
    WHERE b.parent_guardian_name = '$fullname_escaped' AND v.status = 'completed'
    ORDER BY archived_at DESC 
    LIMIT 10";
$completed_appointments_result = mysqli_query($conn, $completed_appointments_query);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome, <?php echo htmlspecialchars($fullname); ?></title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Navbar Styles -->
    <link rel="stylesheet" href="assets/css/navbar.css">
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/residents_dashboard.css?v=<?php echo time(); ?>">
    <!-- Modal Notifications Styles -->
    <link rel="stylesheet" href="assets/css/success-error_messages.css">
    <style>
         body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%) !important;
            min-height: 100vh !important;
        }
        
        /* Style for booked time slots */
        #preferred_time option:disabled {
            color: #dc3545 !important;
            background-color: #f8d7da !important;
            font-style: italic;
        }
        
        /* Loading indicator for time slots */
        .time-loading {
            position: relative;
        }
        
        .time-loading::after {
            content: '';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }
    </style>
</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Message Modal -->
    <div class="message-modal" id="messageModal">
        <div class="message-modal-content">
            <div class="message-modal-header">
                <button type="button" class="message-modal-close" onclick="hideModal()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="message-modal-icon" id="modalIcon">
                    <i class="fas fa-check" id="modalIconSymbol"></i>
                </div>
                <h3 class="message-modal-title" id="modalTitle">Success</h3>
            </div>
            <div class="message-modal-body">
                <p class="message-modal-message" id="modalMessage">Operation completed successfully!</p>
                <div class="message-modal-actions">
                    <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="hideModal()">
                        <i class="fas fa-check me-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="welcome-section mb-5">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">
                        <i class="fas fa-hand-wave me-2 text-warning"></i>
                        Welcome, <?php echo htmlspecialchars($fullname); ?>!
                    </h1>
                    <p class="welcome-subtitle">Manage your health appointments and stay up to date with your medical
                        care.</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="welcome-date">
                        <i class="fas fa-calendar-day me-2"></i>
                        <?php
                        date_default_timezone_set('Asia/Manila');
                        echo date('l, F j, Y');
                        ?>
                        <br>
                        <i class="fas fa-clock me-2"></i>
                        <?php echo date('g:i A'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Widgets Section -->
        <section class="dashboard-widgets mb-5">
            <div class="row g-4">
                <!-- Left Side: Google Calendar Widget -->
                <div class="col-lg-7">
                    <?php include 'includes/google_calendar_widget.php'; ?>
                </div>
                
                <!-- Right Side: Health Tip and Weather Stacked -->
                <div class="col-lg-5">
                    <div class="row g-4">
                        <!-- Daily Health Tip Widget -->
                        <div class="col-12">
                            <div class="widget-card health-tip-widget">
                                <div class="widget-header">
                                    <div class="widget-title-section">
                                        <span class="widget-icon">💡</span>
                                        <h3 class="widget-title">Daily Health Tip</h3>
                                    </div>
                                    <button class="widget-refresh-btn" id="refreshTipBtn" title="Get new tip">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <div class="widget-body">
                                    <div class="loading-state" id="loadingState">
                                        <div class="spinner"></div>
                                        <p>Loading tip…</p>
                                    </div>
                                    <div class="tip-content" id="tipContent" style="display: none;">
                                        <blockquote class="tip-quote" id="tipQuote">
                                            <!-- Quote text will be inserted here -->
                                        </blockquote>
                                        <p class="tip-author" id="tipAuthor">
                                            <!-- Author will be inserted here -->
                                        </p>
                                    </div>
                                    <div class="error-state" id="errorState" style="display: none;">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <p>Unable to load tip.</p>
                                        <button class="retry-btn" onclick="fetchHealthTip()">Retry</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Today's Weather Widget -->
                        <div class="col-12">
                            <div class="widget-card weather-widget">
                                <div class="widget-header">
                                    <div class="widget-title-section">
                                        <span class="widget-icon">🌦</span>
                                        <h3 class="widget-title">Today's Weather</h3>
                                    </div>
                                    <button class="widget-refresh-btn" id="refreshWeatherBtn" title="Refresh weather">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <div class="widget-body">
                                    <!-- Loading State -->
                                    <div class="weather-loading-state" id="weatherLoadingState">
                                        <div class="spinner"></div>
                                        <p>Loading weather…</p>
                                    </div>
                                    
                                    <!-- Weather Content -->
                                    <div class="weather-content" id="weatherContent" style="display: none;">
                                        <div class="weather-main-compact">
                                            <div class="weather-location-compact">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span id="weatherCity">Manila</span>
                                            </div>
                                            <div class="weather-display">
                                                <img id="weatherIcon" src="" alt="Weather icon" class="weather-icon-compact">
                                                <div class="weather-temp-compact">
                                                    <div class="temp-value" id="weatherTemp">--°C</div>
                                                    <div class="weather-condition-compact" id="weatherCondition">--</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="weather-details-compact">
                                            <div class="weather-detail-compact">
                                                <i class="fas fa-tint"></i>
                                                <div>
                                                    <span class="detail-label">Humidity</span>
                                                    <span class="detail-value" id="weatherHumidity">--%</span>
                                                </div>
                                            </div>
                                            <div class="weather-detail-compact">
                                                <i class="fas fa-wind"></i>
                                                <div>
                                                    <span class="detail-label">Wind</span>
                                                    <span class="detail-value" id="weatherWind">-- m/s</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Error State -->
                                    <div class="weather-error-state" id="weatherErrorState" style="display: none;">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <p>Unable to load weather.</p>
                                        <button class="retry-btn" onclick="fetchWeatherData()">Retry</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <section class="quick-actions">
            <h2 class="section-title">
                <i class="fas fa-bolt me-2"></i>Quick Actions
            </h2>
            <div class="row">
                <div class="col-md-6 <?php echo empty($user_babies) ? 'col-lg-4' : 'col-lg-3'; ?> mb-4">
                    <div class="card action-card schedule">
                        <div class="card-body">
                            <div class="action-icon">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <h5 class="card-title">Schedule Appointment</h5>
                            <p class="card-text">Book a health check-up or vaccination appointment with our medical
                                staff</p>
                            <button type="button" class="btn action-btn btn-schedule" data-bs-toggle="modal"
                                data-bs-target="#scheduleModal">
                                <i class="fas fa-plus me-2"></i>Schedule Now
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 <?php echo empty($user_babies) ? 'col-lg-4' : 'col-lg-3'; ?> mb-4">
                    <div class="card action-card profile">
                        <div class="card-body">
                            <div class="action-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <h5 class="card-title">Update Profile</h5>
                            <p class="card-text">Manage your personal information and keep your details up to date</p>
                            <button type="button" class="btn action-btn btn-profile" data-bs-toggle="modal"
                                data-bs-target="#profileModal">
                                <i class="fas fa-edit me-2"></i>Update Profile
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 <?php echo empty($user_babies) ? 'col-lg-4' : 'col-lg-3'; ?> mb-4">
                    <div class="card action-card appointments">
                        <div class="card-body">
                            <div class="action-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h5 class="card-title">My Appointments</h5>
                            <p class="card-text">View your appointment history and check current appointment status</p>
                            <button type="button" class="btn action-btn btn-appointments" data-bs-toggle="modal"
                                data-bs-target="#appointmentsModal">
                                <i class="fas fa-eye me-2"></i>View Appointments
                            </button>
                        </div>
                    </div>
                </div>
                <?php if (!empty($user_babies)): ?>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card action-card vaccinations">
                        <div class="card-body">
                            <div class="action-icon">
                                <i class="fas fa-syringe"></i>
                            </div>
                            <h5 class="card-title">Baby Vaccinations</h5>
                            <p class="card-text">View upcoming vaccination schedules for your babies</p>
                            <button type="button" class="btn action-btn btn-vaccinations" data-bs-toggle="modal"
                                data-bs-target="#vaccinationsModal">
                                <i class="fas fa-eye me-2"></i>View Schedules
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Schedule Appointment Modal -->
        <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="scheduleModalLabel">
                            <i class="fas fa-calendar-plus"></i> Schedule Health Appointment
                        </h5>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger"><?php echo $error; ?></div>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user me-1"></i>Full Name
                                    </label>
                                    <input type="text" class="form-control"
                                        value="<?php echo htmlspecialchars($_SESSION['fullname']); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="appointment_type" class="form-label">
                                        <i class="fas fa-stethoscope me-1"></i>Appointment Type <span class="text-danger">*</span>
                                    </label>
                                    <select name="appointment_type" id="appointment_type" class="form-select" required onchange="toggleBabyVaccination()">
                                        <option value="">Select type</option>
                                        <option value="Vaccination" <?php echo empty($user_babies) ? 'disabled' : ''; ?>>
                                            Vaccination <?php echo empty($user_babies) ? '(No registered dependent)' : ''; ?>
                                        </option>
                                        <option value="Check-up">Check-up</option>
                                    </select>
                                    <?php if (empty($user_babies)): ?>
                                        <div class="form-text text-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            You must have a registered dependent (baby/child) to schedule a vaccination appointment.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Baby Vaccination Options (shown only for Vaccination) -->
                                <div class="col-12 mb-3" id="baby_vaccination_section" style="display: none;">
                                    <?php if (!empty($user_babies)): ?>
                                        <!-- Existing Babies Selection -->
                                        <div class="mb-3">
                                            <label for="selected_baby" class="form-label">
                                                <i class="fas fa-child me-2"></i>Which baby needs vaccination? <span class="text-danger">*</span>
                                            </label>
                                            <select name="selected_baby" id="selected_baby" class="form-select" required>
                                                <option value="">-- Choose your baby --</option>
                                                <?php foreach ($user_babies as $baby): ?>
                                                    <option value="<?php echo $baby['id']; ?>">
                                                        <?php echo htmlspecialchars($baby['full_name']); ?> 
                                                        (<?php echo getAge($baby['date_of_birth']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">
                                                <i class="fas fa-shield-alt me-1 text-success"></i>
                                                You only see babies registered under your name for privacy protection.
                                            </div>
                                        </div>

                                        <!-- Vaccination Type Selection (shown after baby is selected) -->
                                        <div class="mb-3" id="vaccination_type_section" style="display: none;">
                                            <label for="vaccination_type" class="form-label">
                                                <i class="fas fa-vial me-2"></i>Vaccination Type <span class="text-danger">*</span>
                                            </label>
                                            <select name="vaccination_type" id="vaccination_type" class="form-select" required>
                                                <option value="">-- Select vaccination type --</option>
                                            </select>
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1 text-primary"></i>
                                                <span id="vaccine-age-info">Vaccines are filtered based on your baby's age</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="preferred_date" class="form-label">
                                        <i class="fas fa-calendar-alt me-1"></i>Preferred Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" name="preferred_date" id="preferred_date" class="form-control"
                                        required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="preferred_time" class="form-label">
                                        <i class="fas fa-clock me-1"></i>Preferred Time <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-clock text-primary"></i>
                                        </span>
                                        <select name="preferred_time" id="preferred_time" class="form-select" required>
                                            <option value="">Select time</option>
                                            <option value="08:00">8:00 AM</option>
                                            <option value="08:15">8:15 AM</option>
                                            <option value="08:30">8:30 AM</option>
                                            <option value="08:45">8:45 AM</option>
                                            <option value="09:00">9:00 AM</option>
                                            <option value="09:15">9:15 AM</option>
                                            <option value="09:30">9:30 AM</option>
                                            <option value="09:45">9:45 AM</option>
                                            <option value="10:00">10:00 AM</option>
                                            <option value="10:15">10:15 AM</option>
                                            <option value="10:30">10:30 AM</option>
                                            <option value="10:45">10:45 AM</option>
                                            <option value="11:00">11:00 AM</option>
                                            <option value="11:15">11:15 AM</option>
                                            <option value="11:30">11:30 AM</option>
                                            <option value="11:45">11:45 AM</option>
                                            <option value="12:00">12:00 PM</option>
                                            <option value="12:15">12:15 PM</option>
                                            <option value="12:30">12:30 PM</option>
                                            <option value="12:45">12:45 PM</option>
                                            <option value="13:00">1:00 PM</option>
                                            <option value="13:15">1:15 PM</option>
                                            <option value="13:30">1:30 PM</option>
                                            <option value="13:45">1:45 PM</option>
                                            <option value="14:00">2:00 PM</option>
                                            <option value="14:15">2:15 PM</option>
                                            <option value="14:30">2:30 PM</option>
                                            <option value="14:45">2:45 PM</option>
                                            <option value="15:00">3:00 PM</option>
                                            <option value="15:15">3:15 PM</option>
                                            <option value="15:30">3:30 PM</option>
                                            <option value="15:45">3:45 PM</option>
                                            <option value="16:00">4:00 PM</option>
                                            <option value="16:15">4:15 PM</option>
                                            <option value="16:30">4:30 PM</option>
                                            <option value="16:45">4:45 PM</option>
                                            <option value="17:00">5:00 PM</option>
                                        </select>
                                    </div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Available hours: 8:00 AM - 5:00 PM (15-minute intervals)
                                    </div>
                                    <div id="time-availability-info" class="form-text mt-1" style="display: none;">
                                        <i class="fas fa-calendar-times me-1 text-warning"></i>
                                        <span id="availability-message"></span>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <label for="notes" class="form-label">
                                        <i class="fas fa-sticky-note me-1"></i>Notes (optional)
                                    </label>
                                    <textarea name="notes" id="notes" class="form-control" rows="3"
                                        placeholder="Any additional information or special requests..."></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancel
                            </button>
                            <button type="submit" name="schedule_appointment" class="btn btn-primary" id="submitAppointmentBtn">
                                <i class="fas fa-calendar-check"></i> Submit Appointment Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Appointments Modal -->
        <div class="modal fade" id="appointmentsModal" tabindex="-1" aria-labelledby="appointmentsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="appointmentsModalLabel">
                            <i class="fas fa-calendar-check"></i> My Appointments
                        </h5>
                    </div>
                    <div class="modal-body">
                        <!-- Upcoming/Pending Appointments -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3"><i class="fas fa-calendar-alt"></i> Active Appointments</h6>
                            <?php
                            // Reset the result pointer to reuse the data
                            mysqli_data_seek($appointments_result, 0);
                            if (mysqli_num_rows($appointments_result) > 0): ?>
                                <div class="table-responsive shadow-sm rounded">
                                    <table class="table table-hover mb-0 appointments-table">
                                        <thead class="table-primary">
                                            <tr>
                                                <th class="border-0 py-3 px-4">
                                                    <i class="fas fa-medical-kit me-2"></i>Appointment Type
                                                </th>
                                                <th class="border-0 py-3 px-4">
                                                    <i class="fas fa-calendar-clock me-2"></i>Date & Time
                                                </th>
                                                <th class="border-0 py-3 px-4 text-center">
                                                    <i class="fas fa-info-circle me-2"></i>Status
                                                </th>
                                                <th class="border-0 py-3 px-4">
                                                    <i class="fas fa-sticky-note me-2"></i>Notes
                                                </th>
                                                <th class="border-0 py-3 px-4 text-center">
                                                    <i class="fas fa-cogs me-2"></i>Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($appointment = mysqli_fetch_assoc($appointments_result)): ?>
                                                <tr class="appointment-row">
                                                    <td class="py-3 px-4 align-middle">
                                                        <div class="d-flex align-items-center">
                                                            <div class="appointment-icon me-3">
                                                                <i
                                                                    class="fas fa-<?php echo $appointment['appointment_type'] == 'Vaccination' ? 'syringe text-primary' : 'stethoscope text-info'; ?>"></i>
                                                            </div>
                                                            <div>
                                                                <span
                                                                    class="fw-semibold text-dark"><?php echo htmlspecialchars($appointment['appointment_type']); ?></span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 px-4 align-middle">
                                                        <div class="appointment-datetime">
                                                            <div class="fw-semibold text-dark">
                                                                <?php echo date('M d, Y', strtotime($appointment['preferred_datetime'])); ?>
                                                            </div>
                                                            <small
                                                                class="text-muted"><?php echo date('g:i A', strtotime($appointment['preferred_datetime'])); ?></small>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 px-4 align-middle text-center">
                                                        <span class="badge fs-6 px-3 py-2 rounded-pill bg-<?php
                                                        if ($appointment['status'] == 'approved' || $appointment['status'] == 'confirmed') {
                                                            echo 'success';
                                                        } elseif ($appointment['status'] == 'rejected' || $appointment['status'] == 'cancelled') {
                                                            echo 'danger';
                                                        } else {
                                                            echo 'warning';
                                                        }
                                                        ?>">
                                                            <i class="fas fa-<?php
                                                            if ($appointment['status'] == 'approved' || $appointment['status'] == 'confirmed') {
                                                                echo 'check-circle';
                                                            } elseif ($appointment['status'] == 'rejected' || $appointment['status'] == 'cancelled') {
                                                                echo 'times-circle';
                                                            } else {
                                                                echo 'clock';
                                                            }
                                                            ?> me-1"></i>
                                                            <?php echo ucfirst($appointment['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-3 px-4 align-middle">
                                                        <div class="appointment-notes">
                                                            <?php if (!empty($appointment['notes'])): ?>
                                                                <span
                                                                    class="text-dark"><?php echo htmlspecialchars($appointment['notes']); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted fst-italic">No additional notes</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 px-4 align-middle text-center">
                                                        <?php if ($appointment['status'] == 'pending' || $appointment['status'] == 'confirmed'): ?>
                                                            <a href="api/cancel_appointment.php?id=<?php echo $appointment['id']; ?>" 
                                                               class="btn btn-sm btn-danger" 
                                                               onclick="return confirm('Are you sure you want to cancel this appointment?')"
                                                               title="Cancel Appointment">
                                                                <i class="fas fa-times me-1"></i>Cancel
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No upcoming appointments found.
                                </div>
                            <?php endif; ?>
                        </div>

                        <hr>

                        <!-- Completed Health Services -->
                        <div>
                            <h6 class="text-success mb-3"><i class="fas fa-check-circle"></i> Completed Health Services
                            </h6>
                            <?php
                            // Reset the result pointer to reuse the data
                            mysqli_data_seek($completed_appointments_result, 0);
                            if (mysqli_num_rows($completed_appointments_result) > 0): ?>
                                <div class="table-responsive shadow-sm rounded">
                                    <table class="table table-hover mb-0 completed-table">
                                        <thead class="table-success">
                                            <tr>
                                                <th class="border-0 py-3 px-4">
                                                    <i class="fas fa-check-circle me-2"></i>Service Type
                                                </th>
                                                <th class="border-0 py-3 px-4">
                                                    <i class="fas fa-calendar-check me-2"></i>Date Completed
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($completed = mysqli_fetch_assoc($completed_appointments_result)): ?>
                                                <tr class="completed-row">
                                                    <td class="py-3 px-4 align-middle">
                                                        <div class="d-flex align-items-center">
                                                            <div class="service-icon me-3">
                                                                <i
                                                                    class="fas fa-<?php echo $completed['appointment_type'] == 'Vaccination' ? 'syringe text-success' : 'stethoscope text-primary'; ?>"></i>
                                                            </div>
                                                            <div>
                                                                <span class="fw-semibold text-dark">
                                                                    <?php echo htmlspecialchars($completed['appointment_type']); ?>
                                                                    <?php if ($completed['record_type'] == 'vaccination'): ?>
                                                                        <small class="text-muted">- <?php echo htmlspecialchars($completed['patient_name']); ?></small>
                                                                    <?php endif; ?>
                                                                </span>
                                                                <div class="completed-badge">
                                                                    <small
                                                                        class="badge bg-success-subtle text-success border border-success-subtle">
                                                                        <i class="fas fa-check me-1"></i>Completed
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 px-4 align-middle">
                                                        <div class="completed-datetime">
                                                            <div class="fw-semibold text-dark">
                                                                <?php echo date('M d, Y', strtotime($completed['archived_at'])); ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-secondary">
                                    <i class="fas fa-calendar-times"></i> No completed health services found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Close
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal"
                            data-bs-target="#scheduleModal">
                            <i class="fas fa-calendar-plus"></i> Schedule New Appointment
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Modal -->
        <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="profileModalLabel">
                            <i class="fas fa-user-edit"></i> My Profile
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php include 'profile_modal.php'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Baby Vaccinations Modal -->
        <div class="modal fade" id="vaccinationsModal" tabindex="-1" aria-labelledby="vaccinationsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="vaccinationsModalLabel">
                            <i class="fas fa-syringe"></i> My Baby's Vaccination Schedules
                        </h5>
                    </div>
                    <div class="modal-body">
                        <!-- Loading State -->
                        <div id="vaccinationsLoading" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading vaccination schedules...</p>
                        </div>

                        <!-- Error State -->
                        <div id="vaccinationsError" class="alert alert-danger" style="display: none;">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <span id="vaccinationsErrorMessage">Failed to load vaccination schedules.</span>
                        </div>

                        <!-- Empty State -->
                        <div id="vaccinationsEmpty" class="text-center py-5" style="display: none;">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Upcoming Vaccinations</h5>
                            <p class="text-muted">There are no scheduled vaccinations for your babies at this time.</p>
                        </div>

                        <!-- Vaccinations List -->
                        <div id="vaccinationsList" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                These are vaccination schedules created by health workers for your babies.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-primary">
                                        <tr>
                                            <th><i class="fas fa-baby me-1"></i>Baby Name</th>
                                            <th><i class="fas fa-vial me-1"></i>Vaccine Type</th>
                                            <th><i class="fas fa-calendar me-1"></i>Schedule Date & Time</th>
                                            <th><i class="fas fa-flag me-1"></i>Status</th>
                                            <th><i class="fas fa-sticky-note me-1"></i>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vaccinationsTableBody">
                                        <!-- Vaccination rows will be inserted here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom spacing for better UX -->
        <div style="height: 60px;"></div>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <!-- Profile Modal JS -->
        <script src="assets/js/profile_modal.js"></script>

        <script>
            // Password toggle function for profile modal
            function togglePasswordVisibility(inputId, iconId) {
                const input = document.getElementById(inputId);
                const icon = document.getElementById(iconId);
                
                if (input && icon) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                }
            }
        </script>

        <script>
            // Set minimum date to today
            document.getElementById('preferred_date').min = new Date().toISOString().split('T')[0];

            // Time selection functionality - no default time selected
            const timeSelect = document.getElementById('preferred_time');

            // Function to check booked times and update time options
            async function updateAvailableTimeSlots(selectedDate) {
                if (!selectedDate) {
                    // Reset all time options to enabled if no date selected
                    const timeOptions = timeSelect.querySelectorAll('option');
                    timeOptions.forEach(option => {
                        if (option.value !== '') {
                            option.disabled = false;
                            option.textContent = option.textContent.replace(' (Booked)', '');
                        }
                    });
                    
                    // Hide availability info
                    const availabilityInfo = document.getElementById('time-availability-info');
                    if (availabilityInfo) {
                        availabilityInfo.style.display = 'none';
                    }
                    return;
                }

                // Add loading indicator
                const timeSelectContainer = timeSelect.parentElement;
                timeSelectContainer.classList.add('time-loading');
                timeSelect.disabled = true;

                try {
                    // Check appointment type
                    const appointmentType = document.getElementById('appointment_type').value;
                    let allBookedTimes = [];
                    
                    // Always fetch regular appointment booked times
                    const appointmentResponse = await fetch(`api/get_booked_times.php?date=${selectedDate}`);
                    
                    if (!appointmentResponse.ok) {
                        throw new Error(`HTTP error! status: ${appointmentResponse.status}`);
                    }
                    
                    const appointmentData = await appointmentResponse.json();
                    
                    if (appointmentData.error) {
                        console.error('Error fetching appointment booked times:', appointmentData.message);
                        showModal('Failed to check time availability. Please try again.', 'error');
                        return;
                    }
                    
                    allBookedTimes = [...appointmentData.booked_times];
                    
                    // If appointment type is Vaccination, also check vaccination booked times
                    if (appointmentType === 'Vaccination') {
                        const vaccinationResponse = await fetch(`api/get_booked_vaccination_times.php?date=${selectedDate}`);
                        
                        if (vaccinationResponse.ok) {
                            const vaccinationData = await vaccinationResponse.json();
                            
                            if (!vaccinationData.error && vaccinationData.booked_times) {
                                // Merge vaccination booked times with appointment booked times
                                allBookedTimes = [...new Set([...allBookedTimes, ...vaccinationData.booked_times])];
                            }
                        }
                    }
                    
                    // Get all time options
                    const timeOptions = timeSelect.querySelectorAll('option');
                    
                    // Reset all options first
                    timeOptions.forEach(option => {
                        if (option.value !== '') {
                            option.disabled = false;
                            option.textContent = option.textContent.replace(' (Booked)', '');
                        }
                    });
                    
                    // Disable booked time slots
                    allBookedTimes.forEach(bookedTime => {
                        timeOptions.forEach(option => {
                            if (option.value === bookedTime) {
                                option.disabled = true;
                                option.textContent += ' (Booked)';
                            }
                        });
                    });
                    
                    // Clear current selection if it's now booked
                    if (timeSelect.value && allBookedTimes.includes(timeSelect.value)) {
                        timeSelect.value = '';
                        showModal('Your selected time slot is no longer available. Please choose another time.', 'error');
                    }
                    
                    // Show feedback about availability
                    const availableSlots = Array.from(timeOptions).filter(option => 
                        option.value !== '' && !option.disabled
                    ).length;
                    
                    const availabilityInfo = document.getElementById('time-availability-info');
                    const availabilityMessage = document.getElementById('availability-message');
                    
                    if (availableSlots === 0) {
                        showModal('No time slots available for this date. Please choose another date.', 'error');
                        availabilityInfo.style.display = 'block';
                        availabilityMessage.innerHTML = '<span class="text-danger">All time slots are booked for this date</span>';
                    } else if (allBookedTimes.length > 0) {
                        availabilityInfo.style.display = 'block';
                        availabilityMessage.innerHTML = `<span class="text-warning">${allBookedTimes.length} time slot(s) already booked</span>`;
                    } else {
                        availabilityInfo.style.display = 'block';
                        availabilityMessage.innerHTML = '<span class="text-success">All time slots are available</span>';
                    }
                    
                } catch (error) {
                    console.error('Error updating available time slots:', error);
                    showModal('Failed to check time availability. Please try again.', 'error');
                } finally {
                    // Remove loading indicator
                    timeSelectContainer.classList.remove('time-loading');
                    timeSelect.disabled = false;
                }
            }

            // Add event listener to date input to check availability when date changes
            document.getElementById('preferred_date').addEventListener('change', function() {
                const selectedDate = this.value;
                updateAvailableTimeSlots(selectedDate);
            });

            // Form validation before submission
            document.querySelector('form[method="POST"]').addEventListener('submit', async function(e) {
                const selectedDate = document.getElementById('preferred_date').value;
                const selectedTime = document.getElementById('preferred_time').value;
                
                if (!selectedDate || !selectedTime) {
                    return; // Let normal validation handle this
                }
                
                // Check if the selected time is still available
                const selectedOption = timeSelect.querySelector(`option[value="${selectedTime}"]`);
                if (selectedOption && selectedOption.disabled) {
                    e.preventDefault();
                    showModal('The selected time slot is no longer available. Please choose another time.', 'error');
                    return false;
                }
                
                // Double-check with server before submitting
                try {
                    const response = await fetch(`api/get_booked_times.php?date=${selectedDate}`);
                    const data = await response.json();
                    
                    if (!data.error && data.booked_times.includes(selectedTime)) {
                        e.preventDefault();
                        showModal('This time slot was just booked by another user. Please choose another time.', 'error');
                        updateAvailableTimeSlots(selectedDate); // Refresh the time slots
                        return false;
                    }
                } catch (error) {
                    console.error('Error validating time slot:', error);
                    // Allow submission to proceed if validation fails
                }
            });

            // Toggle baby vaccination section based on appointment type
            function toggleBabyVaccination() {
                const appointmentType = document.getElementById('appointment_type').value;
                const babyVaccinationSection = document.getElementById('baby_vaccination_section');
                const babySelect = document.getElementById('selected_baby');
                const vaccinationTypeSection = document.getElementById('vaccination_type_section');
                const vaccinationTypeSelect = document.getElementById('vaccination_type');
                
                if (appointmentType === 'Vaccination') {
                    babyVaccinationSection.style.display = 'block';
                    // Make baby selection required for vaccination
                    if (babySelect) {
                        babySelect.setAttribute('required', 'required');
                    }
                } else {
                    babyVaccinationSection.style.display = 'none';
                    if (vaccinationTypeSection) {
                        vaccinationTypeSection.style.display = 'none';
                    }
                    // Reset selections when hidden
                    if (babySelect) {
                        babySelect.value = '';
                        babySelect.removeAttribute('required');
                    }
                    if (vaccinationTypeSelect) {
                        vaccinationTypeSelect.value = '';
                        vaccinationTypeSelect.removeAttribute('required');
                    }
                }
                
                // Re-check time slot availability when appointment type changes
                const selectedDate = document.getElementById('preferred_date').value;
                if (selectedDate) {
                    updateAvailableTimeSlots(selectedDate);
                }
            }

            // Load age-appropriate vaccines when baby is selected
            async function loadVaccinesByAge() {
                const babySelect = document.getElementById('selected_baby');
                const vaccinationTypeSection = document.getElementById('vaccination_type_section');
                const vaccinationTypeSelect = document.getElementById('vaccination_type');
                const vaccineAgeInfo = document.getElementById('vaccine-age-info');
                
                if (!babySelect || !vaccinationTypeSection || !vaccinationTypeSelect) {
                    return;
                }
                
                const babyId = babySelect.value;
                
                if (!babyId || babyId === '') {
                    vaccinationTypeSection.style.display = 'none';
                    vaccinationTypeSelect.value = '';
                    vaccinationTypeSelect.removeAttribute('required');
                    return;
                }
                
                // Show loading state
                vaccinationTypeSelect.innerHTML = '<option value="">Loading vaccines...</option>';
                vaccinationTypeSelect.disabled = true;
                vaccinationTypeSection.style.display = 'block';
                
                try {
                    const response = await fetch(`api/get_vaccines_by_age.php?baby_id=${babyId}`);
                    const data = await response.json();
                    
                    if (data.error) {
                        throw new Error(data.message || 'Failed to load vaccines');
                    }
                    
                    // Clear existing options
                    vaccinationTypeSelect.innerHTML = '<option value="">-- Select vaccination type --</option>';
                    
                    // Add vaccine options grouped by age
                    data.vaccines.forEach(group => {
                        const optgroup = document.createElement('optgroup');
                        optgroup.label = group.group;
                        
                        group.options.forEach(vaccine => {
                            const option = document.createElement('option');
                            option.value = vaccine.value;
                            option.textContent = vaccine.label;
                            optgroup.appendChild(option);
                        });
                        
                        vaccinationTypeSelect.appendChild(optgroup);
                    });
                    
                    // Update age info
                    const ageText = data.age_months === 1 ? '1 month' : `${data.age_months} months`;
                    vaccineAgeInfo.textContent = `Showing vaccines appropriate for ${ageText} old`;
                    
                    // Make vaccination type required
                    vaccinationTypeSelect.setAttribute('required', 'required');
                    vaccinationTypeSelect.disabled = false;
                    
                } catch (error) {
                    console.error('Error loading vaccines:', error);
                    vaccinationTypeSelect.innerHTML = '<option value="">Error loading vaccines</option>';
                    showModal('Failed to load vaccination types. Please try again.', 'error');
                    vaccinationTypeSelect.disabled = false;
                }
            }

            // Add event listener to baby selection
            document.addEventListener('DOMContentLoaded', function() {
                const babySelect = document.getElementById('selected_baby');
                if (babySelect) {
                    babySelect.addEventListener('change', loadVaccinesByAge);
                }
            });

            // Reset time selection when modal opens
            document.getElementById('scheduleModal').addEventListener('show.bs.modal', function () {
                timeSelect.value = ''; // Clear selection to show "Select time"
                
                // Reset all time options to enabled state
                const timeOptions = timeSelect.querySelectorAll('option');
                timeOptions.forEach(option => {
                    if (option.value !== '') {
                        option.disabled = false;
                        option.textContent = option.textContent.replace(' (Booked)', '');
                    }
                });
                
                // Hide availability info
                const availabilityInfo = document.getElementById('time-availability-info');
                if (availabilityInfo) {
                    availabilityInfo.style.display = 'none';
                }
                
                // Reset vaccination type section
                const vaccinationTypeSection = document.getElementById('vaccination_type_section');
                const vaccinationTypeSelect = document.getElementById('vaccination_type');
                if (vaccinationTypeSection) {
                    vaccinationTypeSection.style.display = 'none';
                }
                if (vaccinationTypeSelect) {
                    vaccinationTypeSelect.value = '';
                    vaccinationTypeSelect.removeAttribute('required');
                }
                
                // Check if there's already a selected date and update time slots
                const selectedDate = document.getElementById('preferred_date').value;
                if (selectedDate) {
                    updateAvailableTimeSlots(selectedDate);
                }
            });

            // Modal notification functions
            function showModal(message, type = 'success') {
                const modal = document.getElementById('messageModal');
                const modalIcon = document.getElementById('modalIcon');
                const modalIconSymbol = document.getElementById('modalIconSymbol');
                const modalTitle = document.getElementById('modalTitle');
                const modalMessage = document.getElementById('modalMessage');
                
                // Reset classes
                modal.className = 'message-modal';
                modalIcon.className = 'message-modal-icon';
                
                // Set content based on type
                if (type === 'success') {
                    modal.classList.add('message-modal-success');
                    modalIconSymbol.className = 'fas fa-check';
                    modalTitle.textContent = 'Success';
                } else {
                    modal.classList.add('message-modal-error');
                    modalIconSymbol.className = 'fas fa-exclamation-triangle';
                    modalTitle.textContent = 'Error';
                }
                
                modalMessage.textContent = message;
                
                // Show modal
                modal.classList.add('show');
                
                // Auto-hide after 5 seconds
                setTimeout(() => {
                    hideModal();
                }, 5000);
            }
            
            function hideModal() {
                const modal = document.getElementById('messageModal');
                modal.classList.add('hiding');
                
                setTimeout(() => {
                    modal.classList.remove('show', 'hiding');
                }, 300);
            }
            
            // Close modal when clicking outside
            document.addEventListener('click', function(e) {
                const modal = document.getElementById('messageModal');
                if (e.target === modal) {
                    hideModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    hideModal();
                }
            });

            // Show success message if redirected after successful submission
            <?php if (!empty($success)): ?>
                showModal('<?php echo addslashes($success); ?>', 'success');

                // Remove the success parameter from URL to prevent showing on refresh
                if (window.history.replaceState) {
                    var url = new URL(window.location);
                    url.searchParams.delete('success');
                    window.history.replaceState({}, document.title, url.pathname + url.search);
                }
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                showModal('<?php echo addslashes($error); ?>', 'error');
            <?php endif; ?>

            // Show modal if there's an error
            <?php if (!empty($error)): ?>
                var scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
                scheduleModal.show();
            <?php endif; ?>

            // ========================================
            // Daily Health Tip - API Integration
            // ========================================

            // API Configuration - Using PHP proxy to avoid CORS issues
            const API_URL = 'api/get_health_tip.php';

            /**
             * Fetch a random health tip from API Ninjas Quotes API
             * Uses PHP proxy to handle server-side API calls
             * Uses async/await for cleaner asynchronous code
             */
            async function fetchHealthTip() {
                // Get DOM elements
                const loadingState = document.getElementById('loadingState');
                const tipContent = document.getElementById('tipContent');
                const errorState = document.getElementById('errorState');
                const refreshBtn = document.getElementById('refreshTipBtn');

                // Show loading state, hide others
                loadingState.style.display = 'block';
                tipContent.style.display = 'none';
                errorState.style.display = 'none';
                
                // Add spinning animation to refresh button
                refreshBtn.classList.add('spinning');

                try {
                    // Make request to PHP proxy
                    const response = await fetch(API_URL, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });

                    // Check if response is successful
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    // Parse JSON response
                    const data = await response.json();

                    // Check for error in response
                    if (data.error) {
                        throw new Error(data.message || 'API error');
                    }

                    // Check if we received valid data
                    if (data && data.length > 0) {
                        const quote = data[0]; // API returns an array, get first item
                        
                        // Update DOM with quote data
                        document.getElementById('tipQuote').textContent = quote.quote;
                        document.getElementById('tipAuthor').textContent = quote.author || 'Unknown';

                        // Hide loading, show content
                        loadingState.style.display = 'none';
                        tipContent.style.display = 'block';
                    } else {
                        // No data received
                        throw new Error('No health tip available');
                    }

                } catch (error) {
                    // Log error for debugging
                    console.error('Error fetching health tip:', error);

                    // Hide loading, show error state
                    loadingState.style.display = 'none';
                    errorState.style.display = 'block';

                } finally {
                    // Remove spinning animation from refresh button
                    refreshBtn.classList.remove('spinning');
                }
            }

            /**
             * Event listener for refresh button
             * Fetches a new health tip when clicked
             */
            document.getElementById('refreshTipBtn').addEventListener('click', function() {
                fetchHealthTip();
            });

            /**
             * Initialize - Fetch health tip when page loads
             * Wait for DOM to be fully loaded
             */
            document.addEventListener('DOMContentLoaded', function() {
                fetchHealthTip();
            });

            // ========================================
            // Weather Section - OpenWeatherMap API Integration
            // ========================================

            /**
             * Weather API Configuration
             * Uses PHP proxy to avoid CORS issues and keep API key secure
             */
            const WEATHER_API_URL = 'api/get_weather.php?city=Manila,PH';

            /**
             * Fetch weather data from OpenWeatherMap API
             * Uses async/await for cleaner asynchronous code
             * Displays temperature in Celsius, weather condition, humidity, and wind speed
             */
            async function fetchWeatherData() {
                // Get DOM elements
                const weatherLoadingState = document.getElementById('weatherLoadingState');
                const weatherContent = document.getElementById('weatherContent');
                const weatherErrorState = document.getElementById('weatherErrorState');
                const refreshWeatherBtn = document.getElementById('refreshWeatherBtn');

                // Show loading state, hide others
                weatherLoadingState.style.display = 'block';
                weatherContent.style.display = 'none';
                weatherErrorState.style.display = 'none';
                
                // Add spinning animation to refresh button
                refreshWeatherBtn.classList.add('spinning');

                try {
                    // Make request to PHP proxy
                    const response = await fetch(WEATHER_API_URL, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });

                    // Check if response is successful
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    // Parse JSON response
                    const data = await response.json();

                    // Check for error in proxy response
                    if (data.error) {
                        throw new Error(data.message || 'Weather API error');
                    }

                    // Check for API error response
                    if (data.cod && data.cod !== 200) {
                        throw new Error(data.message || 'Weather API error');
                    }

                    // Check if we received valid data
                    if (data && data.main && data.weather && data.weather.length > 0) {
                        // Extract weather information
                        const cityName = data.name || 'Unknown';
                        const temperature = Math.round(data.main.temp); // Round to nearest integer
                        const weatherCondition = data.weather[0].main; // e.g., "Clear", "Rain", "Clouds"
                        const weatherDescription = data.weather[0].description; // e.g., "clear sky"
                        const weatherIcon = data.weather[0].icon; // Icon code from API
                        const humidity = data.main.humidity; // Humidity percentage
                        const windSpeed = data.wind.speed; // Wind speed in m/s

                        // Weather icon URL from OpenWeatherMap
                        const iconUrl = `https://openweathermap.org/img/wn/${weatherIcon}@2x.png`;

                        // Update DOM with weather data
                        document.getElementById('weatherCity').textContent = cityName;
                        document.getElementById('weatherTemp').textContent = `${temperature}°C`;
                        document.getElementById('weatherCondition').textContent = weatherCondition;
                        document.getElementById('weatherIcon').src = iconUrl;
                        document.getElementById('weatherIcon').alt = weatherDescription;
                        document.getElementById('weatherHumidity').textContent = `${humidity}%`;
                        document.getElementById('weatherWind').textContent = `${windSpeed} m/s`;

                        // Hide loading, show content
                        weatherLoadingState.style.display = 'none';
                        weatherContent.style.display = 'block';

                    } else {
                        // Invalid data structure
                        throw new Error('Invalid weather data received');
                    }

                } catch (error) {
                    // Log error for debugging
                    console.error('Error fetching weather data:', error);
                    console.error('Error details:', error.message);

                    // Hide loading, show error state
                    weatherLoadingState.style.display = 'none';
                    weatherErrorState.style.display = 'block';
                    
                    // Update error message with more details
                    const errorMsg = weatherErrorState.querySelector('p');
                    if (errorMsg && error.message) {
                        errorMsg.textContent = `Unable to load weather information. ${error.message}`;
                    }

                } finally {
                    // Remove spinning animation from refresh button
                    refreshWeatherBtn.classList.remove('spinning');
                }
            }

            /**
             * Event listener for weather refresh button
             * Fetches new weather data when clicked
             */
            document.getElementById('refreshWeatherBtn').addEventListener('click', function() {
                fetchWeatherData();
            });

            /**
             * Initialize - Fetch weather data when page loads
             * Wait for DOM to be fully loaded
             */
            document.addEventListener('DOMContentLoaded', function() {
                fetchWeatherData();
            });

            // ========================================
            // Profile Modal Initialization
            // ========================================
            
            /**
             * Initialize profile modal forms when modal is shown
             * This ensures the forms are properly set up each time the modal opens
             */
            const profileModal = document.getElementById('profileModal');
            if (profileModal) {
                // Initialize on page load since profile modal is included directly
                if (typeof initializeProfileForms === 'function') {
                    initializeProfileForms();
                }
                // Initialize password toggles on page load
                if (typeof initializePasswordToggles === 'function') {
                    initializePasswordToggles();
                }
                
                profileModal.addEventListener('shown.bs.modal', function () {
                    console.log('Profile modal shown - initializing forms');
                    // Wait a bit for content to fully load
                    setTimeout(function() {
                        if (typeof initializeProfileForms === 'function') {
                            initializeProfileForms();
                        }
                        // Initialize password toggles
                        if (typeof initializePasswordToggles === 'function') {
                            initializePasswordToggles();
                        }
                    }, 100);
                });
            }

            // ========================================
            // Baby Vaccinations Modal
            // ========================================
            
            /**
             * Fetch and display baby vaccination schedules
             */
            async function loadBabyVaccinations() {
                const loadingState = document.getElementById('vaccinationsLoading');
                const errorState = document.getElementById('vaccinationsError');
                const emptyState = document.getElementById('vaccinationsEmpty');
                const listState = document.getElementById('vaccinationsList');
                const tableBody = document.getElementById('vaccinationsTableBody');
                
                // Show loading state
                loadingState.style.display = 'block';
                errorState.style.display = 'none';
                emptyState.style.display = 'none';
                listState.style.display = 'none';
                
                try {
                    const response = await fetch('api/get_baby_vaccinations.php');
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.error) {
                        throw new Error(data.message || 'Failed to load vaccinations');
                    }
                    
                    // Hide loading
                    loadingState.style.display = 'none';
                    
                    // Check if there are vaccinations
                    if (data.count === 0) {
                        emptyState.style.display = 'block';
                        return;
                    }
                    
                    // Display vaccinations
                    tableBody.innerHTML = '';
                    
                    data.vaccinations.forEach(vaccination => {
                        const row = document.createElement('tr');
                        
                        // Format date and time
                        const scheduleDate = new Date(vaccination.schedule_date);
                        const dateStr = scheduleDate.toLocaleDateString('en-US', { 
                            month: 'short', 
                            day: 'numeric', 
                            year: 'numeric' 
                        });
                        const timeStr = scheduleDate.toLocaleTimeString('en-US', { 
                            hour: 'numeric', 
                            minute: '2-digit',
                            hour12: true 
                        });
                        
                        // Calculate baby age
                        const dob = new Date(vaccination.date_of_birth);
                        const ageMonths = Math.floor((Date.now() - dob.getTime()) / (1000 * 60 * 60 * 24 * 30.44));
                        const ageText = ageMonths < 12 
                            ? `${ageMonths} month${ageMonths !== 1 ? 's' : ''} old`
                            : `${Math.floor(ageMonths / 12)} year${Math.floor(ageMonths / 12) !== 1 ? 's' : ''} old`;
                        
                        // Status badge
                        let statusBadge = '';
                        if (vaccination.status === 'confirmed') {
                            statusBadge = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Confirmed</span>';
                        } else if (vaccination.status === 'pending') {
                            statusBadge = '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Pending</span>';
                        }
                        
                        row.innerHTML = `
                            <td class="align-middle">
                                <div>
                                    <strong>${escapeHtml(vaccination.baby_name)}</strong>
                                    <br>
                                    <small class="text-muted">${ageText}</small>
                                </div>
                            </td>
                            <td class="align-middle">
                                <i class="fas fa-vial text-primary me-1"></i>
                                ${escapeHtml(vaccination.vaccine_type)}
                            </td>
                            <td class="align-middle">
                                <div>
                                    <strong>${dateStr}</strong>
                                    <br>
                                    <small class="text-muted">${timeStr}</small>
                                </div>
                            </td>
                            <td class="align-middle text-center">
                                ${statusBadge}
                            </td>
                            <td class="align-middle">
                                ${vaccination.notes ? escapeHtml(vaccination.notes) : '<span class="text-muted fst-italic">No notes</span>'}
                            </td>
                        `;
                        
                        tableBody.appendChild(row);
                    });
                    
                    listState.style.display = 'block';
                    
                } catch (error) {
                    console.error('Error loading vaccinations:', error);
                    loadingState.style.display = 'none';
                    errorState.style.display = 'block';
                    document.getElementById('vaccinationsErrorMessage').textContent = error.message;
                }
            }
            
            /**
             * Helper function to escape HTML
             */
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            /**
             * Load vaccinations when modal is opened
             */
            const vaccinationsModal = document.getElementById('vaccinationsModal');
            if (vaccinationsModal) {
                vaccinationsModal.addEventListener('show.bs.modal', function () {
                    loadBabyVaccinations();
                });
            }

        </script>
</body>

</html>