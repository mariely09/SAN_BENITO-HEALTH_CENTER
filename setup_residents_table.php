<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Only admins can run this setup
requireAdmin();

$success_messages = [];
$error_messages = [];

// Create barangay_residents table
$create_table_sql = "
CREATE TABLE IF NOT EXISTS `barangay_residents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `age` int(11) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `birthday` date NOT NULL,
  `purok` varchar(50) NOT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `education` varchar(100) DEFAULT NULL,
  `is_senior` tinyint(1) DEFAULT 0,
  `is_pwd` tinyint(1) DEFAULT 0,
  `family_planning` enum('Yes','No') DEFAULT 'No',
  `has_electricity` tinyint(1) DEFAULT 0,
  `has_poso` tinyint(1) DEFAULT 0,
  `has_nawasa` tinyint(1) DEFAULT 0,
  `has_cr` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_purok` (`purok`),
  KEY `idx_age` (`age`),
  KEY `idx_is_senior` (`is_senior`),
  KEY `idx_gender` (`gender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

if (mysqli_query($conn, $create_table_sql)) {
    $success_messages[] = "Barangay residents table created successfully.";
} else {
    $error_messages[] = "Error creating barangay residents table: " . mysqli_error($conn);
}

// Add some sample data
$sample_residents = [
    [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'middle_name' => 'Santos',
        'age' => 45,
        'gender' => 'Male',
        'birthday' => '1978-05-15',
        'purok' => 'Purok 1',
        'occupation' => 'Farmer',
        'education' => 'High School Graduate',
        'is_senior' => 0,
        'is_pwd' => 0,
        'family_planning' => 'Yes',
        'has_electricity' => 1,
        'has_poso' => 1,
        'has_nawasa' => 0,
        'has_cr' => 1
    ],
    [
        'first_name' => 'Maria',
        'last_name' => 'Garcia',
        'middle_name' => 'Lopez',
        'age' => 67,
        'gender' => 'Female',
        'birthday' => '1956-12-03',
        'purok' => 'Purok 2',
        'occupation' => 'Retired',
        'education' => 'Elementary Graduate',
        'is_senior' => 1,
        'is_pwd' => 0,
        'family_planning' => 'No',
        'has_electricity' => 1,
        'has_poso' => 0,
        'has_nawasa' => 1,
        'has_cr' => 1
    ],
    [
        'first_name' => 'Pedro',
        'last_name' => 'Reyes',
        'middle_name' => 'Cruz',
        'age' => 32,
        'gender' => 'Male',
        'birthday' => '1991-08-22',
        'purok' => 'Purok 1',
        'occupation' => 'Construction Worker',
        'education' => 'High School Graduate',
        'is_senior' => 0,
        'is_pwd' => 1,
        'family_planning' => 'Yes',
        'has_electricity' => 0,
        'has_poso' => 1,
        'has_nawasa' => 0,
        'has_cr' => 0
    ],
    [
        'first_name' => 'Ana',
        'last_name' => 'Mendoza',
        'middle_name' => 'Torres',
        'age' => 28,
        'gender' => 'Female',
        'birthday' => '1995-03-10',
        'purok' => 'Purok 3',
        'occupation' => 'Teacher',
        'education' => 'College Graduate',
        'is_senior' => 0,
        'is_pwd' => 0,
        'family_planning' => 'Yes',
        'has_electricity' => 1,
        'has_poso' => 1,
        'has_nawasa' => 1,
        'has_cr' => 1
    ],
    [
        'first_name' => 'Baby',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 1,
        'gender' => 'Female',
        'birthday' => '2022-11-15',
        'purok' => 'Purok 2',
        'occupation' => '',
        'education' => '',
        'is_senior' => 0,
        'is_pwd' => 0,
        'family_planning' => 'No',
        'has_electricity' => 1,
        'has_poso' => 1,
        'has_nawasa' => 0,
        'has_cr' => 1
    ]
];

foreach ($sample_residents as $resident) {
    $insert_sql = "INSERT INTO barangay_residents (first_name, last_name, middle_name, age, gender, birthday, purok, occupation, education, is_senior, is_pwd, family_planning, has_electricity, has_poso, has_nawasa, has_cr) 
                   VALUES ('{$resident['first_name']}', '{$resident['last_name']}', '{$resident['middle_name']}', {$resident['age']}, '{$resident['gender']}', '{$resident['birthday']}', '{$resident['purok']}', '{$resident['occupation']}', '{$resident['education']}', {$resident['is_senior']}, {$resident['is_pwd']}, '{$resident['family_planning']}', {$resident['has_electricity']}, {$resident['has_poso']}, {$resident['has_nawasa']}, {$resident['has_cr']})";
    
    if (mysqli_query($conn, $insert_sql)) {
        $success_messages[] = "Sample resident '{$resident['first_name']} {$resident['last_name']}' added successfully.";
    } else {
        $error_messages[] = "Error adding sample resident '{$resident['first_name']} {$resident['last_name']}': " . mysqli_error($conn);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Barangay Residents Table - San Benito Health Center</title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            Barangay Residents Table Setup
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($success_messages)): ?>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle me-2"></i>Success!</h5>
                                <ul class="mb-0">
                                    <?php foreach ($success_messages as $message): ?>
                                        <li><?php echo htmlspecialchars($message); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($error_messages)): ?>
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Errors!</h5>
                                <ul class="mb-0">
                                    <?php foreach ($error_messages as $message): ?>
                                        <li><?php echo htmlspecialchars($message); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4">
                            <h5>What was set up:</h5>
                            <ul>
                                <li>Created <code>barangay_residents</code> table with all required fields</li>
                                <li>Added proper indexes for better performance</li>
                                <li>Inserted sample resident data for testing</li>
                            </ul>
                        </div>

                        <div class="mt-4">
                            <h5>Table Structure:</h5>
                            <ul>
                                <li><strong>Personal Info:</strong> First Name, Last Name, Middle Name, Age, Gender, Birthday</li>
                                <li><strong>Location:</strong> Purok</li>
                                <li><strong>Background:</strong> Occupation, Education</li>
                                <li><strong>Categories:</strong> Senior Citizen, PWD, Family Planning</li>
                                <li><strong>Utilities:</strong> Electricity, Poso, Nawasa, CR</li>
                            </ul>
                        </div>

                        <div class="mt-4 text-center">
                            <a href="residents.php" class="btn btn-primary me-2">
                                <i class="fas fa-users me-2"></i>View Residents
                            </a>
                            <a href="<?php echo isAdmin() ? 'admin_dashboard.php' : 'worker_dashboard.php'; ?>" class="btn btn-secondary">
                                <i class="fas fa-home me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>