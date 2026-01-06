<?php
// Check if user is logged in first
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/header.php';
requireApproved();

// Get all medicines data
$medicines_query = "SELECT * FROM medicines ORDER BY 
                   CASE WHEN quantity <= low_stock_threshold THEN 0 ELSE 1 END, 
                   CASE WHEN expiry_date < CURDATE() THEN 0 
                        WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 
                        ELSE 2 END, 
                   medicine_name ASC";
$medicines_result = mysqli_query($conn, $medicines_query);
if (!$medicines_result) {
    die("Error in medicines query: " . mysqli_error($conn));
}

// Get all babies and their vaccination records
$babies_query = "SELECT b.*, 
                 COUNT(v.id) as total_vaccinations,
                 COUNT(CASE WHEN v.status = 'completed' THEN 1 END) as completed_vaccinations,
                 COUNT(CASE WHEN v.status = 'pending' THEN 1 END) as pending_vaccinations
                 FROM babies b 
                 LEFT JOIN vaccinations v ON b.id = v.baby_id 
                 GROUP BY b.id 
                 ORDER BY b.full_name ASC";
$babies_result = mysqli_query($conn, $babies_query);
if (!$babies_result) {
    die("Error in babies query: " . mysqli_error($conn));
}

// Get all appointments
$appointments_query = "SELECT a.*, u.username 
                      FROM appointments a 
                      LEFT JOIN users u ON a.user_id = u.id 
                      ORDER BY a.preferred_datetime DESC";
$appointments_result = mysqli_query($conn, $appointments_query);
if (!$appointments_result) {
    die("Error in appointments query: " . mysqli_error($conn));
}

// Get vaccination schedules
$vaccinations_query = "SELECT v.*, b.full_name as baby_name, b.parent_guardian_name 
                      FROM vaccinations v 
                      JOIN babies b ON v.baby_id = b.id 
                      ORDER BY v.schedule_date DESC";
$vaccinations_result = mysqli_query($conn, $vaccinations_query);
if (!$vaccinations_result) {
    die("Error in vaccinations query: " . mysqli_error($conn));
}

// Get summary statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM medicines) as total_medicines,
                (SELECT COUNT(*) FROM medicines WHERE quantity <= low_stock_threshold) as low_stock_medicines,
                (SELECT COUNT(*) FROM medicines WHERE expiry_date < CURDATE()) as expired_medicines,
                (SELECT COUNT(*) FROM babies) as total_babies,
                (SELECT COUNT(*) FROM vaccinations) as total_vaccinations,
                (SELECT COUNT(*) FROM vaccinations WHERE status = 'completed') as completed_vaccinations,
                (SELECT COUNT(*) FROM appointments) as total_appointments,
                (SELECT COUNT(*) FROM appointments WHERE status = 'confirmed') as confirmed_appointments";
$stats_result = mysqli_query($conn, $stats_query);
if (!$stats_result) {
    die("Error in stats query: " . mysqli_error($conn));
}
$stats = mysqli_fetch_assoc($stats_result);
?>

