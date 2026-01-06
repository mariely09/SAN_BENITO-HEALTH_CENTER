<?php
/**
 * Google Calendar API Functions
 * Functions to create, update, delete, and retrieve calendar events
 */

require_once 'google_calendar_config.php';

/**
 * Create a calendar event
 * 
 * @param string $accessToken Google access token
 * @param array $eventData Event details
 * @return array|false Event data or false on failure
 */
function createCalendarEvent($accessToken, $eventData) {
    $url = GOOGLE_CALENDAR_API_URL . '/calendars/primary/events';
    
    $event = [
        'summary' => $eventData['summary'],
        'description' => $eventData['description'] ?? '',
        'start' => [
            'dateTime' => $eventData['start_datetime'],
            'timeZone' => 'Asia/Manila'
        ],
        'end' => [
            'dateTime' => $eventData['end_datetime'],
            'timeZone' => 'Asia/Manila'
        ],
        'reminders' => [
            'useDefault' => false,
            'overrides' => [
                ['method' => 'email', 'minutes' => 24 * 60], // 1 day before
                ['method' => 'popup', 'minutes' => 60] // 1 hour before
            ]
        ]
    ];
    
    if (isset($eventData['location'])) {
        $event['location'] = $eventData['location'];
    }
    
    error_log("createCalendarEvent: Sending request to Google Calendar API");
    error_log("createCalendarEvent: Event payload - " . json_encode($event));
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("createCalendarEvent: HTTP Code: $httpCode");
    error_log("createCalendarEvent: Response: $response");
    
    // Store for debugging
    if (isset($GLOBALS)) {
        $GLOBALS['last_http_code'] = $httpCode;
        $GLOBALS['last_api_response'] = $response;
        $GLOBALS['last_api_error'] = $curlError;
    }
    
    if ($curlError) {
        error_log("createCalendarEvent: CURL Error: $curlError");
    }
    
    // Google Calendar API returns 200 for successful event creation
    // Some APIs return 201 for created resources, but Google uses 200
    if ($httpCode === 200 || $httpCode === 201) {
        error_log("createCalendarEvent: Successfully created event");
        return json_decode($response, true);
    }
    
    error_log("createCalendarEvent: Failed to create calendar event. HTTP Code: $httpCode, Response: $response");
    return false;
}

/**
 * Update a calendar event
 * 
 * @param string $accessToken Google access token
 * @param string $eventId Google Calendar event ID
 * @param array $eventData Updated event details
 * @return array|false Updated event data or false on failure
 */
function updateCalendarEvent($accessToken, $eventId, $eventData) {
    $url = GOOGLE_CALENDAR_API_URL . '/calendars/primary/events/' . $eventId;
    
    $event = [
        'summary' => $eventData['summary'],
        'description' => $eventData['description'] ?? '',
        'start' => [
            'dateTime' => $eventData['start_datetime'],
            'timeZone' => 'Asia/Manila'
        ],
        'end' => [
            'dateTime' => $eventData['end_datetime'],
            'timeZone' => 'Asia/Manila'
        ]
    ];
    
    if (isset($eventData['location'])) {
        $event['location'] = $eventData['location'];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    error_log("Failed to update calendar event. HTTP Code: $httpCode, Response: $response");
    return false;
}

/**
 * Delete a calendar event
 * 
 * @param string $accessToken Google access token
 * @param string $eventId Google Calendar event ID
 * @return bool Success status
 */
function deleteCalendarEvent($accessToken, $eventId) {
    $url = GOOGLE_CALENDAR_API_URL . '/calendars/primary/events/' . $eventId;
    
    error_log("deleteCalendarEvent: Attempting to delete event $eventId");
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("deleteCalendarEvent: HTTP Code: $httpCode");
    if ($curlError) {
        error_log("deleteCalendarEvent: CURL Error: $curlError");
    }
    
    // 204 No Content means successful deletion
    // 410 Gone means already deleted (also consider success)
    if ($httpCode === 204 || $httpCode === 200 || $httpCode === 410) {
        error_log("deleteCalendarEvent: Successfully deleted event $eventId");
        return true;
    }
    
    error_log("deleteCalendarEvent: Failed to delete event $eventId. HTTP Code: $httpCode, Response: $response");
    return false;
}

/**
 * Get upcoming calendar events
 * 
 * @param string $accessToken Google access token
 * @param int $maxResults Maximum number of events to retrieve
 * @return array|false Array of events or false on failure
 */
function getUpcomingEvents($accessToken, $maxResults = 10) {
    $timeMin = urlencode(date('c')); // Current time in RFC3339 format
    $url = GOOGLE_CALENDAR_API_URL . "/calendars/primary/events?maxResults=$maxResults&orderBy=startTime&singleEvents=true&timeMin=$timeMin";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['items'] ?? [];
    }
    
    return false;
}

