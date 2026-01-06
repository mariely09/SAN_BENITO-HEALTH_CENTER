CREATE DATABASE IF NOT EXISTS san_benito_health;
USE san_benito_health;

-- ========================
-- USERS
-- ========================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  fullname VARCHAR(100) NOT NULL,
  email VARCHAR(100),
  contact_number VARCHAR(20) NOT NULL,
  role ENUM('admin','worker','resident') NOT NULL,
  user_type VARCHAR(50) DEFAULT 'resident',
  status ENUM('pending','approved') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reset_otp VARCHAR(6),
  reset_otp_expiry DATETIME
);


-- ========================
-- USERS GOOGLE TOKENS
-- ========================
CREATE TABLE user_google_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  access_token TEXT NOT NULL,
  refresh_token TEXT DEFAULT NULL,
  token_expiry DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================
-- SYSTEM AUDIT LOG
-- ========================
CREATE TABLE system_audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(100) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ========================
-- APPOINTMENTS
-- ========================
CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  fullname VARCHAR(150) NOT NULL,
  appointment_type ENUM('Vaccination','Check-up') NOT NULL,
  preferred_datetime DATETIME NOT NULL,
  notes TEXT,
  status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================
-- APPOINTMENT CALENDAR SYNC
-- ========================
CREATE TABLE appointment_calendar_sync (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT NOT NULL,
  user_id INT NOT NULL,
  google_event_id VARCHAR(255) NOT NULL,
  last_synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (appointment_id, user_id),
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================
-- BABIES
-- ========================
CREATE TABLE babies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  date_of_birth DATE NOT NULL,
  parent_guardian_name VARCHAR(100) NOT NULL,
  contact_number VARCHAR(20),
  address VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================
-- VACCINATIONS
-- ========================
CREATE TABLE vaccinations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  baby_id INT NOT NULL,
  vaccine_type VARCHAR(100) NOT NULL,
  schedule_date DATETIME NOT NULL,
  status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  notes TEXT,
  administered_by INT,
  administered_date DATETIME,
  FOREIGN KEY (baby_id) REFERENCES babies(id) ON DELETE CASCADE,
  FOREIGN KEY (administered_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ========================
-- VACCINATION CALENDAR SYNC
-- ========================
CREATE TABLE vaccination_calendar_sync (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vaccination_id INT NOT NULL,
  user_id INT NOT NULL,
  google_event_id VARCHAR(255) NOT NULL,
  last_synced_at DATETIME NOT NULL,
  UNIQUE (vaccination_id, user_id),
  FOREIGN KEY (vaccination_id) REFERENCES vaccinations(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================
-- MEDICINES
-- ========================
CREATE TABLE medicines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  medicine_name VARCHAR(100) NOT NULL,
  dosage VARCHAR(100),
  quantity INT NOT NULL,
  expiry_date DATE NOT NULL,
  batch_number VARCHAR(50) NOT NULL,
  low_stock_threshold INT DEFAULT 10,
  date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================
-- BARANGAY RESIDENTS
-- ========================
CREATE TABLE barangay_residents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100),
  age INT NOT NULL,
  gender ENUM('Male','Female') NOT NULL,
  birthday DATE NOT NULL,
  purok VARCHAR(50) NOT NULL,
  occupation VARCHAR(100),
  education VARCHAR(100),
  is_senior BOOLEAN DEFAULT 0,
  is_pwd BOOLEAN DEFAULT 0,
  family_planning ENUM('Yes','No') DEFAULT 'No',
  has_electricity BOOLEAN DEFAULT 0,
  has_poso BOOLEAN DEFAULT 0,
  has_nawasa BOOLEAN DEFAULT 0,
  has_cr BOOLEAN DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ========================
-- ARCHIVED APPOINMENTS
-- ========================
CREATE TABLE archived_appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  original_id INT NOT NULL,
  user_id INT NOT NULL,
  fullname VARCHAR(255) NOT NULL,
  contact_number VARCHAR(20) DEFAULT NULL,
  appointment_type enum('Check-up','Vaccination') NOT NULL,
  preferred_datetime DATETIME NOT NULL,
  status enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  notes text DEFAULT NULL,
  cancellation_reason text DEFAULT NULL,
  cancelled_by_role varchar(20) DEFAULT NULL,
  created_at timestamp NOT NULL DEFAULT current_timestamp(),
  archived_at timestamp NOT NULL DEFAULT current_timestamp(),
  archived_by int(11) DEFAULT NULL,
  archive_reason varchar(255) DEFAULT NULL,

  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (archived_by) REFERENCES users(id)
);


-- ========================
-- ARCHIVED BABIES
-- ========================
CREATE TABLE archived_babies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  original_id INT NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  date_of_birth DATE NOT NULL,
  parent_guardian_name VARCHAR(255) NOT NULL,
  contact_number VARCHAR(20),
  address TEXT,
  gender ENUM('Male','Female'),
  birth_weight DECIMAL(5,2),
  birth_height DECIMAL(5,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  archived_by INT,
  archive_reason VARCHAR(255),
  FOREIGN KEY (archived_by) REFERENCES users(id)
);

-- ========================
-- ARCHIVED MEDICINES
-- ========================
CREATE TABLE archived_medicines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  original_id INT NOT NULL,
  medicine_name VARCHAR(255) NOT NULL,
  dosage VARCHAR(100),
  quantity INT NOT NULL,
  expiry_date DATE NOT NULL,
  batch_number VARCHAR(100) NOT NULL,
  low_stock_threshold INT DEFAULT 10,
  date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  archived_by INT,
  archive_reason VARCHAR(255),
  FOREIGN KEY (archived_by) REFERENCES users(id)
);

-- ========================
-- ARCHIVED RESIDENTS
-- ========================
CREATE TABLE archived_residents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  original_id INT NOT NULL,
  first_name VARCHAR(255) NOT NULL,
  last_name VARCHAR(255) NOT NULL,
  middle_name VARCHAR(255),
  age INT NOT NULL,
  gender ENUM('Male','Female') NOT NULL,
  birthday DATE NOT NULL,
  purok VARCHAR(100),
  occupation VARCHAR(255),
  education VARCHAR(255),
  is_senior BOOLEAN DEFAULT 0,
  is_pwd BOOLEAN DEFAULT 0,
  family_planning ENUM('Yes','No') DEFAULT 'No',
  has_electricity BOOLEAN DEFAULT 0,
  has_poso BOOLEAN DEFAULT 0,
  has_nawasa BOOLEAN DEFAULT 0,
  has_cr BOOLEAN DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  archived_by INT,
  archive_reason VARCHAR(255),
  FOREIGN KEY (archived_by) REFERENCES users(id)
);

-- ========================
-- ARCHIVED USERS
-- ========================
CREATE TABLE archived_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  original_id INT NOT NULL,
  username VARCHAR(50) NOT NULL,
  fullname VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  role ENUM('admin','worker','resident') NOT NULL,
  status VARCHAR(20),
  contact_number VARCHAR(20),
  address TEXT,
  hire_date DATE,
  departure_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  archived_by INT,
  archive_reason VARCHAR(255),
  FOREIGN KEY (archived_by) REFERENCES users(id)
);

-- ========================
-- ARCHIVED VACCINATIONS
-- ========================
CREATE TABLE archived_vaccinations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  original_id INT NOT NULL,
  baby_id INT NOT NULL,
  vaccine_type VARCHAR(100) NOT NULL,
  schedule_date DATETIME NOT NULL,
  status ENUM('pending','confirmed','completed','cancelled'),
  notes TEXT,
  administered_by INT,
  administered_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  archived_by INT,
  archive_reason VARCHAR(255),
  FOREIGN KEY (archived_by) REFERENCES users(id)
);
