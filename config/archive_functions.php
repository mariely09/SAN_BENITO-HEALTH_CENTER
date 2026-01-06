<?php
require_once 'database.php';
require_once 'session.php';

/**
 * Archive a baby record and its vaccinations
 */
function archiveBaby($baby_id, $user_id) {
    global $conn;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get baby record
        $query = "SELECT * FROM babies WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $baby_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $baby = mysqli_fetch_assoc($result);
        
        if (!$baby) {
            throw new Exception("Baby record not found");
        }
        
        // Archive baby record
        $query = "INSERT INTO archived_babies (original_id, full_name, date_of_birth, parent_guardian_name, contact_number, created_at, archived_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "isssssi", 
            $baby['id'],
            $baby['full_name'],
            $baby['date_of_birth'],
            $baby['parent_guardian_name'],
            $baby['contact_number'],
            $baby['created_at'],
            $user_id
        );
        mysqli_stmt_execute($stmt);
        
        // Archive associated vaccinations
        $query = "SELECT * FROM vaccinations WHERE baby_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $baby_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while ($vaccination = mysqli_fetch_assoc($result)) {
            $query = "INSERT INTO archived_vaccinations (original_id, baby_id, vaccine_type, schedule_date, status, notes, administered_by, administered_date, created_at, archived_by) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "iisssssssi", 
                $vaccination['id'],
                $vaccination['baby_id'],
                $vaccination['vaccine_type'],
                $vaccination['schedule_date'],
                $vaccination['status'],
                $vaccination['notes'],
                $vaccination['administered_by'],
                $vaccination['administered_date'],
                $vaccination['created_at'],
                $user_id
            );
            mysqli_stmt_execute($stmt);
        }
        
        // Delete original records
        $query = "DELETE FROM vaccinations WHERE baby_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $baby_id);
        mysqli_stmt_execute($stmt);
        
        $query = "DELETE FROM babies WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $baby_id);
        mysqli_stmt_execute($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * Archive a vaccination record
 */
function archiveVaccination($vaccination_id, $user_id) {
    global $conn;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get vaccination record
        $query = "SELECT * FROM vaccinations WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $vaccination_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $vaccination = mysqli_fetch_assoc($result);
        
        if (!$vaccination) {
            throw new Exception("Vaccination record not found");
        }
        
        // Archive vaccination record
        $query = "INSERT INTO archived_vaccinations (original_id, baby_id, vaccine_type, schedule_date, status, notes, administered_by, administered_date, created_at, archived_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "iisssssssi", 
            $vaccination['id'],
            $vaccination['baby_id'],
            $vaccination['vaccine_type'],
            $vaccination['schedule_date'],
            $vaccination['status'],
            $vaccination['notes'],
            $vaccination['administered_by'],
            $vaccination['administered_date'],
            $vaccination['created_at'],
            $user_id
        );
        mysqli_stmt_execute($stmt);
        
        // Delete original record
        $query = "DELETE FROM vaccinations WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $vaccination_id);
        mysqli_stmt_execute($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * Restore a baby record from archive
 */
function restoreBaby($archive_id) {
    global $conn;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get archived baby record
        $query = "SELECT * FROM archived_babies WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $baby = mysqli_fetch_assoc($result);
        
        if (!$baby) {
            throw new Exception("Archived baby record not found");
        }
        
        // Check if original ID exists in babies table
        $check_query = "SELECT id FROM babies WHERE id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $baby['original_id']);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->num_rows > 0;
        
        // Insert into babies table
        if ($exists) {
            // If original ID exists, create new record
            $query = "INSERT INTO babies (full_name, date_of_birth, parent_guardian_name, contact_number, created_at) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssss", 
                $baby['full_name'],
                $baby['date_of_birth'],
                $baby['parent_guardian_name'],
                $baby['contact_number'],
                $baby['created_at']
            );
        } else {
            // If original ID is available, use it
            $query = "INSERT INTO babies (id, full_name, date_of_birth, parent_guardian_name, contact_number, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "isssss", 
                $baby['original_id'],
                $baby['full_name'],
                $baby['date_of_birth'],
                $baby['parent_guardian_name'],
                $baby['contact_number'],
                $baby['created_at']
            );
        }
        mysqli_stmt_execute($stmt);
        
        // Delete from archived_babies
        $query = "DELETE FROM archived_babies WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * Restore a vaccination record from archive
 */
function restoreVaccination($archive_id) {
    global $conn;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get archived vaccination record
        $query = "SELECT * FROM archived_vaccinations WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $vaccination = mysqli_fetch_assoc($result);
        
        if (!$vaccination) {
            throw new Exception("Archived vaccination record not found");
        }
        
        // Check if baby exists
        $check_query = "SELECT id FROM babies WHERE id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $vaccination['baby_id']);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_get_result($stmt)->num_rows == 0) {
            throw new Exception("Cannot restore vaccination: Baby record does not exist");
        }
        
        // Check if original ID exists in vaccinations table
        $check_query = "SELECT id FROM vaccinations WHERE id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $vaccination['original_id']);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->num_rows > 0;
        
        // Insert into vaccinations table
        if ($exists) {
            // If original ID exists, create new record
            $query = "INSERT INTO vaccinations (baby_id, vaccine_type, schedule_date, status, notes, administered_by, administered_date) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "issssss", 
                $vaccination['baby_id'],
                $vaccination['vaccine_type'],
                $vaccination['schedule_date'],
                $vaccination['status'],
                $vaccination['notes'],
                $vaccination['administered_by'],
                $vaccination['administered_date']
            );
        } else {
            // If original ID is available, use it
            $query = "INSERT INTO vaccinations (id, baby_id, vaccine_type, schedule_date, status, notes, administered_by, administered_date) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "iissssss", 
                $vaccination['original_id'],
                $vaccination['baby_id'],
                $vaccination['vaccine_type'],
                $vaccination['schedule_date'],
                $vaccination['status'],
                $vaccination['notes'],
                $vaccination['administered_by'],
                $vaccination['administered_date']
            );
        }
        mysqli_stmt_execute($stmt);
        
        // Delete from archived_vaccinations
        $query = "DELETE FROM archived_vaccinations WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        
        // Commit transaction
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * Archive cancelled appointments that are older than 30 days
 */