/**
 * Sync appointment to Google Calendar
 * Creates or updates calendar event based on appointment data
 * 
 * @param object $conn Database connection
 * @param int $appointmentId Appointment ID
 * @param int $userId User ID
 * @return bool Success status
 */
function syncAppointmentToCalendar($conn, $appointmentId, $userId) {
    // Get valid access token
    $accessToken = getValidAccessToken($conn, $userId);
    if (!$accessToken) {
        error_log("syncAppointmentToCalendar: Failed to get valid access token for user $userId");
        return false;
    }
    
    // Get appointment details
    $query = "SELECT * FROM appointments WHERE id = $appointmentId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        error_log("syncAppointmentToCalendar: Appointment $appointmentId not found");
        return false;
    }
    
    $appointment = mysqli_fetch_assoc($result);
    
    // Set timezone to Asia/Manila to ensure correct datetime
    date_default_timezone_set('Asia/Manila');
    
    // Get the exact datetime from database
    $preferredDatetime = $appointment['preferred_datetime'];
    
    // Create DateTime objects with Manila timezone
    $startDate = new DateTime($preferredDatetime, new DateTimeZone('Asia/Manila'));
    $endDate = clone $startDate;
    $endDate->modify('+1 hour');
    
    // Format for Google Calendar API (RFC3339 format with timezone)
    $startDateTime = $startDate->format('c');
    $endDateTime = $endDate->format('c');
    
    error_log("syncAppointmentToCalendar: Original datetime from DB: $preferredDatetime");
    error_log("syncAppointmentToCalendar: Formatted start: $startDateTime");
    error_log("syncAppointmentToCalendar: Formatted end: $endDateTime");
    
    // Prepare event data
    $eventData = [
        'summary' => 'Health Appointment: ' . $appointment['appointment_type'],
        'description' => "Appointment Type: {$appointment['appointment_type']}\n" .
                        "Patient: {$appointment['fullname']}\n" .
                        "Status: {$appointment['status']}\n" .
                        ($appointment['notes'] ? "Notes: {$appointment['notes']}" : ''),
        'start_datetime' => $startDateTime,
        'end_datetime' => $endDateTime,
        'location' => 'Barangay Health Center'
    ];
    
    error_log("syncAppointmentToCalendar: Syncing appointment $appointmentId for user $userId");
    error_log("syncAppointmentToCalendar: Event data - " . json_encode($eventData));
    
    // Check if event already exists FOR THIS USER
    $checkQuery = "SELECT google_event_id FROM appointment_calendar_sync 
                   WHERE appointment_id = $appointmentId 
                   AND user_id = $userId";
    $checkResult = mysqli_query($conn, $checkQuery);
    
    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
        // Update existing event for this user
        $syncData = mysqli_fetch_assoc($checkResult);
        $googleEventId = $syncData['google_event_id'];
        
        error_log("syncAppointmentToCalendar: Updating existing event $googleEventId for user $userId");
        $updatedEvent = updateCalendarEvent($accessToken, $googleEventId, $eventData);
        
        if ($updatedEvent) {
            $updateQuery = "UPDATE appointment_calendar_sync 
                           SET last_synced_at = NOW() 
                           WHERE appointment_id = $appointmentId 
                           AND user_id = $userId";
            mysqli_query($conn, $updateQuery);
            error_log("syncAppointmentToCalendar: Successfully updated event $googleEventId");
            return true;
        } else {
            error_log("syncAppointmentToCalendar: Failed to update event $googleEventId");
        }
    } else {
        // Create new event
        error_log("syncAppointmentToCalendar: Creating new event");
        $createdEvent = createCalendarEvent($accessToken, $eventData);
        
        if ($createdEvent && isset($createdEvent['id'])) {
            $googleEventId = mysqli_real_escape_string($conn, $createdEvent['id']);
            $insertQuery = "INSERT INTO appointment_calendar_sync 
                           (appointment_id, user_id, google_event_id, last_synced_at) 
                           VALUES ($appointmentId, $userId, '$googleEventId', NOW())";
            mysqli_query($conn, $insertQuery);
            error_log("syncAppointmentToCalendar: Successfully created event $googleEventId");
            return true;
        } else {
            error_log("syncAppointmentToCalendar: Failed to create event - " . json_encode($createdEvent));
        }
    }
    
    return false;
}

