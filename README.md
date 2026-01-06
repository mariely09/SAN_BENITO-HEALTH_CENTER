# San Benito Health Center Management System

A comprehensive PHP-based health management system for the Barangay Health Center located in San Benito, Masapang, Victoria, Laguna. This system provides complete healthcare service management including resident information, medicine inventory, vaccination scheduling, and appointment management.

## Features

### Multi-Role User System
- **Admin**: Full system access, user management, and system configuration
- **Worker**: Healthcare staff with access to patient records and scheduling
- **Resident**: Community members can schedule appointments and view their health records
- Secure authentication with role-based access control
- User approval system for new registrations

### Comprehensive Resident Management
- Complete resident database with personal information
- Health priority group tracking (Senior Citizens, PWD, Family Planning participants)
- Utilities and facilities tracking (electricity, water, sanitation)
- Advanced filtering by purok, age groups, and health categories
- Resident archiving system for data retention
- Pagination for large datasets

### Advanced Medicine Inventory Management
- Complete medicine inventory with dosage tracking
- Batch number management and expiry date monitoring
- Automated low stock alerts (threshold: 10 units)
- Medicine archiving system instead of permanent deletion
- Real-time inventory status with color-coded alerts
- Advanced filtering by stock status and expiry dates

### Baby/Child Health Records
- Comprehensive baby registration system
- Parent/guardian linkage for security
- Age-based vaccination recommendations
- Growth and development tracking
- Vaccination history management

### Smart Vaccination Management
- Age-appropriate vaccine scheduling system
- Automated vaccine recommendations based on baby's age
- Real-time appointment conflict detection
- Vaccination status tracking (pending, confirmed, completed)
- Integration with appointment system

### Intelligent Appointment System
- Dual appointment types: Check-ups and Vaccinations
- Real-time time slot availability checking
- 15-minute interval scheduling (8:00 AM - 5:00 PM)
- Automatic conflict prevention
- Email notifications to healthcare workers
- Appointment cancellation system
- Status tracking (pending, approved, confirmed, completed)

### Google Calendar Integration
- Automatic synchronization of appointments to Google Calendar
- Multi-user calendar sync for healthcare workers
- Real-time calendar updates
- OAuth 2.0 secure authentication

### Interactive Dashboard Features
- **Resident Dashboard**: 
  - Personal appointment management
  - Baby vaccination schedules
  - Daily health tips via API integration
  - Real-time weather information
  - Google Calendar widget
- **Admin/Worker Dashboard**: 
  - System statistics and analytics
  - Quick access to all management functions
  - Real-time notifications

### Advanced Reporting & Analytics
- Comprehensive appointment reports
- Medicine inventory reports with PDF generation
- Resident demographics and statistics
- Vaccination coverage reports
- Filterable and exportable data

### Archive Management System
- Soft delete functionality for all major entities
- Archive restoration capabilities
- Data retention for historical records
- Separate archive views for different data types

### API Integrations
- **Health Tips API**: Daily motivational health quotes
- **Weather API**: Real-time weather information for Manila
- **Google Calendar API**: Appointment synchronization
- **Email API**: Automated notifications

### Modern UI/UX Features
- Responsive Bootstrap 5 design
- Interactive modals and forms
- Real-time form validation
- Loading states and progress indicators
- Success/error message system
- Mobile-friendly interface
- Smooth animations and transitions

## Installation

1. **Server Requirements**:
   - PHP 7.4 or higher
   - MySQL 5.7 or higher
   - Apache/Nginx web server
   - cURL extension enabled
   - OpenSSL extension enabled

2. **Setup Process**:
   ```bash
   # Clone the repository
   git clone [repository-url]
   cd SAN_BENITO_SYSTEM
   
   # Create MySQL database
   mysql -u root -p
   CREATE DATABASE san_benito_health;
   
   # Import database structure
   mysql -u root -p san_benito_health < sql/database.sql
   ```

3. **Configuration**:
   - Update database credentials in `config/database.php`
   - Configure email settings in `config/email.php`
   - Set up Google Calendar API credentials (optional)
   - Configure API keys for weather and health tips

4. **Access the System**:
   - Navigate to `http://your-domain/index.php`
   - Use default admin credentials to get started

## Default Credentials

### Admin Account
- **Username**: admin
- **Password**: admin123
- **Role**: Administrator (full system access)

### Test Worker Account
- **Username**: worker
- **Password**: worker123
- **Role**: Healthcare Worker

## System Architecture

```
SAN_BENITO_SYSTEM/
├── api/                    # API endpoints for AJAX requests
├── assets/                 # CSS, JS, and image files
├── config/                 # Configuration files
├── docs/                   # Documentation files
├── emails/                 # Email templates
├── includes/               # Reusable components
├── sql/                    # Database scripts
├── vendor/                 # Third-party libraries
├── *.php                   # Main application files
└── README.md              # This file
```

## Key Technologies

- **Backend**: PHP 7.4+, MySQL
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework**: Bootstrap 5
- **Icons**: Font Awesome 6
- **APIs**: Google Calendar, OpenWeatherMap, API Ninjas
- **Libraries**: PHPMailer, Google API Client

## Security Features

- SQL injection prevention with prepared statements
- XSS protection with input sanitization
- CSRF protection for forms
- Role-based access control
- Secure session management
- Input validation and sanitization

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is developed for the San Benito Barangay Health Center. All rights reserved.

## Support

For technical support or feature requests, please contact the development team or create an issue in the repository.

---

**Developed for San Benito Barangay Health Center**  
*Improving community healthcare through technology* 
