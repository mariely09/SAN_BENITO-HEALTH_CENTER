/**
 * Google Calendar Widget JavaScript
 * Handles calendar integration, event syncing, and UI updates
 */

class GoogleCalendarWidget {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.isConnected = false;
        this.events = [];
        this.currentDate = new Date();
        this.currentMonth = this.currentDate.getMonth();
        this.currentYear = this.currentDate.getFullYear();
        this.init();
    }

    /**
     * Initialize the widget
     */
    init() {
        this.checkAndAutoSync();
        this.loadCalendarEvents();
        this.setupEventListeners();
    }

    /**
     * Check if user is connected and auto-sync appointments and vaccinations
     */
    async checkAndAutoSync() {
        const isConnected = this.container.dataset.connected === 'true';
        
        if (isConnected) {
            // Get last sync timestamp
            const lastSync = localStorage.getItem('calendar_last_sync');
            const now = Date.now();
            const fiveMinutes = 5 * 60 * 1000; // 5 minutes in milliseconds
            
            // Auto-sync if:
            // 1. Never synced before, OR
            // 2. Last sync was more than 5 minutes ago
            if (!lastSync || (now - parseInt(lastSync)) > fiveMinutes) {
                console.log('Auto-syncing appointments and vaccinations to Google Calendar...');
                await this.syncAllEvents();
                localStorage.setItem('calendar_last_sync', now.toString());
            } else {
                console.log('Skipping auto-sync (last sync was recent)');
            }
        }
    }

    /**
     * Sync all appointments and vaccinations to Google Calendar
     */
    async syncAllEvents() {
        try {
            // Sync appointments
            const appointmentsResponse = await fetch('api/sync_all_appointments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const appointmentsData = await appointmentsResponse.json();

            // Sync vaccinations
            const vaccinationsResponse = await fetch('api/sync_all_vaccinations.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const vaccinationsData = await vaccinationsResponse.json();

            if (appointmentsData.success && vaccinationsData.success) {
                const totalSynced = (appointmentsData.synced || 0) + (vaccinationsData.synced || 0);
                const totalSkipped = (appointmentsData.skipped || 0) + (vaccinationsData.skipped || 0);
                const totalFailed = (appointmentsData.failed || 0) + (vaccinationsData.failed || 0);
                
                console.log(`Auto-sync complete: ${totalSynced} synced, ${totalSkipped} skipped, ${totalFailed} failed`);
                
                if (totalFailed > 0) {
                    console.error('Some events failed to sync');
                }
                
                // Show notification if events were synced
                if (totalSynced > 0) {
                    this.showSyncNotification(totalSynced);
                }
            } else if (appointmentsData.needs_auth || vaccinationsData.needs_auth) {
                console.log('Google Calendar authentication needed');
            } else {
                console.error('Auto-sync failed');
            }
        } catch (error) {
            console.error('Error during auto-sync:', error);
        }
    }

    /**
     * Show sync notification
     */
    showSyncNotification(count) {
        const notification = document.createElement('div');
        notification.className = 'calendar-sync-notification';
        notification.innerHTML = `
            <i class="fas fa-check-circle"></i>
            <span>${count} event${count > 1 ? 's' : ''} synced to Google Calendar</span>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        // Remove after 5 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    }

    /**
     * Load calendar events from Google Calendar
     */
    async loadCalendarEvents() {
        this.showLoadingState();

        try {
            const response = await fetch('api/get_calendar_events.php?max_results=10');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            console.log('Calendar API Response:', data);

            if (data.success && data.events) {
                this.events = data.events;
                this.isConnected = true;
                this.renderEvents();
            } else if (data.needs_auth) {
                this.isConnected = false;
                this.showNotConnectedState();
            } else {
                this.isConnected = true;
                this.showEmptyState();
            }
        } catch (error) {
            console.error('Error loading calendar events:', error);
            this.showErrorState('Failed to load calendar events. Please try again.');
        }
    }

    /**
     * Sync appointment to Google Calendar
     */
    async syncAppointment(appointmentId) {
        try {
            const response = await fetch('api/sync_appointment_calendar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ appointment_id: appointmentId })
            });

            const data = await response.json();

            if (data.success) {
                this.showMessage('Appointment synced to Google Calendar!', 'success');
                this.loadCalendarEvents(); // Reload events
                return true;
            } else if (data.needs_auth) {
                this.showNotConnectedState();
                return false;
            } else {
                this.showMessage(data.message || 'Failed to sync appointment', 'error');
                return false;
            }
        } catch (error) {
            console.error('Error syncing appointment:', error);
            this.showMessage('Failed to sync appointment', 'error');
            return false;
        }
    }

    /**
     * Connect to Google Calendar
     */
    connectCalendar() {
        // Redirect to Google OAuth
        window.location.href = 'api/google_calendar_auth.php';
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Connect button click
        this.container.addEventListener('click', (e) => {
            if (e.target.closest('.calendar-connect-btn') || 
                e.target.closest('.calendar-connect-main-btn')) {
                this.connectCalendar();
            }

            // Refresh button click
            if (e.target.closest('.calendar-refresh-btn')) {
                this.loadCalendarEvents();
            }
        });
    }

    /**
     * Render calendar events
     */
    renderEvents() {
        if (!this.events || this.events.length === 0) {
            this.renderCalendar();
            return;
        }

        this.renderCalendar();
    }

    /**
     * Render calendar grid
     */
    renderCalendar() {
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        // Get first day of month and number of days
        const firstDay = new Date(this.currentYear, this.currentMonth, 1);
        const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDayOfWeek = firstDay.getDay();

        // Get previous month's last days
        const prevMonthLastDay = new Date(this.currentYear, this.currentMonth, 0).getDate();
        const prevMonthDays = startingDayOfWeek;

        // Calculate total cells needed
        const totalCells = Math.ceil((daysInMonth + startingDayOfWeek) / 7) * 7;
        const nextMonthDays = totalCells - (daysInMonth + startingDayOfWeek);

        let calendarHTML = `
            <div class="calendar-widget-body">
                <div class="calendar-nav">
                    <div class="calendar-month-year">
                        ${monthNames[this.currentMonth]} ${this.currentYear}
                    </div>
                    <div class="calendar-nav-buttons">
                        <button class="calendar-nav-btn calendar-prev-btn" title="Previous month">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="calendar-nav-btn calendar-today-btn" title="Today">
                            <i class="fas fa-calendar-day"></i>
                        </button>
                        <button class="calendar-nav-btn calendar-next-btn" title="Next month">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <div class="calendar-legend">
                    <div class="calendar-legend-item">
                        <div class="calendar-legend-color vaccination"></div>
                        <span>Vaccination</span>
                    </div>
                    <div class="calendar-legend-item">
                        <div class="calendar-legend-color checkup"></div>
                        <span>Check-up</span>
                    </div>
                    <div class="calendar-legend-item">
                        <div class="calendar-legend-color other"></div>
                        <span>Other Appointments</span>
                    </div>
                </div>
                <div class="calendar-grid">
                    <div class="calendar-weekdays">
                        ${weekdays.map(day => `<div class="calendar-weekday">${day}</div>`).join('')}
                    </div>
                    <div class="calendar-days">
        `;

        // Previous month days
        for (let i = prevMonthDays - 1; i >= 0; i--) {
            const day = prevMonthLastDay - i;
            calendarHTML += this.renderDay(day, this.currentMonth - 1, this.currentYear, true);
        }

        // Current month days
        for (let day = 1; day <= daysInMonth; day++) {
            calendarHTML += this.renderDay(day, this.currentMonth, this.currentYear, false);
        }

        // Next month days
        for (let day = 1; day <= nextMonthDays; day++) {
            calendarHTML += this.renderDay(day, this.currentMonth + 1, this.currentYear, true);
        }

        calendarHTML += `
                    </div>
                </div>
            </div>
        `;

        this.container.innerHTML = calendarHTML;
        this.attachCalendarEventListeners();
    }

    /**
     * Render a single day cell
     */
    renderDay(day, month, year, isOtherMonth) {
        const date = new Date(year, month, day);
        const today = new Date();
        const isToday = date.toDateString() === today.toDateString();
        
        // Get events for this day
        const dayEvents = this.getEventsForDay(date);
        
        let classes = 'calendar-day';
        if (isOtherMonth) classes += ' other-month';
        if (isToday) classes += ' today';

        let eventsHTML = '';
        if (dayEvents.length > 0) {
            const maxVisible = 2;
            eventsHTML = '<div class="calendar-day-events">';
            
            dayEvents.slice(0, maxVisible).forEach(event => {
                const eventType = this.getEventType(event);
                const eventDate = new Date(event.start.dateTime || event.start.date);
                const time = this.formatTimeShort(eventDate);
                const summary = event.summary.length > 15 ? event.summary.substring(0, 15) + '...' : event.summary;
                eventsHTML += `
                    <div class="calendar-day-event ${eventType}" data-event-id="${event.id}" title="${this.escapeHtml(event.summary)} - ${time}">
                        ${time} ${this.escapeHtml(summary)}
                    </div>
                `;
            });
            
            if (dayEvents.length > maxVisible) {
                eventsHTML += `<div class="calendar-day-event-more">+${dayEvents.length - maxVisible} more</div>`;
            }
            
            eventsHTML += '</div>';
        }

        return `
            <div class="${classes}" data-date="${date.toISOString()}">
                <div class="calendar-day-number">${day}</div>
                ${eventsHTML}
            </div>
        `;
    }

    /**
     * Get events for a specific day
     */
    getEventsForDay(date) {
        return this.events.filter(event => {
            // Parse the datetime from the API response
            const eventDateStr = event.start.dateTime || event.start.date;
            const eventDate = new Date(eventDateStr);
            
            // Compare only the date part (year, month, day)
            return eventDate.getFullYear() === date.getFullYear() &&
                   eventDate.getMonth() === date.getMonth() &&
                   eventDate.getDate() === date.getDate();
        }).sort((a, b) => {
            // Sort events by time
            const timeA = new Date(a.start.dateTime || a.start.date);
            const timeB = new Date(b.start.dateTime || b.start.date);
            return timeA - timeB;
        });
    }

    /**
     * Get event type class
     */
    getEventType(event) {
        // Check if it's a vaccination event (from vaccinations table)
        if (event.type === 'vaccination') {
            return 'vaccination';
        }
        
        // Check appointment_type for appointments
        if (event.appointment_type) {
            if (event.appointment_type.toLowerCase().includes('vaccination')) {
                return 'vaccination';
            } else if (event.appointment_type.toLowerCase().includes('check-up') || 
                       event.appointment_type.toLowerCase().includes('checkup')) {
                return 'checkup';
            }
        }
        return 'other';
    }

    /**
     * Attach event listeners to calendar
     */
    attachCalendarEventListeners() {
        // Navigation buttons
        const prevBtn = this.container.querySelector('.calendar-prev-btn');
        const nextBtn = this.container.querySelector('.calendar-next-btn');
        const todayBtn = this.container.querySelector('.calendar-today-btn');

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                this.currentMonth--;
                if (this.currentMonth < 0) {
                    this.currentMonth = 11;
                    this.currentYear--;
                }
                this.renderCalendar();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                this.currentMonth++;
                if (this.currentMonth > 11) {
                    this.currentMonth = 0;
                    this.currentYear++;
                }
                this.renderCalendar();
            });
        }

        if (todayBtn) {
            todayBtn.addEventListener('click', () => {
                const today = new Date();
                this.currentMonth = today.getMonth();
                this.currentYear = today.getFullYear();
                this.renderCalendar();
            });
        }

        // Event clicks
        const eventElements = this.container.querySelectorAll('.calendar-day-event');
        eventElements.forEach(el => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                const eventId = el.dataset.eventId;
                const event = this.events.find(ev => ev.id == eventId);
                if (event) {
                    this.showEventPopup(event);
                }
            });
        });
    }

    /**
     * Show event details popup
     */
    showEventPopup(event) {
        // Parse the exact datetime from the API
        const startDateStr = event.start.dateTime || event.start.date;
        const startDate = new Date(startDateStr);
        
        const formattedDate = this.formatDate(startDate);
        const formattedTime = this.formatTime(startDate);
        
        const eventType = this.getEventType(event);
        const statusClass = event.status === 'confirmed' ? 'success' : 'warning';

        const popupHTML = `
            <div class="calendar-event-popup-overlay" id="eventPopupOverlay"></div>
            <div class="calendar-event-popup" id="eventPopup">
                <div class="calendar-event-popup-header">
                    <h3 class="calendar-event-popup-title">${this.escapeHtml(event.summary)}</h3>
                    <button class="calendar-event-popup-close" id="closeEventPopup">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="calendar-event-popup-body">
                    <div class="calendar-event-popup-detail">
                        <i class="fas fa-calendar"></i>
                        <div class="calendar-event-popup-detail-content">
                            <div class="calendar-event-popup-detail-label">Date</div>
                            <div class="calendar-event-popup-detail-value">${formattedDate}</div>
                        </div>
                    </div>
                    <div class="calendar-event-popup-detail">
                        <i class="fas fa-clock"></i>
                        <div class="calendar-event-popup-detail-content">
                            <div class="calendar-event-popup-detail-label">Time</div>
                            <div class="calendar-event-popup-detail-value">${formattedTime}</div>
                        </div>
                    </div>
                    ${event.appointment_type ? `
                        <div class="calendar-event-popup-detail">
                            <i class="fas fa-tag"></i>
                            <div class="calendar-event-popup-detail-content">
                                <div class="calendar-event-popup-detail-label">Type</div>
                                <div class="calendar-event-popup-detail-value">
                                    <span class="calendar-event-badge ${eventType}">${event.appointment_type}</span>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                    ${event.status ? `
                        <div class="calendar-event-popup-detail">
                            <i class="fas fa-info-circle"></i>
                            <div class="calendar-event-popup-detail-content">
                                <div class="calendar-event-popup-detail-label">Status</div>
                                <div class="calendar-event-popup-detail-value">
                                    <span class="calendar-status-badge ${statusClass}">${event.status}</span>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                    ${event.location ? `
                        <div class="calendar-event-popup-detail">
                            <i class="fas fa-map-marker-alt"></i>
                            <div class="calendar-event-popup-detail-content">
                                <div class="calendar-event-popup-detail-label">Location</div>
                                <div class="calendar-event-popup-detail-value">${this.escapeHtml(event.location)}</div>
                            </div>
                        </div>
                    ` : ''}
                    ${event.description ? `
                        <div class="calendar-event-popup-detail">
                            <i class="fas fa-align-left"></i>
                            <div class="calendar-event-popup-detail-content">
                                <div class="calendar-event-popup-detail-label">Notes</div>
                                <div class="calendar-event-popup-detail-value">${this.escapeHtml(event.description)}</div>
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', popupHTML);

        // Close popup handlers
        const closeBtn = document.getElementById('closeEventPopup');
        const overlay = document.getElementById('eventPopupOverlay');

        const closePopup = () => {
            document.getElementById('eventPopup').remove();
            document.getElementById('eventPopupOverlay').remove();
        };

        closeBtn.addEventListener('click', closePopup);
        overlay.addEventListener('click', closePopup);
    }

    /**
     * Show loading state
     */
    showLoadingState() {
        this.container.innerHTML = `
            <div class="calendar-widget-body">
                <div class="calendar-loading">
                    <div class="spinner"></div>
                    <p>Loading calendar events...</p>
                </div>
            </div>
        `;
    }

    /**
     * Show not connected state
     */
    showNotConnectedState() {
        this.container.innerHTML = `
            <div class="calendar-widget-body">
                <div class="calendar-not-connected">
                    <i class="fas fa-calendar-times"></i>
                    <h5>Google Calendar Not Connected</h5>
                    <p>Connect your Google Calendar to sync appointments and view upcoming events.</p>
                    <button class="calendar-connect-main-btn">
                        <i class="fab fa-google"></i>
                        Connect Google Calendar
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Show empty state - still show calendar grid
     */
    showEmptyState() {
        // Show the calendar grid even when there are no events
        this.renderCalendar();
    }

    /**
     * Show error state
     */
    showErrorState(message) {
        this.container.innerHTML = `
            <div class="calendar-widget-body">
                <div class="calendar-message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>${message}</span>
                </div>
                <button class="calendar-refresh-btn" style="margin: 0 auto; display: block;">
                    <i class="fas fa-sync-alt"></i> Retry
                </button>
            </div>
        `;
    }

    /**
     * Show message
     */
    showMessage(message, type = 'success') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `calendar-message ${type}`;
        messageDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;

        const body = this.container.querySelector('.calendar-widget-body');
        if (body) {
            body.insertBefore(messageDiv, body.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }
    }

    /**
     * Format date
     */
    formatDate(date) {
        const options = { month: 'short', day: 'numeric', year: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }

    /**
     * Format time - uses exact time from database
     */
    formatTime(date) {
        // Ensure we're working with a Date object
        if (!(date instanceof Date)) {
            date = new Date(date);
        }
        
        const hours = date.getHours();
        const minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const displayHours = hours % 12 || 12;
        
        return `${displayHours}:${minutes.toString().padStart(2, '0')} ${ampm}`;
    }

    /**
     * Format time for calendar grid (shorter format)
     */
    formatTimeShort(date) {
        // Ensure we're working with a Date object
        if (!(date instanceof Date)) {
            date = new Date(date);
        }
        
        const hours = date.getHours();
        const minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const displayHours = hours % 12 || 12;
        
        // Only show minutes if not on the hour
        if (minutes === 0) {
            return `${displayHours}${ampm}`;
        }
        
        return `${displayHours}:${minutes.toString().padStart(2, '0')}${ampm}`;
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

/**
 * Add sync button to appointment items
 */
function addSyncButtonsToAppointments() {
    const appointmentRows = document.querySelectorAll('.appointment-row');
    
    appointmentRows.forEach(row => {
        const appointmentId = row.dataset.appointmentId;
        if (!appointmentId) return;

        const actionsCell = row.querySelector('.appointment-actions');
        if (!actionsCell) return;

        // Check if sync button already exists
        if (actionsCell.querySelector('.calendar-sync-btn')) return;

        const syncBtn = document.createElement('button');
        syncBtn.className = 'calendar-sync-btn';
        syncBtn.innerHTML = '<i class="fas fa-calendar-plus"></i> Add to Calendar';
        syncBtn.onclick = async function() {
            syncBtn.classList.add('syncing');
            syncBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Syncing...';
            
            const widget = window.calendarWidget;
            if (widget) {
                const success = await widget.syncAppointment(appointmentId);
                
                if (success) {
                    syncBtn.innerHTML = '<i class="fas fa-check"></i> Synced';
                    syncBtn.disabled = true;
                } else {
                    syncBtn.classList.remove('syncing');
                    syncBtn.innerHTML = '<i class="fas fa-calendar-plus"></i> Add to Calendar';
                }
            }
        };

        actionsCell.appendChild(syncBtn);
    });
}

/**
 * Sync all appointments and vaccinations to Google Calendar
 */
async function syncAllAppointments() {
    const syncBtn = document.getElementById('syncAllAppointmentsBtn');
    if (!syncBtn) return;

    // Show loading state
    syncBtn.classList.add('syncing');
    syncBtn.disabled = true;
    const originalHTML = syncBtn.innerHTML;
    syncBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Syncing...';

    try {
        // Sync appointments
        const appointmentsResponse = await fetch('api/sync_all_appointments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const appointmentsData = await appointmentsResponse.json();

        // Sync vaccinations
        const vaccinationsResponse = await fetch('api/sync_all_vaccinations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const vaccinationsData = await vaccinationsResponse.json();

        // Check if both succeeded
        if (appointmentsData.success && vaccinationsData.success) {
            // Update last sync timestamp
            localStorage.setItem('calendar_last_sync', Date.now().toString());
            
            // Show success message
            syncBtn.innerHTML = '<i class="fas fa-check"></i> Synced!';
            
            // Calculate totals
            const totalSynced = (appointmentsData.synced || 0) + (vaccinationsData.synced || 0);
            const totalSkipped = (appointmentsData.skipped || 0) + (vaccinationsData.skipped || 0);
            const appointmentsSynced = appointmentsData.synced || 0;
            const vaccinationsSynced = vaccinationsData.synced || 0;
            
            // Build message
            let message = '';
            if (totalSynced > 0) {
                const parts = [];
                if (appointmentsSynced > 0) {
                    parts.push(`${appointmentsSynced} appointment${appointmentsSynced > 1 ? 's' : ''}`);
                }
                if (vaccinationsSynced > 0) {
                    parts.push(`${vaccinationsSynced} vaccination${vaccinationsSynced > 1 ? 's' : ''}`);
                }
                message = `Successfully synced ${parts.join(' and ')} to Google Calendar!`;
            } else {
                message = `All events already synced (${totalSkipped} skipped)`;
            }
            
            showSyncNotification(message, 'success');
            
            // Reload calendar events
            if (window.calendarWidget) {
                window.calendarWidget.loadCalendarEvents();
            }
            
            // Reset button after 3 seconds
            setTimeout(() => {
                syncBtn.innerHTML = originalHTML;
                syncBtn.classList.remove('syncing');
                syncBtn.disabled = false;
            }, 3000);
        } else {
            // Handle partial failure
            let errorMessage = 'Failed to sync some events. ';
            if (!appointmentsData.success) {
                errorMessage += 'Appointments: ' + (appointmentsData.message || 'Unknown error') + '. ';
            }
            if (!vaccinationsData.success) {
                errorMessage += 'Vaccinations: ' + (vaccinationsData.message || 'Unknown error');
            }
            throw new Error(errorMessage);
        }
    } catch (error) {
        console.error('Error syncing events:', error);
        showSyncNotification(error.message || 'Failed to sync events. Please try again.', 'error');
        
        // Reset button
        syncBtn.innerHTML = originalHTML;
        syncBtn.classList.remove('syncing');
        syncBtn.disabled = false;
    }
}

/**
 * Show sync notification modal
 */
function showSyncNotification(message, type = 'success') {
    // Remove existing modal if any
    const existingModal = document.getElementById('syncNotificationModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Create modal HTML using success-error_messages.css styles
    const modalHTML = `
        <div class="message-modal message-modal-${type} show" id="syncNotificationModal">
            <div class="message-modal-content">
                <div class="message-modal-header">
                    <button type="button" class="message-modal-close" onclick="hideSyncModal()">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="message-modal-icon">
                        <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'}"></i>
                    </div>
                    <h3 class="message-modal-title">${type === 'success' ? 'Sync Successful!' : 'Sync Failed'}</h3>
                </div>
                <div class="message-modal-body">
                    <p class="message-modal-message">
                        <i class="fas fa-${type === 'success' ? 'sync-alt' : 'exclamation-circle'} me-2"></i>
                        ${message}
                    </p>
                    <div class="message-modal-actions">
                        <button type="button" class="message-modal-btn message-modal-btn-primary" onclick="hideSyncModal()">
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
        hideSyncModal();
    }, 5000);
}

/**
 * Hide sync notification modal
 */
function hideSyncModal() {
    const modal = document.getElementById('syncNotificationModal');
    if (modal) {
        modal.classList.add('hiding');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('syncNotificationModal');
    if (modal && e.target === modal) {
        hideSyncModal();
    }
});

/**
 * Initialize calendar widget on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    const calendarContainer = document.getElementById('googleCalendarWidget');
    
    if (calendarContainer) {
        // Always initialize the widget to show appointments from database
        // Google Calendar sync is optional
        window.calendarWidget = new GoogleCalendarWidget('googleCalendarWidget');
        
        // Add sync buttons to existing appointments
        addSyncButtonsToAppointments();
    }

    // Add event listener for manual sync all button
    const syncAllBtn = document.getElementById('syncAllAppointmentsBtn');
    if (syncAllBtn) {
        syncAllBtn.addEventListener('click', syncAllAppointments);
    }
    
    // Add event listener for disconnect button
    const disconnectBtn = document.getElementById('disconnectCalendarBtn');
    if (disconnectBtn) {
        disconnectBtn.addEventListener('click', disconnectGoogleCalendar);
    }
});

/**
 * Disconnect Google Calendar
 */
async function disconnectGoogleCalendar() {
    // Confirm before disconnecting
    if (!confirm('Are you sure you want to disconnect your Google Calendar? Your synced events will remain in Google Calendar, but new events will not sync automatically.')) {
        return;
    }

    const disconnectBtn = document.getElementById('disconnectCalendarBtn');
    if (!disconnectBtn) return;

    // Show loading state
    disconnectBtn.classList.add('disconnecting');
    disconnectBtn.disabled = true;
    const originalHTML = disconnectBtn.innerHTML;
    disconnectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Disconnecting...';

    try {
        const response = await fetch('api/disconnect_google_calendar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            showSyncNotification('Google Calendar disconnected successfully. Refreshing page...', 'success');
            
            // Reload page after 2 seconds
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            throw new Error(data.message || 'Failed to disconnect');
        }
    } catch (error) {
        console.error('Error disconnecting calendar:', error);
        showSyncNotification('Failed to disconnect Google Calendar. Please try again.', 'error');
        
        // Reset button
        disconnectBtn.innerHTML = originalHTML;
        disconnectBtn.classList.remove('disconnecting');
        disconnectBtn.disabled = false;
    }
}