function archiveOldCancelledAppointments($user_id = null) {
    global $conn;
    
    // Use system user ID if not provided
    if ($user_id === null) {
        $user_id = 1; // System user ID
    }
    
    $archived_count = 0;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Find cancelled appointments older than 30 days
        // Since we don't have updated_at, we'll use created_at as a fallback
        // In a real system, you'd want to add an updated_at column to track when status changes
        $query = "SELECT a.*, v.id as vaccination_id, v.baby_id, v.vaccine_type, v.schedule_date, v.status as vaccination_status, v.notes, v.administered_by, v.administered_date, v.created_at as vaccination_created_at
                  FROM appointments a 
                  LEFT JOIN vaccinations v ON (a.appointment_type = 'Vaccination' 
                                              AND a.fullname LIKE CONCAT('%', (SELECT full_name FROM babies WHERE id = v.baby_id), '%')
                                              AND DATE(a.preferred_datetime) = v.schedule_date)
                  WHERE a.status = 'cancelled' 
                  AND a.appointment_type = 'Vaccination'
                  AND DATEDIFF(CURDATE(), a.created_at) >= 30";
        
        $result = mysqli_query($conn, $query);
        
        if (!$result) {
            throw new Exception("Query failed: " . mysqli_error($conn));
        }
        
        while ($row = mysqli_fetch_assoc($result)) {
            // Archive the vaccination record if it exists
            if ($row['vaccination_id']) {
                $query = "INSERT INTO archived_vaccinations (original_id, baby_id, vaccine_type, schedule_date, status, notes, administered_by, administered_date, created_at, archived_by, archive_reason) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                $archive_reason = "Auto-archived: Cancelled appointment older than 30 days";
                mysqli_stmt_bind_param($stmt, "iisssssssis", 
                    $row['vaccination_id'],
                    $row['baby_id'],
                    $row['vaccine_type'],
                    $row['schedule_date'],
                    $row['vaccination_status'],
                    $row['notes'],
                    $row['administered_by'],
                    $row['administered_date'],
                    $row['vaccination_created_at'],
                    $user_id,
                    $archive_reason
                );
                mysqli_stmt_execute($stmt);
                
                // Delete the vaccination record
                $query = "DELETE FROM vaccinations WHERE id = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "i", $row['vaccination_id']);
                mysqli_stmt_execute($stmt);
            }
            
            // Archive the appointment record (if we have an archived_appointments table)
            // For now, we'll just delete the appointment since we don't have an archived_appointments table
            $query = "DELETE FROM appointments WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $row['id']);
            mysqli_stmt_execute($stmt);
            
            $archived_count++;
        }
        
        // Commit transaction
        mysqli_commit($conn);
        return $archived_count;
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * Restore a medicine record from archive
 */
function restoreMedicine($archive_id) {
    global $conn;
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get archived medicine record
        $query = "SELECT * FROM archived_medicines WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $medicine = mysqli_fetch_assoc($result);
        
        if (!$medicine) {
            throw new Exception("Archived medicine record not found");
        }
        
        // Check if original ID exists in medicines table
        $check_query = "SELECT id FROM medicines WHERE id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $medicine['original_id']);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->num_rows > 0;
        
        // Insert into medicines table
        if ($exists) {
            // If original ID exists, create new record
            $query = "INSERT INTO medicines (medicine_name, dosage, quantity, expiry_date, batch_number, low_stock_threshold) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssissi", 
                $medicine['medicine_name'],
                $medicine['dosage'],
                $medicine['quantity'],
                $medicine['expiry_date'],
                $medicine['batch_number'],
                $medicine['low_stock_threshold']
            );
        } else {
            // If original ID is available, use it
            $query = "INSERT INTO medicines (id, medicine_name, dosage, quantity, expiry_date, batch_number, low_stock_threshold) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "isssssi", 
                $medicine['original_id'],
                $medicine['medicine_name'],
                $medicine['dosage'],
                $medicine['quantity'],
                $medicine['expiry_date'],
                $medicine['batch_number'],
                $medicine['low_stock_threshold']
            );
        }
        mysqli_stmt_execute($stmt);
        
        // Delete from archived_medicines
        $query = "DELETE FROM archived_medicines WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * Restore a user record from archive
 */
