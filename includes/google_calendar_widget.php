<?php
/**
 * Google Calendar Widget Component
 * Reusable widget for displaying Google Calendar integration
 * 
 * Usage: include 'includes/google_calendar_widget.php';
 */

// Check if user has connected Google Calendar
$userId = $_SESSION['user_id'];
$calendarConnected = false;

$tokenCheckQuery = "SELECT id FROM user_google_tokens WHERE user_id = $userId";
$tokenCheckResult = mysqli_query($conn, $tokenCheckQuery);
if ($tokenCheckResult && mysqli_num_rows($tokenCheckResult) > 0) {
    $calendarConnected = true;
}
?>

<!-- Appointments Calendar Widget -->
<div class="calendar-widget">
    <div class="calendar-widget-header">
        <h3 class="calendar-widget-title">
            <i class="fas fa-calendar-alt"></i>
            Appointments Calendar
        </h3>
        <div style="display: flex; align-items: center; gap: 10px;">
            <?php if ($calendarConnected): ?>
                <span class="calendar-status connected">
                    <i class="fab fa-google"></i>
                    Connected
                </span>
                <button class="calendar-disconnect-btn" id="disconnectCalendarBtn" title="Disconnect Google Calendar">
                    <i class="fas fa-unlink"></i>
                    Disconnect
                </button>
                <button class="calendar-sync-all-btn" id="syncAllAppointmentsBtn" title="Sync all appointments to Google Calendar">
                    <i class="fas fa-sync-alt"></i>
                    Sync All
                </button>
            <?php else: ?>
                <button class="calendar-connect-btn" onclick="window.location.href='api/google_calendar_auth.php'" title="Sync with Google Calendar">
                    <i class="fab fa-google"></i>
                    Connect Calendar
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div id="googleCalendarWidget" data-connected="<?php echo $calendarConnected ? 'true' : 'false'; ?>">
        <!-- Widget content loads here via JavaScript -->
        <div class="calendar-widget-body">
            <div class="calendar-loading">
                <div class="spinner"></div>
                <p>Loading calendar...</p>
            </div>
        </div>
    </div>
</div>

<!-- Success message if just connected -->
<?php if (isset($_GET['calendar_connected']) && $_GET['calendar_connected'] == '1'): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Show success modal
        showCalendarSuccessModal();
        
        // Remove query parameter from URL
        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.delete('calendar_connected');
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }
    });
    
    function showCalendarSuccessModal() {
        // Create modal HTML
        const modalHTML = `
            <div class="message-modal message-modal-success show" id="calendarSuccessModal">
                <div class="message-modal-content">
                    <div class="message-modal-header">
                        <button type="button" class="message-modal-close" onclick="hideCalendarModal()">
                            <i class="fas fa-times"></i>
                        </button>
                        <div class="message-modal-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <h3 class="message-modal-title">Google Calendar Connected!</h3>
                    </div>
                    <div class="message-modal-body">
                        <p class="message-modal-message">
                            <i class="fab fa-google me-2"></i>
                            Your Google Calendar has been connected successfully. Your appointments will now sync automatically.
                        </p>
                        <div class="message-modal-actions">
                            <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="hideCalendarModal()">
                                <i class="fas fa-check me-2"></i>Got it!
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            hideCalendarModal();
        }, 5000);
    }
    
    function hideCalendarModal() {
        const modal = document.getElementById('calendarSuccessModal');
        if (modal) {
            modal.classList.add('hiding');
            setTimeout(() => {
                modal.remove();
            }, 300);
        }
    }
    
    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('calendarSuccessModal');
        if (modal && e.target === modal) {
            hideCalendarModal();
        }
    });
</script>
<?php endif; ?>

<!-- Include Calendar Widget Styles and Scripts -->
<link rel="stylesheet" href="assets/css/google_calendar_widget.css">
<link rel="stylesheet" href="assets/css/success-error_messages.css">
<script src="assets/js/google_calendar_widget.js"></script>