/**
 * Delete appointment from Google Calendar
 * 
 * @param object $conn Database connection
 * @param int $appointmentId Appointment ID
 * @param int $userId User ID
 * @return bool Success status
 */
function deleteAppointmentFromCalendar($conn, $appointmentId, $userId) {
    // Get valid access token
    $accessToken = getValidAccessToken($conn, $userId);
    if (!$accessToken) {
        return false;
    }
    
    // Get Google event ID
    $query = "SELECT google_event_id FROM appointment_calendar_sync WHERE appointment_id = $appointmentId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        return false;
    }
    
    $syncData = mysqli_fetch_assoc($result);
    $googleEventId = $syncData['google_event_id'];
    
    // Delete from Google Calendar
    $deleted = deleteCalendarEvent($accessToken, $googleEventId);
    
    if ($deleted) {
        // Remove sync record
        $deleteQuery = "DELETE FROM appointment_calendar_sync WHERE appointment_id = $appointmentId";
        mysqli_query($conn, $deleteQuery);
        return true;
    }
    
    return false;
}

/**
 * Sync vaccination to Google Calendar
 * Creates or updates calendar event based on vaccination data
 * 
 * @param object $conn Database connection
 * @param int $vaccinationId Vaccination ID
 * @param int $userId User ID
 * @return bool Success status
 */