function restoreUser($archive_id) {
    global $conn;
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get archived user record
        $query = "SELECT * FROM archived_users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        if (!$user) {
            throw new Exception("Archived user record not found");
        }
        
        // Check if username already exists
        $check_query = "SELECT id FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "s", $user['username']);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_get_result($stmt)->num_rows > 0) {
            throw new Exception("Cannot restore user: Username already exists");
        }
        
        // Check if original ID exists in users table
        $check_query = "SELECT id FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $user['original_id']);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->num_rows > 0;
        
        // Insert into users table (note: password will need to be reset)
        $email = $user['email'] ?? null;
        $contact_number = $user['contact_number'] ?? null;
        
        if ($exists) {
            // If original ID exists, create new record
            $query = "INSERT INTO users (username, fullname, email, contact_number, role, status) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssssss", 
                $user['username'],
                $user['fullname'],
                $email,
                $contact_number,
                $user['role'],
                $user['status']
            );
        } else {
            // If original ID is available, use it
            $query = "INSERT INTO users (id, username, fullname, email, contact_number, role, status) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "issssss", 
                $user['original_id'],
                $user['username'],
                $user['fullname'],
                $email,
                $contact_number,
                $user['role'],
                $user['status']
            );
        }
        mysqli_stmt_execute($stmt);
        
        // Delete from archived_users
        $query = "DELETE FROM archived_users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * Restore an appointment record from archive
 */