<style>
    @media print {
        .no-print {
            display: none !important;
        }
        
        .sidebar, .topbar, .navbar {
            display: none !important;
        }
        
        .main-content {
            margin-left: 0 !important;
        }
        
        .content {
            padding: 0 !important;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .signature-section {
            position: fixed;
            bottom: 50px;
            width: 100%;
        }
        
        body {
            font-size: 12px;
        }
        
        .table {
            font-size: 10px;
        }
        
        .card {
            border: 1px solid #000 !important;
            box-shadow: none !important;
        }
    }
    
    .report-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 20px;
        border-bottom: 2px solid #27ae60;
    }
    
    .report-logo {
        width: 80px;
        height: 80px;
        margin-bottom: 15px;
    }
    
    .signature-section {
        margin-top: 50px;
        padding: 20px 0;
    }
    
    .signature-box {
        border-top: 1px solid #000;
        padding-top: 10px;
        text-align: center;
        margin-top: 50px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid #dee2e6;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #27ae60;
    }
    
    .section-title {
        color: #2c3e50;
        font-size: 1.4rem;
        border-bottom: 2px solid #27ae60;
        padding-bottom: 8px;
        margin-bottom: 16px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h1 class="page-title">Comprehensive Health Center Report</h1>
    <div>
        <button onclick="generatePDF()" class="btn btn-danger me-2">
            <i class="fas fa-file-pdf me-2"></i>Generate PDF
        </button>
        <button onclick="window.print()" class="btn btn-primary me-2">
            <i class="fas fa-print me-2"></i>Print Report
        </button>
        <a href="<?php echo getDashboardUrl(); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
</div>

<!-- Report Header -->
<div class="report-header">
    <img src="assets/img/san-benito-logo.png" alt="San Benito Logo" class="report-logo">
    <h2>San Benito Health Center</h2>
    <h4>Comprehensive Health Management Report</h4>
    <p class="mb-0">Barangay San Benito, Masapang, Victoria, Laguna</p>
    <p class="mb-0">Generated on: <?php echo date('F d, Y g:i A'); ?></p>
    <p class="mb-0">Prepared by: <?php echo htmlspecialchars($_SESSION['fullname']); ?> (<?php echo ucfirst($_SESSION['role']); ?>)</p>
</div>

<!-- Summary Statistics -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="section-title mb-0"><i class="fas fa-chart-bar me-2"></i>Summary Statistics</h5>
    </div>
    <div class="card-body">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_medicines']; ?></div>
                <div>Total Medicines</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-warning"><?php echo $stats['low_stock_medicines']; ?></div>
                <div>Low Stock Medicines</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-danger"><?php echo $stats['expired_medicines']; ?></div>
                <div>Expired Medicines</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_babies']; ?></div>
                <div>Registered Babies</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_vaccinations']; ?></div>
                <div>Total Vaccinations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-success"><?php echo $stats['completed_vaccinations']; ?></div>
                <div>Completed Vaccinations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_appointments']; ?></div>
                <div>Total Appointments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number text-info"><?php echo $stats['confirmed_appointments']; ?></div>
                <div>Confirmed Appointments</div>
            </div>
        </div>
    </div>
</div>

<!-- Medicine Inventory -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="section-title mb-0"><i class="fas fa-pills me-2"></i>Medicine Inventory</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Medicine Name</th>
                        <th>Quantity</th>
                        <th>Batch Number</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($medicine = mysqli_fetch_assoc($medicines_result)): ?>
                    <?php
                        $lowStock = $medicine['quantity'] <= $medicine['low_stock_threshold'];
                        $expired = strtotime($medicine['expiry_date']) < strtotime(date('Y-m-d'));
                        $expiringSoon = !$expired && strtotime($medicine['expiry_date']) <= strtotime('+30 days');
                        
                        $status = 'Normal';
                        $statusClass = '';
                        
                        if ($expired) {
                            $status = 'Expired';
                            $statusClass = 'table-danger';
                        } elseif ($expiringSoon) {
                            $status = 'Expiring Soon';
                            $statusClass = 'table-warning';
                        } elseif ($lowStock) {
                            $status = 'Low Stock';
                            $statusClass = 'table-warning';
                        }
                    ?>
                    <tr class="<?php echo $statusClass; ?>">
                        <td><?php echo htmlspecialchars($medicine['medicine_name']); ?></td>
                        <td><?php echo $medicine['quantity']; ?> units</td>
                        <td><?php echo htmlspecialchars($medicine['batch_number']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($medicine['expiry_date'])); ?></td>
                        <td><?php echo $status; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="page-break"></div>

<!-- Baby Records and Vaccination Status -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="section-title mb-0"><i class="fas fa-baby me-2"></i>Baby Records & Vaccination Status</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Baby Name</th>
                        <th>Age</th>
                        <th>Parent/Guardian</th>
                        <th>Contact</th>
                        <th>Total Vaccinations</th>
                        <th>Completed</th>
                        <th>Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($baby = mysqli_fetch_assoc($babies_result)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($baby['full_name']); ?></td>
                        <td><?php echo getAge($baby['date_of_birth']); ?></td>
                        <td><?php echo htmlspecialchars($baby['parent_guardian_name']); ?></td>
                        <td><?php echo htmlspecialchars($baby['contact_number']); ?></td>
                        <td><?php echo $baby['total_vaccinations']; ?></td>
                        <td class="text-success"><?php echo $baby['completed_vaccinations']; ?></td>
                        <td class="text-warning"><?php echo $baby['pending_vaccinations']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Vaccination Schedule -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="section-title mb-0"><i class="fas fa-syringe me-2"></i>Vaccination Schedule</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Baby Name</th>
                        <th>Parent/Guardian</th>
                        <th>Vaccine Type</th>
                        <th>Schedule Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($vaccination = mysqli_fetch_assoc($vaccinations_result)): ?>
                    <?php
                        $statusClass = '';
                        if ($vaccination['status'] == 'completed') {
                            $statusClass = 'table-success';
                        } elseif (strtotime($vaccination['schedule_date']) < strtotime('today')) {
                            $statusClass = 'table-danger';
                        } elseif (strtotime($vaccination['schedule_date']) == strtotime('today')) {
                            $statusClass = 'table-info';
                        }
                    ?>
                    <tr class="<?php echo $statusClass; ?>">
                        <td><?php echo htmlspecialchars($vaccination['baby_name']); ?></td>
                        <td><?php echo htmlspecialchars($vaccination['parent_guardian_name']); ?></td>
                        <td><?php echo htmlspecialchars($vaccination['vaccine_type']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($vaccination['schedule_date'])); ?></td>
                        <td><?php echo ucfirst($vaccination['status']); ?></td>
                        <td><?php echo htmlspecialchars($vaccination['notes'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="page-break"></div>

<!-- Scheduled Appointments -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="section-title mb-0"><i class="fas fa-calendar-check me-2"></i>Scheduled Appointments</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Full Name</th>
                        <th>Appointment Type</th>
                        <th>Preferred Date & Time</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($appointment = mysqli_fetch_assoc($appointments_result)): ?>
                    <?php
                        $statusClass = '';
                        switch ($appointment['status']) {
                            case 'confirmed':
                                $statusClass = 'table-success';
                                break;
                            case 'completed':
                                $statusClass = 'table-info';
                                break;
                            case 'cancelled':
                                $statusClass = 'table-danger';
                                break;
                            default:
                                $statusClass = 'table-warning';
                        }
                    ?>
                    <tr class="<?php echo $statusClass; ?>">
                        <td><?php echo htmlspecialchars($appointment['fullname']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['appointment_type']); ?></td>
                        <td><?php echo date('M d, Y g:i A', strtotime($appointment['preferred_datetime'])); ?></td>
                        <td><?php echo ucfirst($appointment['status']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['notes'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Signature Section -->
<div class="signature-section no-print">
    <div class="row">
        <div class="col-md-6">
            <div class="signature-box">
                <strong><?php echo htmlspecialchars($_SESSION['fullname']); ?></strong><br>
                Health Worker<br>
                San Benito Health Center
            </div>
        </div>
        <div class="col-md-6">
            <div class="signature-box">
                <strong>_________________________</strong><br>
                Barangay Captain<br>
                Barangay San Benito
            </div>
        </div>
    </div>
</div>

<!-- Print Signature Section -->
<div class="signature-section d-none" id="printSignatures">
    <div style="display: flex; justify-content: space-between; margin-top: 50px;">
        <div style="text-align: center; width: 45%;">
            <div style="border-top: 1px solid #000; padding-top: 10px; margin-top: 50px;">
                <strong><?php echo htmlspecialchars($_SESSION['fullname']); ?></strong><br>
                Health Worker<br>
                San Benito Health Center
            </div>
        </div>
        <div style="text-align: center; width: 45%;">
            <div style="border-top: 1px solid #000; padding-top: 10px; margin-top: 50px;">
                <strong>_________________________</strong><br>
                Barangay Captain<br>
                Barangay San Benito
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
function generatePDF() {
    // Show print signatures and hide regular signatures
    document.querySelector('.signature-section.no-print').style.display = 'none';
    document.getElementById('printSignatures').classList.remove('d-none');
    
    // Hide no-print elements
    const noPrintElements = document.querySelectorAll('.no-print');
    noPrintElements.forEach(el => el.style.display = 'none');
    
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');
    
    // Get the content to convert
    const content = document.body;
    
    html2canvas(content, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff'
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const imgWidth = 210; // A4 width in mm
        const pageHeight = 295; // A4 height in mm
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        let heightLeft = imgHeight;
        let position = 0;
        
        // Add first page
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
        
        // Add additional pages if needed
        while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        
        // Save the PDF
        const filename = `San_Benito_Health_Report_${new Date().toISOString().split('T')[0]}.pdf`;
        pdf.save(filename);
        
        // Restore original display
        noPrintElements.forEach(el => el.style.display = '');
        document.querySelector('.signature-section.no-print').style.display = '';
        document.getElementById('printSignatures').classList.add('d-none');
    }).catch(error => {
        console.error('Error generating PDF:', error);
        alert('Error generating PDF. Please try printing instead.');
        
        // Restore original display
        noPrintElements.forEach(el => el.style.display = '');
        document.querySelector('.signature-section.no-print').style.display = '';
        document.getElementById('printSignatures').classList.add('d-none');
    });
}

// Handle print
window.addEventListener('beforeprint', function() {
    document.querySelector('.signature-section.no-print').style.display = 'none';
    document.getElementById('printSignatures').classList.remove('d-none');
});

window.addEventListener('afterprint', function() {
    document.querySelector('.signature-section.no-print').style.display = '';
    document.getElementById('printSignatures').classList.add('d-none');
});
</script>

<?php require_once 'includes/footer.php'; ?>