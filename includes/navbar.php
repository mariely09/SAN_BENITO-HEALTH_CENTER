<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="<?php 
            if (isset($_SESSION['role'])) {
                if ($_SESSION['role'] === 'resident') {
                    echo 'resident_dashboard.php';
                } elseif ($_SESSION['role'] === 'admin') {
                    echo 'admin_dashboard.php';
                } elseif ($_SESSION['role'] === 'worker') {
                    echo 'worker_dashboard.php';
                } else {
                    echo 'index.php';
                }
            } else {
                echo 'index.php';
            }
        ?>">
            <img src="assets/img/san-benito-logo.png" alt="San Benito Health Center Logo" class="navbar-logo me-2">
            San Benito Health Center
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'resident'): ?>
                    <!-- Resident Navigation -->
                    <li class="nav-item">
                        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                            <i class="fas fa-calendar-plus"></i> Schedule Appointment
                        </a>
                    </li>
                <?php else: ?>
                    <!-- Worker/Admin Navigation -->
                    <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="archives.php">
                            <i class="fas fa-archive"></i> Archives
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <?php elseif (isApprovedWorker()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="worker_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['fullname'] ?? 'User'); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal"><i class="fas fa-user-edit"></i> Edit Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="fas fa-user-edit"></i> Edit Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="profileModalBody">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading profile...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load profile content when modal is shown
document.getElementById('profileModal').addEventListener('show.bs.modal', function () {
    fetch('profile_modal.php')
        .then(response => response.text())
        .then(data => {
            document.getElementById('profileModalBody').innerHTML = data;
            // Initialize profile forms after content is loaded
            if (typeof initializeProfileForms === 'function') {
                setTimeout(initializeProfileForms, 100);
            }
            // Initialize password toggles
            if (typeof initializePasswordToggles === 'function') {
                setTimeout(initializePasswordToggles, 100);
            }
        })
        .catch(error => {
            document.getElementById('profileModalBody').innerHTML = 
                '<div class="alert alert-danger">Error loading profile. Please try again.</div>';
        });
});
</script>