function restoreAppointment($archive_id) {
    global $conn;
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get archived appointment record
        $query = "SELECT * FROM archived_appointments WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $appointment = mysqli_fetch_assoc($result);
        
        if (!$appointment) {
            throw new Exception("Archived appointment record not found");
        }
        
        // Check if original ID exists in appointments table
        $check_query = "SELECT id FROM appointments WHERE id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $appointment['original_id']);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->num_rows > 0;
        
        // Insert into appointments table
        if ($exists) {
            // If original ID exists, create new record
            $query = "INSERT INTO appointments (fullname, appointment_type, preferred_datetime, status, notes) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssss", 
                $appointment['fullname'],
                $appointment['appointment_type'],
                $appointment['preferred_datetime'],
                $appointment['status'],
                $appointment['notes']
            );
        } else {
            // If original ID is available, use it
            $query = "INSERT INTO appointments (id, fullname, appointment_type, preferred_datetime, status, notes) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "isssss", 
                $appointment['original_id'],
                $appointment['fullname'],
                $appointment['appointment_type'],
                $appointment['preferred_datetime'],
                $appointment['status'],
                $appointment['notes']
            );
        }
        mysqli_stmt_execute($stmt);
        
        // Delete from archived_appointments
        $query = "DELETE FROM archived_appointments WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * Restore a resident record from archive
 */
function restoreResident($archive_id) {
    global $conn;
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get archived resident record
        $query = "SELECT * FROM archived_residents WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $resident = mysqli_fetch_assoc($result);
        
        if (!$resident) {
            throw new Exception("Archived resident record not found");
        }
        
        // Check if original ID exists in barangay_residents table
        $check_query = "SELECT id FROM barangay_residents WHERE id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "i", $resident['original_id']);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->num_rows > 0;
        
        // Insert into barangay_residents table
        if ($exists) {
            // If original ID exists, create new record
            $query = "INSERT INTO barangay_residents (first_name, last_name, middle_name, age, gender, birthday, purok, occupation, education, is_senior, is_pwd, family_planning, has_electricity, has_poso, has_nawasa, has_cr) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "sssississiisiiii", 
                $resident['first_name'],
                $resident['last_name'],
                $resident['middle_name'],
                $resident['age'],
                $resident['gender'],
                $resident['birthday'],
                $resident['purok'],
                $resident['occupation'],
                $resident['education'],
                $resident['is_senior'],
                $resident['is_pwd'],
                $resident['family_planning'],
                $resident['has_electricity'],
                $resident['has_poso'],
                $resident['has_nawasa'],
                $resident['has_cr']
            );
        } else {
            // If original ID is available, use it
            $query = "INSERT INTO barangay_residents (id, first_name, last_name, middle_name, age, gender, birthday, purok, occupation, education, is_senior, is_pwd, family_planning, has_electricity, has_poso, has_nawasa, has_cr) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "isssisssssiisiiii", 
                $resident['original_id'],
                $resident['first_name'],
                $resident['last_name'],
                $resident['middle_name'],
                $resident['age'],
                $resident['gender'],
                $resident['birthday'],
                $resident['purok'],
                $resident['occupation'],
                $resident['education'],
                $resident['is_senior'],
                $resident['is_pwd'],
                $resident['family_planning'],
                $resident['has_electricity'],
                $resident['has_poso'],
                $resident['has_nawasa'],
                $resident['has_cr']
            );
        }
        mysqli_stmt_execute($stmt);
        
        // Delete from archived_residents
        $query = "DELETE FROM archived_residents WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $archive_id);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * Archive a user record
 */
