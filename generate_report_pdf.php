<?php


require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Check if user is logged in and approved
if (!isset($_SESSION['user_id']) || (!isAdmin() && !isApprovedWorker())) {
    die("Access denied. Please log in with proper permissions.");
}



// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Get comprehensive statistics with error checking
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM medicines) as total_medicines,
                (SELECT COUNT(*) FROM medicines WHERE quantity <= low_stock_threshold) as low_stock_medicines,
                (SELECT COUNT(*) FROM medicines WHERE expiry_date < CURDATE()) as expired_medicines,
                (SELECT COUNT(*) FROM medicines WHERE expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as expiring_soon_medicines,
                (SELECT COUNT(*) FROM babies) as total_babies,
                (SELECT COUNT(*) FROM vaccinations) as total_vaccinations,
                (SELECT COUNT(*) FROM vaccinations WHERE status = 'completed') as completed_vaccinations,
                (SELECT COUNT(*) FROM vaccinations WHERE status = 'pending') as pending_vaccinations,
                (SELECT COUNT(*) FROM appointments) as total_appointments,
                (SELECT COUNT(*) FROM appointments WHERE status = 'confirmed') as confirmed_appointments,
                (SELECT COUNT(*) FROM appointments WHERE status = 'completed') as completed_appointments,
                (SELECT COUNT(*) FROM appointments WHERE status = 'pending') as pending_appointments,
                (SELECT COUNT(*) FROM barangay_residents) as total_residents,
                (SELECT COUNT(*) FROM barangay_residents WHERE is_senior = 1) as total_seniors,
                (SELECT COUNT(*) FROM barangay_residents WHERE is_pwd = 1) as total_pwd,
                (SELECT COUNT(*) FROM barangay_residents WHERE age BETWEEN 0 AND 12) as total_children";

$stats_result = mysqli_query($conn, $stats_query);
if (!$stats_result) {
    die("Stats query failed: " . mysqli_error($conn));
}
$stats = mysqli_fetch_assoc($stats_result);



// Get medicines with status
$medicines_query = "SELECT * FROM medicines ORDER BY 
                   CASE WHEN quantity <= low_stock_threshold THEN 0 ELSE 1 END,
                   CASE WHEN expiry_date < CURDATE() THEN 0 
                        WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 
                        ELSE 2 END,
                   medicine_name ASC";
$medicines_result = mysqli_query($conn, $medicines_query);
if (!$medicines_result) {
    error_log("Medicines query failed: " . mysqli_error($conn));
} else {
    // Store medicines data in array to avoid result pointer issues
    $medicines_data = [];
    while ($row = mysqli_fetch_assoc($medicines_result)) {
        $medicines_data[] = $row;
    }
}

// Get babies with vaccination info
$babies_query = "SELECT b.*, 
                 COUNT(v.id) as total_vaccinations,
                 COUNT(CASE WHEN v.status = 'completed' THEN 1 END) as completed_vaccinations
                 FROM babies b 
                 LEFT JOIN vaccinations v ON b.id = v.baby_id 
                 GROUP BY b.id 
                 ORDER BY b.full_name ASC";
$babies_result = mysqli_query($conn, $babies_query);
if (!$babies_result) {
    error_log("Babies query failed: " . mysqli_error($conn));
} else {
    // Store babies data in array
    $babies_data = [];
    while ($row = mysqli_fetch_assoc($babies_result)) {
        $babies_data[] = $row;
    }
}

// Get recent vaccinations
$vaccinations_query = "SELECT v.*, b.full_name as baby_name, b.parent_guardian_name 
                      FROM vaccinations v 
                      JOIN babies b ON v.baby_id = b.id 
                      ORDER BY v.schedule_date DESC 
                      LIMIT 20";
$vaccinations_result = mysqli_query($conn, $vaccinations_query);
if (!$vaccinations_result) {
    error_log("Vaccinations query failed: " . mysqli_error($conn));
} else {
    // Store vaccinations data in array
    $vaccinations_data = [];
    while ($row = mysqli_fetch_assoc($vaccinations_result)) {
        $vaccinations_data[] = $row;
    }
}

// Get appointments
$appointments_query = "SELECT a.*, u.username 
                      FROM appointments a 
                      LEFT JOIN users u ON a.user_id = u.id 
                      ORDER BY a.preferred_datetime ASC";
$appointments_result = mysqli_query($conn, $appointments_query);
if (!$appointments_result) {
    error_log("Appointments query failed: " . mysqli_error($conn));
} else {
    // Store appointments data in array
    $appointments_data = [];
    while ($row = mysqli_fetch_assoc($appointments_result)) {
        $appointments_data[] = $row;
    }
}

// Get residents data
$residents_query = "SELECT * FROM barangay_residents ORDER BY last_name, first_name ASC";
$residents_result = mysqli_query($conn, $residents_query);
if (!$residents_result) {
    error_log("Residents query failed: " . mysqli_error($conn));
} else {
    // Store residents data in array
    $residents_data = [];
    while ($row = mysqli_fetch_assoc($residents_result)) {
        $residents_data[] = $row;
    }
}



// Get filter parameter
$filter = $_GET['filter'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Management Report - San Benito Health Center</title>
    <style>
        /* Professional Medical Document Styles - Consistent with Reports.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', serif;
            font-size: 10pt;
            line-height: 1.2;
            color: #000000;
            background: white;
            margin: 0;
            padding: 0;
        }

        .document {
            max-width: 8.5in;
            margin: 0 auto;
            padding: 0.1in;
            background: white;
        }

        /* Header Styles - Professional and Clean */
        .header {
            text-align: center;
            margin-bottom: 2pt;
            padding-bottom: 2pt;
            border-bottom: 2pt solid #000000;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -2pt;
            left: 50%;
            transform: translateX(-50%);
            width: 60pt;
            height: 1pt;
            background: #000000;
        }

        .logo {
            width: 50pt;
            height: 50pt;
            margin-bottom: 6pt;
            border-radius: 0;
            box-shadow: none;
            border: 1pt solid #000000;
        }

        .title {
            font-size: 14pt;
            font-weight: bold;
            margin: 4pt 0;
            text-transform: uppercase;
            letter-spacing: 0.8pt;
            color: #000000;
            font-family: 'Times New Roman', serif;
        }

        .subtitle {
            font-size: 11pt;
            margin: 3pt 0;
            color: #000000;
            font-weight: bold;
            font-family: 'Times New Roman', serif;
        }

        .info {
            font-size: 8pt;
            margin-top: 6pt;
            color: #000000;
            line-height: 1.2;
            font-family: 'Times New Roman', serif;
        }

        .info div {
            margin: 1pt 0;
        }

        /* Section Styles - Professional Document Design */
        .section {
            margin: 0;
            margin-bottom: 3pt;
            page-break-inside: avoid;
            background: white;
            border-radius: 0;
            padding: 5pt;
            box-shadow: none;
            border: 1pt solid #000000;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 6pt;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            color: #000000;
            position: relative;
            padding-bottom: 3pt;
            border-bottom: 1pt solid #000000;
            font-family: 'Times New Roman', serif;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2pt;
            left: 0;
            width: 30pt;
            height: 1pt;
            background: #000000;
        }

        /* Professional Table Styles - Fixed Spacing */
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 8pt;
            font-size: 9pt;
            font-family: 'Times New Roman', serif;
            color: #000000;
            border: 1pt solid #000000;
        }

        .table th {
            background: #ffffff;
            border-right: 1pt solid #000000;
            border-bottom: 1pt solid #000000;
            padding: 4pt 6pt;
            text-align: center;
            font-weight: bold;
            font-size: 9pt;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            font-family: 'Times New Roman', serif;
            line-height: 1.1;
        }

        .table th:last-child {
            border-right: none;
        }

        .table td {
            border-right: 1pt solid #000000;
            border-bottom: 1pt solid #000000;
            padding: 3pt 5pt;
            vertical-align: top;
            line-height: 1.1;
            color: #000000;
            background-color: #ffffff;
            font-family: 'Times New Roman', serif;
        }

        .table td:last-child {
            border-right: none;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table .left {
            text-align: left;
        }

        .table .center {
            text-align: center;
        }

        .table .right {
            text-align: right;
        }

        /* Clean professional rows - no alternating colors */
        .table tbody tr {
            background-color: #ffffff;
        }

        /* Status Indicators - No Colors for Print */
        .status-critical {
            background-color: #ffffff;
            color: #000000;
            font-weight: 600;
        }

        .status-warning {
            background-color: #ffffff;
            color: #000000;
            font-weight: 600;
        }

        .status-normal {
            background-color: #ffffff;
            color: #000000;
            font-weight: 600;
        }

        /* Executive Summary - Professional Style */
        .summary-section {
            background: #ffffff;
            border: 1pt solid #000000;
            border-radius: 0;
            padding: 5pt;
            margin-bottom: 5pt;
        }

        .summary-table {
            margin-bottom: 8pt;
            font-family: 'Times New Roman', serif;
            color: #000000;
            border-collapse: separate;
            border-spacing: 0;
            border: 1pt solid #000000;
        }

        .summary-table th {
            background: #ffffff;
            border-right: 1pt solid #000000;
            border-bottom: 1pt solid #000000;
            font-size: 9pt;
            padding: 4pt 6pt;
            font-weight: bold;
            text-align: center;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            font-family: 'Times New Roman', serif;
            line-height: 1.1;
        }

        .summary-table th:last-child {
            border-right: none;
        }

        .summary-table td {
            border-right: 1pt solid #000000;
            border-bottom: 1pt solid #000000;
            padding: 3pt 5pt;
            font-size: 9pt;
            vertical-align: top;
            line-height: 1.1;
            color: #000000;
            background-color: #ffffff;
            font-family: 'Times New Roman', serif;
        }

        .summary-table td:last-child {
            border-right: none;
        }

        .summary-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Footer Section - Professional Styling */
        .footer-section {
            margin-top: 15pt;
            page-break-inside: avoid;
            border-top: 1pt solid #000000;
            padding-top: 15pt;
            background: #ffffff;
            border-radius: 0;
            padding: 15pt;
        }

        .certification {
            background: #ffffff;
            border-left: 3pt solid #000000;
            padding: 10pt;
            margin-bottom: 15pt;
            border-radius: 0;
        }

        .certification p {
            font-size: 9pt;
            text-align: justify;
            margin: 0;
            font-style: normal;
            color: #000000;
            line-height: 1.4;
            font-family: 'Times New Roman', serif;
        }

        /* Signature Section */
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 25pt;
        }

        .signature-block {
            width: 45%;
            text-align: center;
            font-size: 9pt;
            background: white;
            padding: 15pt;
            border-radius: 6pt;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1pt solid #000000;
        }

        .signature-line {
            border-bottom: 1.5pt solid #000000;
            height: 30pt;
            margin-bottom: 8pt;
        }

        .signature-label {
            font-weight: 600;
            margin-bottom: 10pt;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
        }

        .date-line {
            border-bottom: 1pt solid #000000;
            display: inline-block;
            width: 100pt;
            margin-left: 8pt;
        }

        /* Document Control */
        .document-control {
            margin-top: 25pt;
            text-align: center;
            font-size: 8pt;
            color: #000000;
            border-top: 1pt solid #000000;
            padding-top: 10pt;
            background: rgba(248, 249, 250, 0.5);
            border-radius: 4pt;
            padding: 10pt;
        }

        /* Print Optimizations */
        @page {
            margin: 0.25in;
            size: letter;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .document {
                padding: 0;
                margin: 0;
                max-width: none;
            }

            .section {
                box-shadow: none !important;
                border: 1pt solid #dee2e6 !important;
            }

            .summary-section {
                box-shadow: none !important;
                border: 2pt solid #28a745 !important;
            }

            .signature-block {
                box-shadow: none !important;
                border: 1pt solid #dee2e6 !important;
            }

            .table {
                box-shadow: none !important;
            }

            .summary-table {
                box-shadow: none !important;
            }

            .footer-section {
                box-shadow: none !important;
            }

            .logo {
                box-shadow: none !important;
            }

            .certification {
                box-shadow: none !important;
            }

            .document-control {
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="document">
        <!-- Report Header -->
        <div class="header">
            <img src="assets/img/san-benito-logo.png" alt="San Benito Health Center Logo" class="logo">
            <div class="title">San Benito Health Center</div>
            <div class="subtitle">Health Management Report</div>
            <div class="info">
                <div>Barangay San Benito, Victoria, Laguna</div>
                <div>Report Generated: <?php echo date('F d, Y \a\t g:i A'); ?></div>
                <div>Prepared by: <?php echo htmlspecialchars($_SESSION['fullname']); ?></div>
                <div>Report Type: <?php
                switch ($filter) {
                    case 'all':
                        echo 'Complete Report';
                        break;
                    case 'summary':
                        echo 'Executive Summary';
                        break;
                    case 'medicines':
                        echo 'Medicine Inventory Report';
                        break;
                    case 'babies':
                        echo 'Baby Records Report';
                        break;
                    case 'vaccinations':
                        echo 'Vaccination Records Report';
                        break;
                    case 'appointments':
                        echo 'Appointment Records Report';
                        break;
                    case 'residents':
                        echo 'Barangay Residents Report';
                        break;
                    default:
                        echo 'Health Report';
                        break;
                }
                ?></div>
            </div>
        </div>

        <?php
        // ONLY show executive summary for 'summary' or 'all' filters
        if ($filter === 'summary' || $filter === 'all'):
            ?>
            <!-- Executive Summary - Professional Document Design -->
            <div style="margin: 0; padding: 5pt; border: 1pt solid #000000; background: white;">
                <div
                    style="font-size: 11pt; font-weight: bold; margin-bottom: 6pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #000000; padding-bottom: 3pt; border-bottom: 1pt solid #000000; font-family: 'Times New Roman', serif;">
                    EXECUTIVE SUMMARY</div>

                <?php
                // Enhanced KPI Calculations with Thresholds (matching reports.php)
                $critical_issues = $stats['low_stock_medicines'] + $stats['expired_medicines'];
                $vaccination_rate = $stats['total_vaccinations'] > 0 ? round(($stats['completed_vaccinations'] / $stats['total_vaccinations']) * 100, 1) : 0;
                $pending_appointments = $stats['pending_appointments'];

                // KPI Thresholds (configurable)
                $kpi_thresholds = [
                    'vaccination_rate_good' => 90,
                    'vaccination_rate_fair' => 75,
                    'inventory_critical_max' => 5,
                    'pending_appointments_max' => 10,
                    'expiry_warning_days' => 30
                ];

                // Calculate additional KPIs
                $appointment_completion_rate = $stats['total_appointments'] > 0 ?
                    round(($stats['completed_appointments'] / $stats['total_appointments']) * 100, 1) : 0;
                $inventory_health_score = $stats['total_medicines'] > 0 ?
                    round((($stats['total_medicines'] - $critical_issues) / $stats['total_medicines']) * 100, 1) : 100;

                // Overall Health Score (weighted average)
                $health_score = round(($vaccination_rate * 0.3 + $appointment_completion_rate * 0.3 + $inventory_health_score * 0.4), 1);
                ?>

                <!-- Report Header - Professional Design -->
                <div style="border-bottom: 1pt solid #000000; padding-bottom: 2pt; margin-bottom: 4pt;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; font-size: 9pt; color: #000000;">
                        <div><strong>Report Period:</strong> <?php echo date('F Y'); ?></div>
                        <div><strong>Generated:</strong> <?php echo date('M d, Y'); ?></div>
                        <div>
                            <strong>Overall Health Score:</strong>
                            <span
                                style="background: #000000; color: white; padding: 2pt 6pt; font-weight: 600; margin-left: 4pt; border: 1pt solid #000000;">
                                <?php echo $health_score; ?>%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Professional KPI Table -->
                <div style="margin-bottom: 4pt;">
                    <table class="summary-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                        <thead>
                            <tr>
                                <th
                                    style="background: #ffffff; border: 1pt solid #000000; padding: 3pt 4pt; text-align: center; color: #000000; font-weight: 600; font-size: 8pt;">
                                    Performance Indicator</th>
                                <th
                                    style="background: #ffffff; border: 1pt solid #000000; padding: 3pt 4pt; text-align: center; color: #000000; font-weight: 600; font-size: 8pt;">
                                    Current Value</th>
                                <th
                                    style="background: #ffffff; border: 1pt solid #000000; padding: 3pt 4pt; text-align: center; color: #000000; font-weight: 600; font-size: 8pt;">
                                    Assessment</th>
                                <th
                                    style="background: #ffffff; border: 1pt solid #000000; padding: 3pt 4pt; text-align: center; color: #000000; font-weight: 600; font-size: 8pt;">
                                    Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="border: 1pt solid #000000; padding: 2pt 3pt; color: #000000; font-size: 8pt;">
                                    Vaccination Completion Rate</td>
                                <td
                                    style="border: 1pt solid #000000; padding: 2pt 3pt; text-align: center; font-weight: 600; color: #000000; font-size: 8pt;">
                                    <?php echo $vaccination_rate; ?>%</td>
                                <td
                                    style="border: 1pt solid #000000; padding: 2pt 3pt; text-align: center; color: #000000; font-size: 8pt;">
                                    <span
                                        style="background: #ffffff; color: #000000; padding: 1pt 3pt; border: 1pt solid #000000; font-size: 7pt; font-weight: 600; text-transform: uppercase;">
                                        <?php echo $vaccination_rate >= $kpi_thresholds['vaccination_rate_good'] ? 'Excellent' : ($vaccination_rate >= $kpi_thresholds['vaccination_rate_fair'] ? 'Satisfactory' : 'Below Standard'); ?>
                                    </span>
                                </td>
                                <td
                                    style="border: 1pt solid #000000; padding: 2pt 3pt; text-align: center; color: #000000; font-size: 8pt;">
                                    ≥90%</td>
                            </tr>
                            <tr>
                                <td style="border: 1pt solid #000000; padding: 2pt 3pt; color: #000000; font-size: 8pt;">
                                    Appointment Completion Rate</td>
                                <td
                                    style="border: 1pt solid #000000; padding: 2pt 3pt; text-align: center; font-weight: 600; color: #000000; font-size: 8pt;">
                                    <?php echo $appointment_completion_rate; ?>%</td>
                                <td
                                    style="border: 1pt solid #000000; padding: 2pt 3pt; text-align: center; color: #000000; font-size: 8pt;">
                                    <span
                                        style="background: #ffffff; color: #000000; padding: 1pt 3pt; border: 1pt solid #000000; font-size: 7pt; font-weight: 600; text-transform: uppercase;">
                                        <?php echo $appointment_completion_rate >= 80 ? 'Satisfactory' : ($appointment_completion_rate >= 60 ? 'Acceptable' : 'Below Standard'); ?>
                                    </span>
                                </td>
                                <td
                                    style="border: 1pt solid #000000; padding: 2pt 3pt; text-align: center; color: #000000; font-size: 8pt;">
                                    ≥80%</td>
                            </tr>
                            <tr>
                                <td style="border: 1pt solid #000000; padding: 2pt 3pt; color: #000000; font-size: 8pt;">
                                    Inventory Management Score</td>
                                <td
                                    style="border: 1pt solid #000000; padding: 2pt 3pt; text-align: center; font-weight: 600; color: #000000; font-size: 8pt;">
                                    <?php echo $inventory_health_score; ?>%</td>
                                <td
                                    style="border: 1pt solid #000000; padding: 2pt 3pt; text-align: center; color: #000000; font-size: 8pt;">
                                    <span
                                        style="background: #ffffff; color: #000000; padding: 1pt 3pt; border: 1pt solid #000000; font-size: 7pt; font-weight: 600; text-transform: uppercase;">
                                        <?php echo $inventory_health_score >= 95 ? 'Excellent' : ($inventory_health_score >= 85 ? 'Satisfactory' : 'Critical'); ?>
                                    </span>
                                </td>
                                <td
                                    style="border: 1pt solid #000000; padding: 2pt 3pt; text-align: center; color: #000000; font-size: 8pt;">
                                    ≥95%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Summary Statistics and Status -->
                <div style="display: flex; justify-content: space-between; margin-bottom: 0;">
                    <div style="width: 48%;">
                        <div
                            style="font-size: 9pt; font-weight: 600; color: #000000; margin-bottom: 3pt; text-transform: uppercase; letter-spacing: 0.3pt;">
                            Service Statistics</div>
                        <table style="width: 100%; font-size: 8pt; border-collapse: collapse;">
                            <tbody>
                                <tr>
                                    <td
                                        style="color: #000000; padding: 1pt 0; border-bottom: 1pt solid #cccccc; font-size: 8pt;">
                                        Total Medicines in Inventory</td>
                                    <td
                                        style="color: #000000; text-align: right; font-weight: 600; padding: 1pt 0; border-bottom: 1pt solid #cccccc; font-size: 8pt;">
                                        <?php echo $stats['total_medicines']; ?></td>
                                </tr>
                                <tr>
                                    <td
                                        style="color: #000000; padding: 1pt 0; border-bottom: 1pt solid #cccccc; font-size: 8pt;">
                                        Completed Vaccinations</td>
                                    <td
                                        style="color: #000000; text-align: right; font-weight: 600; padding: 1pt 0; border-bottom: 1pt solid #cccccc; font-size: 8pt;">
                                        <?php echo $stats['completed_vaccinations']; ?>/<?php echo $stats['total_vaccinations']; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="color: #000000; padding: 1pt 0; border-bottom: 1pt solid #cccccc; font-size: 8pt;">
                                        Registered Babies</td>
                                    <td
                                        style="color: #000000; text-align: right; font-weight: 600; padding: 1pt 0; border-bottom: 1pt solid #cccccc; font-size: 8pt;">
                                        <?php echo $stats['total_babies']; ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #000000; padding: 1pt 0; border-bottom: 1pt solid #cccccc; font-size: 8pt;">Total Appointments</td>
                                    <td
                                        style="color: #000000; text-align: right; font-weight: 600; padding: 1pt 0; border-bottom: 1pt solid #cccccc; font-size: 8pt;">
                                        <?php echo $stats['total_appointments']; ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #000000; padding: 1pt 0; font-size: 8pt;">Total Residents</td>
                                    <td
                                        style="color: #000000; text-align: right; font-weight: 600; padding: 1pt 0; font-size: 8pt;">
                                        <?php echo $stats['total_residents']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="width: 48%;">
                        <div
                            style="font-size: 9pt; font-weight: 600; color: #000000; margin-bottom: 3pt; text-transform: uppercase; letter-spacing: 0.3pt;">
                            Operational Status</div>
                        <div style="text-align: center; margin-bottom: 4pt;">
                            <span
                                style="background: #000000; color: white; padding: 3pt 8pt; font-size: 8pt; font-weight: 600; display: inline-block; min-width: 120pt; border: 1pt solid #000000;">
                                <?php
                                // Always show OPERATIONAL when printing to remove attention required message
                                echo 'OPERATIONAL';
                                ?>
                            </span>
                        </div>


                    </div>
                </div>
            </div>

            <!-- Issues Analysis & Recommendations Section -->
            <div class="section">
                <div class="section-title">ISSUES ANALYSIS & RECOMMENDATIONS</div>

                <?php
                // Automated Problem Detection & Recommendations (matching reports.php logic)
                $problems = [];
                $recommendations = [];

                // Medicine Inventory Issues
                if ($stats['expired_medicines'] > 0) {
                    $problems[] = [
                        'category' => 'Inventory Management',
                        'issue' => "Expired medicines detected: {$stats['expired_medicines']} item(s)",
                        'impact' => 'Patient safety risk and regulatory non-compliance',
                        'severity' => 'HIGH'
                    ];
                    $recommendations[] = [
                        'priority' => 'IMMEDIATE',
                        'category' => 'Inventory Management',
                        'action' => 'Remove all expired medicines from inventory and dispose according to protocol',
                        'timeline' => 'Within 24 hours',
                        'responsible' => 'Pharmacy Staff'
                    ];
                }

                if ($stats['low_stock_medicines'] > 0) {
                    $problems[] = [
                        'category' => 'Inventory Management',
                        'issue' => "Low stock levels identified: {$stats['low_stock_medicines']} medicine(s)",
                        'impact' => 'Potential service interruption and patient care disruption',
                        'severity' => 'MEDIUM'
                    ];
                    $recommendations[] = [
                        'priority' => 'HIGH',
                        'category' => 'Inventory Management',
                        'action' => 'Initiate procurement process for low-stock medicines and review reorder points',
                        'timeline' => 'Within 72 hours',
                        'responsible' => 'Supply Officer'
                    ];
                }

                // Vaccination Program Issues
                if ($vaccination_rate < $kpi_thresholds['vaccination_rate_fair']) {
                    $problems[] = [
                        'category' => 'Vaccination Program',
                        'issue' => "Vaccination completion rate below target: {$vaccination_rate}% (target: {$kpi_thresholds['vaccination_rate_fair']}%)",
                        'impact' => 'Reduced community immunity and compromised public health outcomes',
                        'severity' => 'MEDIUM'
                    ];
                    $recommendations[] = [
                        'priority' => 'MEDIUM',
                        'category' => 'Vaccination Program',
                        'action' => 'Implement community outreach program and review vaccination scheduling processes',
                        'timeline' => 'Within 2 weeks',
                        'responsible' => 'Health Workers'
                    ];
                }

                if ($stats['pending_vaccinations'] > 5) {
                    $problems[] = [
                        'category' => 'Vaccination Program',
                        'issue' => "Pending vaccinations require attention: {$stats['pending_vaccinations']} case(s)",
                        'impact' => 'Delayed immunization schedule affecting individual protection',
                        'severity' => 'LOW'
                    ];
                    $recommendations[] = [
                        'priority' => 'MEDIUM',
                        'category' => 'Vaccination Program',
                        'action' => 'Schedule and complete pending vaccinations, contact patients for follow-up',
                        'timeline' => 'Within 1 week',
                        'responsible' => 'Vaccination Team'
                    ];
                }

                // Appointment Management Issues
                if ($pending_appointments > $kpi_thresholds['pending_appointments_max']) {
                    $problems[] = [
                        'category' => 'Appointment Management',
                        'issue' => "Excessive pending appointments: {$pending_appointments} (threshold: {$kpi_thresholds['pending_appointments_max']})",
                        'impact' => 'Extended patient wait times and reduced service quality',
                        'severity' => 'MEDIUM'
                    ];
                    $recommendations[] = [
                        'priority' => 'HIGH',
                        'category' => 'Appointment Management',
                        'action' => 'Review and process pending appointments, optimize scheduling procedures',
                        'timeline' => 'Within 48 hours',
                        'responsible' => 'Reception Staff'
                    ];
                }

                if ($appointment_completion_rate < 70) {
                    $problems[] = [
                        'category' => 'Appointment Management',
                        'issue' => "Appointment completion rate below optimal: {$appointment_completion_rate}% (target: 70%)",
                        'impact' => 'Inefficient resource utilization and reduced operational effectiveness',
                        'severity' => 'LOW'
                    ];
                    $recommendations[] = [
                        'priority' => 'MEDIUM',
                        'category' => 'Appointment Management',
                        'action' => 'Implement appointment reminder system and analyze no-show patterns',
                        'timeline' => 'Within 1 month',
                        'responsible' => 'Administrative Staff'
                    ];
                }
                ?>

                <?php if (empty($problems)): ?>
                    <div
                        style="background: #ffffff; border: 2pt solid #000000; padding: 15pt; border-radius: 4pt; text-align: center;">
                        <div style="color: #000000; font-size: 14pt; font-weight: 600; margin-bottom: 8pt;">
                            NO CRITICAL ISSUES IDENTIFIED
                        </div>
                        <div style="color: #000000; font-size: 11pt; line-height: 1.5;">
                            All operational parameters are within acceptable ranges. Current management practices are
                            effective.<br>
                            <strong>Recommendation:</strong> Continue monitoring key performance indicators and maintain
                            existing operational standards.
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Issues Summary Table -->
                    <div style="margin-bottom: 8pt;">
                        <div
                            style="font-size: 10pt; font-weight: 600; color: #000000; margin-bottom: 4pt; text-transform: uppercase; letter-spacing: 0.3pt;">
                            Identified Issues</div>
                        <table style="width: 100%; border-collapse: collapse; font-size: 10pt;">
                            <thead>
                                <tr>
                                    <th
                                        style="background: #ffffff; border: 1pt solid #000000; padding: 8pt; text-align: center; color: #000000; font-weight: 600;">
                                        Category</th>
                                    <th
                                        style="background: #ffffff; border: 1pt solid #000000; padding: 8pt; text-align: center; color: #000000; font-weight: 600;">
                                        Issue Description</th>
                                    <th
                                        style="background: #ffffff; border: 1pt solid #000000; padding: 8pt; text-align: center; color: #000000; font-weight: 600;">
                                        Impact Assessment</th>
                                    <th
                                        style="background: #ffffff; border: 1pt solid #000000; padding: 8pt; text-align: center; color: #000000; font-weight: 600;">
                                        Severity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($problems as $problem): ?>
                                    <tr>
                                        <td style="border: 1pt solid #000000; padding: 8pt; color: #000000; font-weight: 500;">
                                            <?php echo $problem['category']; ?></td>
                                        <td style="border: 1pt solid #000000; padding: 8pt; color: #000000;">
                                            <?php echo $problem['issue']; ?></td>
                                        <td style="border: 1pt solid #000000; padding: 8pt; color: #000000; font-size: 9pt;">
                                            <?php echo $problem['impact']; ?></td>
                                        <td style="border: 1pt solid #000000; padding: 8pt; text-align: center; color: #000000;">
                                            <span
                                                style="background: #000000; color: white; padding: 2pt 6pt; border-radius: 3pt; font-size: 8pt; font-weight: 600;">
                                                <?php echo $problem['severity']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>


                <?php endif; ?>
            </div>
        <?php endif; ?>



        <?php if ($filter == 'all' || $filter == 'medicines'): ?>
            <!-- Medicine Inventory Overview -->
            <div class="section">
                <div class="section-title">MEDICINE INVENTORY OVERVIEW</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Medicine Name</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($medicines_data) && count($medicines_data) > 0): ?>
                            <?php foreach ($medicines_data as $medicine): ?>
                                <?php
                                $lowStock = $medicine['quantity'] <= $medicine['low_stock_threshold'];
                                $expired = strtotime($medicine['expiry_date']) < strtotime(date('Y-m-d'));
                                $expiringSoon = !$expired && strtotime($medicine['expiry_date']) <= strtotime('+30 days');

                                $status = 'Normal';
                                $rowClass = '';

                                if ($expired) {
                                    $status = 'Expired';
                                    $rowClass = 'status-critical';
                                } elseif ($expiringSoon) {
                                    $status = 'Expiring Soon';
                                    $rowClass = 'status-warning';
                                } elseif ($lowStock) {
                                    $status = 'Low Stock';
                                    $rowClass = 'status-warning';
                                } else {
                                    $rowClass = 'status-normal';
                                }
                                ?>
                                <tr class="<?php echo $rowClass; ?>">
                                    <td class="left"><?php echo htmlspecialchars($medicine['medicine_name']); ?></td>
                                    <td class="center"><?php echo $medicine['quantity']; ?></td>
                                    <td class="center"><?php echo date('M d, Y', strtotime($medicine['expiry_date'])); ?></td>
                                    <td class="center"><?php echo $status; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="center" style="font-style: italic; color: #000000;">
                                    No medicines found in inventory
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($filter == 'all' || $filter == 'babies'): ?>
            <!-- Baby Records Overview -->
            <div class="section">
                <div class="section-title">BABY RECORDS OVERVIEW</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Baby Name</th>
                            <th>Birth Date</th>
                            <th>Parent/Guardian</th>
                            <th>Vaccinations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($babies_data) && count($babies_data) > 0): ?>
                            <?php foreach ($babies_data as $baby): ?>
                                <tr>
                                    <td class="left"><?php echo htmlspecialchars($baby['full_name']); ?></td>
                                    <td class="center"><?php echo date('M d, Y', strtotime($baby['date_of_birth'])); ?></td>
                                    <td class="left"><?php echo htmlspecialchars($baby['parent_guardian_name']); ?></td>
                                    <td class="center">
                                        <span
                                            style="background: #ffffff; color: #000000; padding: 2pt 6pt; border: 1pt solid #000000; font-size: 9pt; font-weight: 600;">
                                            <?php echo $baby['completed_vaccinations']; ?>/<?php echo $baby['total_vaccinations']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="center" style="font-style: italic; color: #000000;">
                                    No baby records found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($filter == 'all' || $filter == 'vaccinations'): ?>
            <!-- Recent Vaccination Records -->
            <div class="section">
                <div class="section-title">RECENT VACCINATION RECORDS</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Baby Name</th>
                            <th>Vaccine Type</th>
                            <th>Schedule Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($vaccinations_data) && count($vaccinations_data) > 0): ?>
                            <?php foreach ($vaccinations_data as $vaccination): ?>
                                <tr>
                                    <td class="left"><?php echo htmlspecialchars($vaccination['baby_name']); ?></td>
                                    <td class="left"><?php echo htmlspecialchars($vaccination['vaccine_type']); ?></td>
                                    <td class="center"><?php echo date('M d, Y', strtotime($vaccination['schedule_date'])); ?></td>
                                    <td class="center">
                                        <span
                                            style="background: #ffffff; color: #000000; padding: 2pt 6pt; border: 1pt solid #000000; font-size: 8pt; font-weight: 600; text-transform: uppercase;">
                                            <?php echo ucfirst($vaccination['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="center" style="font-style: italic; color: #000000;">
                                    No vaccination records found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($filter == 'all' || $filter == 'appointments'): ?>
            <!-- Recent Appointments -->
            <div class="section">
                <div class="section-title">RECENT APPOINTMENTS</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Type</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($appointments_data) && count($appointments_data) > 0): ?>
                            <?php foreach ($appointments_data as $appointment): ?>
                                <tr>
                                    <td class="left"><?php echo htmlspecialchars($appointment['fullname']); ?></td>
                                    <td class="center">
                                        <span
                                            style="background: #ffffff; color: #000000; padding: 2pt 6pt; border: 1pt solid #000000; font-size: 8pt; font-weight: 600; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($appointment['appointment_type']); ?>
                                        </span>
                                    </td>
                                    <td class="center">
                                        <?php echo date('M d, Y g:i A', strtotime($appointment['preferred_datetime'])); ?></td>
                                    <td class="center">
                                        <span
                                            style="background: #ffffff; color: #000000; padding: 2pt 6pt; border: 1pt solid #000000; font-size: 8pt; font-weight: 600; text-transform: uppercase;">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="center" style="font-style: italic; color: #000000;">
                                    No appointments scheduled
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($filter == 'all' || $filter == 'residents'): ?>
            <!-- Barangay Residents Overview -->
            <div class="section">
                <div class="section-title">BARANGAY RESIDENTS OVERVIEW</div>
                
                <!-- Residents Summary Statistics -->
                <div style="margin-bottom: 8pt; padding: 8pt; border: 1pt solid #000000; background: #ffffff;">
                    <div style="display: flex; justify-content: space-around; text-align: center; font-size: 9pt;">
                        <div>
                            <div style="font-weight: 600; font-size: 14pt; color: #000000;"><?php echo $stats['total_residents']; ?></div>
                            <div style="color: #000000; text-transform: uppercase; font-size: 8pt;">Total Residents</div>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 14pt; color: #000000;"><?php echo $stats['total_seniors']; ?></div>
                            <div style="color: #000000; text-transform: uppercase; font-size: 8pt;">Senior Citizens</div>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 14pt; color: #000000;"><?php echo $stats['total_pwd']; ?></div>
                            <div style="color: #000000; text-transform: uppercase; font-size: 8pt;">PWD</div>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 14pt; color: #000000;"><?php echo $stats['total_children']; ?></div>
                            <div style="color: #000000; text-transform: uppercase; font-size: 8pt;">Children (0-12)</div>
                        </div>
                    </div>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Purok</th>
                            <th>Occupation</th>
                            <th>Priority Groups</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($residents_data) && count($residents_data) > 0): ?>
                            <?php foreach ($residents_data as $resident): ?>
                                <tr>
                                    <td class="left">
                                        <?php 
                                        $fullName = $resident['first_name'] . ' ';
                                        if (!empty($resident['middle_name'])) {
                                            $fullName .= $resident['middle_name'] . ' ';
                                        }
                                        $fullName .= $resident['last_name'];
                                        echo htmlspecialchars($fullName); 
                                        ?>
                                    </td>
                                    <td class="center"><?php echo $resident['age']; ?></td>
                                    <td class="center"><?php echo $resident['gender']; ?></td>
                                    <td class="center"><?php echo htmlspecialchars($resident['purok']); ?></td>
                                    <td class="left"><?php echo htmlspecialchars($resident['occupation'] ?: '-'); ?></td>
                                    <td class="center" style="font-size: 7pt;">
                                        <?php
                                        $badges = [];
                                        if ($resident['is_senior']) $badges[] = 'Senior';
                                        if ($resident['is_pwd']) $badges[] = 'PWD';
                                        if ($resident['family_planning'] === 'Yes') $badges[] = 'FP';
                                        echo !empty($badges) ? implode(', ', $badges) : '-';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="center" style="font-style: italic; color: #000000;">
                                    No resident records found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Professional Footer Section -->
        <div class="footer-section">
            <!-- Document Information -->
            <div style="text-align: center; margin-bottom: 20pt;">
                <div class="title" style="font-size: 16pt; margin-bottom: 6pt;">
                    San Benito Health Center
                </div>
                <div class="subtitle" style="font-size: 13pt; margin-bottom: 8pt;">
                    Health Management Report
                </div>
                <div class="info" style="font-size: 10pt; line-height: 1.4;">
                    Barangay San Benito, Victoria, Laguna<br>
                    Report Generated: <?php echo date('F d, Y \a\t g:i A'); ?><br>
                    Prepared by: <?php echo htmlspecialchars($_SESSION['fullname']); ?>
                </div>
            </div>

            <!-- Certification Statement -->
            <div class="certification">
                <p>
                    <strong>CERTIFICATION:</strong> This document contains accurate and complete information regarding
                    the health management activities of San Benito Health Center as of the date of generation. All data
                    presented herein has been compiled from official health records and is certified to be true and
                    correct to the best of our knowledge and belief.
                </p>
            </div>

            <!-- Signature Section -->
            <div class="signatures">
                <div class="signature-block">
                    <div style="margin-bottom: 25pt;">
                        <div class="signature-line"></div>
                        <div class="signature-label">Health Worker</div>
                        <div style="margin-top: 3pt; font-size: 8pt; color: #000000;">Signature over Printed Name</div>
                    </div>
                    <div>
                        <span style="font-weight: 600;">Date:</span>
                        <span class="date-line"></span>
                    </div>
                </div>

                <div class="signature-block">
                    <div style="margin-bottom: 25pt;">
                        <div class="signature-line"></div>
                        <div class="signature-label">Officer-in-Charge</div>
                        <div style="margin-top: 3pt; font-size: 8pt; color: #000000;">Signature over Printed Name</div>
                    </div>
                    <div>
                        <span style="font-weight: 600;">Date:</span>
                        <span class="date-line"></span>
                    </div>
                </div>
            </div>

            <!-- Document Control -->
            <div class="document-control">
                <div style="font-weight: 600; margin-bottom: 4pt;">
                    Document Control No:
                    SBHC-<?php echo date('Y'); ?>-<?php echo str_pad(date('z'), 3, '0', STR_PAD_LEFT); ?>-<?php echo date('His'); ?>
                </div>
                <div>This is a computer-generated report. No signature is required unless specified otherwise.</div>
                <div style="margin-top: 4pt; font-size: 7pt;">
                    Generated on <?php echo date('F d, Y \a\t g:i:s A'); ?> | San Benito Health Center Management System
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-trigger print dialog when page loads - same as reports.php
        window.onload = function () {
            // Small delay to ensure page is fully rendered
            setTimeout(function () {
                window.print();
            }, 300);
        };

        // Handle print dialog events - consistent with reports.php behavior
        window.onbeforeprint = function () {
            console.log('Print dialog opened');
        };

        window.onafterprint = function () {
            console.log('Print dialog closed');
            // Don't auto-close window - let user decide
        };
    </script>

</body>

</html>