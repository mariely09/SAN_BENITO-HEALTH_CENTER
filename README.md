# San Benito Health Center Management System

A PHP-based CRUD system for the Barangay Health Center Inventory and Vaccination Management System located in San Benito, Masapang, Victoria, Laguna.

## Features

### Interactive Landing Page
- Beautiful landing page with information about Barangay San Benito
- Services overview and community goals
- Direct access to login system
- Responsive design with smooth animations

### Medicine Inventory Management
- Add, view, update, and delete medicine records
- Track medicine quantities and expiry dates
- Low stock alerts for medicines that need restocking

### Baby/Child Records Management
- Add, view, update, and delete baby/child records
- Track basic information including name, date of birth, parent/guardian details

### Vaccination Management
- Schedule and track vaccinations for babies/children
- Filter vaccinations by status (pending, completed)
- View upcoming vaccinations and overdue vaccinations

### User Management
- Admin and Worker user roles
- Admin can approve worker registrations
- Secure login system (without password hashing as per requirement)

## Installation

1. Clone the repository to your web server directory
2. Create a MySQL database named `san_benito_health`
3. Import the `database.sql` file to create the necessary tables
4. Configure the database connection in `config/database.php` if needed
5. Access the system through your web browser at `landing.php` (or root directory)

## Default Credentials

- Admin Account:
  - Username: admin
  - Password: admin123

## Directory Structure

- `config/` - Configuration files
- `includes/` - Reusable components (header, footer)
- `*.php` - Main application files

## Requirements

- PHP 7.0 or higher
- MySQL 5.6 or higher
- Web server (Apache, Nginx, etc.)

## Screenshots

[To be added]

## Credits

Developed for San Benito Barangay Health Center by [Your Name/Organization] 