function archiveUser($user_id, $archived_by_user_id) {
    global $conn;
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get user record
        $query = "SELECT * FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        if (!$user) {
            throw new Exception("User record not found");
        }
        
        // Create archived_users table if it doesn't exist
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_users'");
        if (mysqli_num_rows($table_check) == 0) {
            $create_table = "
                CREATE TABLE archived_users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    original_id INT NOT NULL,
                    username VARCHAR(50) NOT NULL,
                    fullname VARCHAR(255) NOT NULL,
                    email VARCHAR(255) DEFAULT NULL,
                    contact_number VARCHAR(20) DEFAULT NULL,
                    role ENUM('admin', 'worker', 'resident') NOT NULL,
                    status VARCHAR(20) DEFAULT 'approved',
                    departure_date DATE NULL,
                    archive_reason VARCHAR(255) DEFAULT 'Archived',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    archived_by INT,
                    FOREIGN KEY (archived_by) REFERENCES users(id)
                )";
            if (!mysqli_query($conn, $create_table)) {
                throw new Exception("Failed to create archived_users table: " . mysqli_error($conn));
            }
        } else {
            // Table exists, check if email and contact_number columns exist
            $columns_check = mysqli_query($conn, "SHOW COLUMNS FROM archived_users");
            $existing_columns = [];
            while ($col = mysqli_fetch_assoc($columns_check)) {
                $existing_columns[] = $col['Field'];
            }
            
            // Add email column if missing
            if (!in_array('email', $existing_columns)) {
                $add_email = "ALTER TABLE archived_users ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER fullname";
                if (!mysqli_query($conn, $add_email)) {
                    error_log("Warning: Could not add email column to archived_users: " . mysqli_error($conn));
                }
            }
            
            // Add contact_number column if missing
            if (!in_array('contact_number', $existing_columns)) {
                $add_contact = "ALTER TABLE archived_users ADD COLUMN contact_number VARCHAR(20) DEFAULT NULL AFTER email";
                if (!mysqli_query($conn, $add_contact)) {
                    error_log("Warning: Could not add contact_number column to archived_users: " . mysqli_error($conn));
                }
            }
        }
        
        // Before deleting the user, update all foreign key references to NULL or the current admin
        // This prevents foreign key constraint errors
        
        // Update archived_babies
        $update_query = "UPDATE archived_babies SET archived_by = ? WHERE archived_by = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ii", $archived_by_user_id, $user_id);
        mysqli_stmt_execute($stmt);
        
        // Update archived_vaccinations
        $update_query = "UPDATE archived_vaccinations SET archived_by = ? WHERE archived_by = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ii", $archived_by_user_id, $user_id);
        mysqli_stmt_execute($stmt);
        
        // Update archived_medicines
        $update_query = "UPDATE archived_medicines SET archived_by = ? WHERE archived_by = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ii", $archived_by_user_id, $user_id);
        mysqli_stmt_execute($stmt);
        
        // Update archived_appointments
        $update_query = "UPDATE archived_appointments SET archived_by = ? WHERE archived_by = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ii", $archived_by_user_id, $user_id);
        mysqli_stmt_execute($stmt);
        
        // Update archived_residents
        $update_query = "UPDATE archived_residents SET archived_by = ? WHERE archived_by = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ii", $archived_by_user_id, $user_id);
        mysqli_stmt_execute($stmt);
        
        // Update archived_users (for users they archived)
        $update_query = "UPDATE archived_users SET archived_by = ? WHERE archived_by = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ii", $archived_by_user_id, $user_id);
        mysqli_stmt_execute($stmt);
        
        // Archive user record
        $query = "INSERT INTO archived_users (original_id, username, fullname, email, contact_number, role, status, archive_reason, created_at, archived_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        $archive_reason = "Archived by admin";
        $email = $user['email'] ?? null;
        $contact_number = $user['contact_number'] ?? null;
        mysqli_stmt_bind_param($stmt, "issssssssi", 
            $user['id'],
            $user['username'],
            $user['fullname'],
            $email,
            $contact_number,
            $user['role'],
            $user['status'],
            $archive_reason,
            $user['created_at'],
            $archived_by_user_id
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to archive user: " . mysqli_stmt_error($stmt));
        }
        
        // Delete original record
        $query = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to delete user: " . mysqli_stmt_error($stmt));
        }
        
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/**
 * Archive a medicine record
 */
