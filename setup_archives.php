<?php
require_once 'config/database.php';
require_once 'config/session.php';
requireAdmin();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Create archived_babies table
        $create_babies_table = "
            CREATE TABLE IF NOT EXISTS archived_babies (
                id INT PRIMARY KEY AUTO_INCREMENT,
                original_id INT NOT NULL,
                full_name VARCHAR(255) NOT NULL,
                date_of_birth DATE NOT NULL,
                parent_guardian_name VARCHAR(255) NOT NULL,
                contact_number VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_by INT,
                FOREIGN KEY (archived_by) REFERENCES users(id)
            )";
        
        if (!mysqli_query($conn, $create_babies_table)) {
            throw new Exception("Error creating archived_babies table: " . mysqli_error($conn));
        }
        
        // Create archived_vaccinations table
        $create_vaccinations_table = "
            CREATE TABLE IF NOT EXISTS archived_vaccinations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                original_id INT NOT NULL,
                baby_id INT NOT NULL,
                vaccine_type VARCHAR(50) NOT NULL,
                schedule_date DATE NOT NULL,
                status VARCHAR(20) NOT NULL,
                notes TEXT,
                administered_by INT,
                administered_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                archived_by INT,
                FOREIGN KEY (archived_by) REFERENCES users(id)
            )";
        
        if (!mysqli_query($conn, $create_vaccinations_table)) {
            throw new Exception("Error creating archived_vaccinations table: " . mysqli_error($conn));
        }
        
        $success = true;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Archive Tables - San Benito Health Center</title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-database me-2"></i>Archive Tables Setup
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Archive tables have been created successfully!
                        </div>
                        <div class="text-center">
                            <a href="archives.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right me-2"></i>Go to Archives
                            </a>
                            <a href="index.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-home me-2"></i>Back to Dashboard
                            </a>
                        </div>
                        <?php elseif ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error: <?php echo htmlspecialchars($error); ?>
                        </div>
                        <div class="text-center">
                            <button type="button" class="btn btn-danger" onclick="location.reload()">
                                <i class="fas fa-redo me-2"></i>Try Again
                            </button>
                            <a href="index.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-home me-2"></i>Back to Dashboard
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will create the necessary database tables for the archive system.
                        </div>
                        
                        <h5>Tables to be created:</h5>
                        <ul class="list-group mb-4">
                            <li class="list-group-item">
                                <i class="fas fa-baby me-2 text-warning"></i>
                                <strong>archived_babies</strong> - For storing archived baby records
                            </li>
                            <li class="list-group-item">
                                <i class="fas fa-syringe me-2 text-info"></i>
                                <strong>archived_vaccinations</strong> - For storing archived vaccination records
                            </li>
                        </ul>
                        
                        <form method="POST">
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-database me-2"></i>Create Archive Tables
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg ms-2">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>