function syncVaccinationToCalendar($conn, $vaccinationId, $userId) {
    // Get valid access token
    $accessToken = getValidAccessToken($conn, $userId);
    if (!$accessToken) {
        error_log("syncVaccinationToCalendar: Failed to get valid access token for user $userId");
        return false;
    }
    
    // Get vaccination details with baby information
    $query = "SELECT v.*, b.full_name as baby_name, b.parent_guardian_name 
              FROM vaccinations v 
              LEFT JOIN babies b ON v.baby_id = b.id 
              WHERE v.id = $vaccinationId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        error_log("syncVaccinationToCalendar: Vaccination $vaccinationId not found");
        return false;
    }
    
    $vaccination = mysqli_fetch_assoc($result);
    
    // Set timezone to Asia/Manila to ensure correct datetime
    date_default_timezone_set('Asia/Manila');
    
    // Get the exact datetime from database
    $scheduleDatetime = $vaccination['schedule_date'];
    
    // Create DateTime objects with Manila timezone
    $startDate = new DateTime($scheduleDatetime, new DateTimeZone('Asia/Manila'));
    $endDate = clone $startDate;
    $endDate->modify('+30 minutes'); // Vaccinations typically take 30 minutes
    
    // Format for Google Calendar API (RFC3339 format with timezone)
    $startDateTime = $startDate->format('c');
    $endDateTime = $endDate->format('c');
    
    error_log("syncVaccinationToCalendar: Original datetime from DB: $scheduleDatetime");
    error_log("syncVaccinationToCalendar: Formatted start: $startDateTime");
    error_log("syncVaccinationToCalendar: Formatted end: $endDateTime");
    
    // Prepare event data
    $eventData = [
        'summary' => 'Vaccination - ' . $vaccination['baby_name'],
        'description' => "Vaccine Type: {$vaccination['vaccine_type']}\n" .
                        "Baby: {$vaccination['baby_name']}\n" .
                        "Parent/Guardian: {$vaccination['parent_guardian_name']}\n" .
                        "Status: {$vaccination['status']}\n" .
                        ($vaccination['notes'] ? "Notes: {$vaccination['notes']}" : ''),
        'start_datetime' => $startDateTime,
        'end_datetime' => $endDateTime,
        'location' => 'Barangay Health Center'
    ];
    
    error_log("syncVaccinationToCalendar: Syncing vaccination $vaccinationId for user $userId");
    error_log("syncVaccinationToCalendar: Event data - " . json_encode($eventData));
    
    // Check if event already exists FOR THIS USER
    $checkQuery = "SELECT google_event_id FROM vaccination_calendar_sync 
                   WHERE vaccination_id = $vaccinationId 
                   AND user_id = $userId";
    $checkResult = mysqli_query($conn, $checkQuery);
    
    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
        // Update existing event for this user
        $syncData = mysqli_fetch_assoc($checkResult);
        $googleEventId = $syncData['google_event_id'];
        
        error_log("syncVaccinationToCalendar: Updating existing event $googleEventId for user $userId");
        $updatedEvent = updateCalendarEvent($accessToken, $googleEventId, $eventData);
        
        if ($updatedEvent) {
            $updateQuery = "UPDATE vaccination_calendar_sync 
                           SET last_synced_at = NOW() 
                           WHERE vaccination_id = $vaccinationId 
                           AND user_id = $userId";
            mysqli_query($conn, $updateQuery);
            error_log("syncVaccinationToCalendar: Successfully updated event $googleEventId");
            return true;
        } else {
            error_log("syncVaccinationToCalendar: Failed to update event $googleEventId");
        }
    } else {
        // Create new event
        error_log("syncVaccinationToCalendar: Creating new event");
        $createdEvent = createCalendarEvent($accessToken, $eventData);
        
        if ($createdEvent && isset($createdEvent['id'])) {
            $googleEventId = mysqli_real_escape_string($conn, $createdEvent['id']);
            
            // Create sync table if it doesn't exist
            $createTableQuery = "CREATE TABLE IF NOT EXISTS vaccination_calendar_sync (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vaccination_id INT NOT NULL,
                user_id INT NOT NULL,
                google_event_id VARCHAR(255) NOT NULL,
                last_synced_at DATETIME NOT NULL,
                UNIQUE KEY unique_vaccination_user (vaccination_id, user_id),
                KEY idx_vaccination_id (vaccination_id),
                KEY idx_user_id (user_id)
            )";
            mysqli_query($conn, $createTableQuery);
            
            $insertQuery = "INSERT INTO vaccination_calendar_sync 
                           (vaccination_id, user_id, google_event_id, last_synced_at) 
                           VALUES ($vaccinationId, $userId, '$googleEventId', NOW())";
            mysqli_query($conn, $insertQuery);
            error_log("syncVaccinationToCalendar: Successfully created event $googleEventId");
            return true;
        } else {
            error_log("syncVaccinationToCalendar: Failed to create event - " . json_encode($createdEvent));
        }
    }
    
    return false;
}

/**
 * Delete vaccination from Google Calendar for all synced users
 * 
 * @param object $conn Database connection
 * @param int $vaccinationId Vaccination ID
 * @return bool Success status
 */
function deleteVaccinationFromCalendar($conn, $vaccinationId) {
    // Get all synced users for this vaccination
    $query = "SELECT user_id, google_event_id FROM vaccination_calendar_sync WHERE vaccination_id = $vaccinationId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        return false;
    }
    
    $success = true;
    while ($syncData = mysqli_fetch_assoc($result)) {
        $userId = $syncData['user_id'];
        $googleEventId = $syncData['google_event_id'];
        
        // Get valid access token
        $accessToken = getValidAccessToken($conn, $userId);
        if ($accessToken) {
            // Delete from Google Calendar
            deleteCalendarEvent($accessToken, $googleEventId);
            error_log("Deleted Google Calendar event $googleEventId for user $userId (vaccination deleted)");
        }
    }
    
    // Remove all sync records for this vaccination
    $deleteQuery = "DELETE FROM vaccination_calendar_sync WHERE vaccination_id = $vaccinationId";
    mysqli_query($conn, $deleteQuery);
    
    return $success;
}