function archiveMedicine($medicine_id, $archived_by_user_id) {
    global $conn;
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get medicine record
        $query = "SELECT * FROM medicines WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $medicine_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $medicine = mysqli_fetch_assoc($result);
        
        if (!$medicine) {
            throw new Exception("Medicine record not found");
        }
        
        // Create archived_medicines table if it doesn't exist
        $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'archived_medicines'");
        if (mysqli_num_rows($table_check) == 0) {
            $create_table = "
                CREATE TABLE archived_medicines (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    original_id INT NOT NULL,
                    medicine_name VARCHAR(255) NOT NULL,
                    dosage VARCHAR(100) DEFAULT NULL,
                    quantity INT NOT NULL,
                    expiry_date DATE NOT NULL,
                    batch_number VARCHAR(100) NOT NULL,
                    low_stock_threshold INT DEFAULT 10,
                    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    archive_reason VARCHAR(255) DEFAULT 'Archived',
                    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    archived_by INT,
                    FOREIGN KEY (archived_by) REFERENCES users(id)
                )";
            if (!mysqli_query($conn, $create_table)) {
                throw new Exception("Failed to create archived_medicines table: " . mysqli_error($conn));
            }
        } else {
            // Table exists, check if dosage and date_added columns exist
            $columns_check = mysqli_query($conn, "SHOW COLUMNS FROM archived_medicines");
            $existing_columns = [];
            while ($col = mysqli_fetch_assoc($columns_check)) {
                $existing_columns[] = $col['Field'];
            }
            
            // Add dosage column if missing
            if (!in_array('dosage', $existing_columns)) {
                $add_dosage = "ALTER TABLE archived_medicines ADD COLUMN dosage VARCHAR(100) DEFAULT NULL AFTER medicine_name";
                if (!mysqli_query($conn, $add_dosage)) {
                    error_log("Warning: Could not add dosage column to archived_medicines: " . mysqli_error($conn));
                }
            }
            
            // Add date_added column if missing
            if (!in_array('date_added', $existing_columns)) {
                $add_date = "ALTER TABLE archived_medicines ADD COLUMN date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER low_stock_threshold";
                if (!mysqli_query($conn, $add_date)) {
                    error_log("Warning: Could not add date_added column to archived_medicines: " . mysqli_error($conn));
                }
            }
        }
        
        // Archive medicine record
        $query = "INSERT INTO archived_medicines (original_id, medicine_name, dosage, quantity, expiry_date, batch_number, low_stock_threshold, date_added, archive_reason, archived_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare archive query: " . mysqli_error($conn));
        }
        
        $archive_reason = "Archived";
        $dosage = $medicine['dosage'] ?? null;
        $date_added = $medicine['date_added'] ?? date('Y-m-d H:i:s');
        
        mysqli_stmt_bind_param($stmt, "issississi", 
            $medicine['id'],
            $medicine['medicine_name'],
            $dosage,
            $medicine['quantity'],
            $medicine['expiry_date'],
            $medicine['batch_number'],
            $medicine['low_stock_threshold'],
            $date_added,
            $archive_reason,
            $archived_by_user_id
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to archive medicine: " . mysqli_stmt_error($stmt));
        }
        
        // Delete original record
        $query = "DELETE FROM medicines WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare delete query: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $medicine_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to delete medicine: " . mysqli_stmt_error($stmt));
        }
        
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}
