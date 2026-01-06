<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';
requireApproved();

// Get comprehensive statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM medicines) as total_medicines,
                (SELECT COUNT(*) FROM medicines WHERE quantity <= low_stock_threshold) as low_stock_medicines,
                (SELECT COUNT(*) FROM medicines WHERE expiry_date < CURDATE()) as expired_medicines,
                (SELECT COUNT(*) FROM medicines WHERE expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as expiring_soon_medicines,
                (SELECT COUNT(*) FROM babies) as total_babies,
                (SELECT COUNT(*) FROM vaccinations) + COALESCE((SELECT COUNT(*) FROM archived_vaccinations), 0) as total_vaccinations,
                COALESCE((SELECT COUNT(*) FROM archived_vaccinations WHERE status = 'completed'), 0) as completed_vaccinations,
                (SELECT COUNT(*) FROM vaccinations WHERE status IN ('pending', 'confirmed')) as pending_vaccinations,
                (SELECT COUNT(*) FROM appointments) + COALESCE((SELECT COUNT(*) FROM archived_appointments), 0) as total_appointments,
                (SELECT COUNT(*) FROM appointments WHERE status = 'confirmed') as confirmed_appointments,
                COALESCE((SELECT COUNT(*) FROM archived_appointments WHERE status = 'completed'), 0) as completed_appointments,
                (SELECT COUNT(*) FROM appointments WHERE status = 'pending') as pending_appointments";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get medicines with status
$medicines_result = mysqli_query($conn, "SELECT * FROM medicines ORDER BY 
                                        CASE WHEN quantity <= low_stock_threshold THEN 0 ELSE 1 END,
                                        CASE WHEN expiry_date < CURDATE() THEN 0 
                                             WHEN expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 
                                             ELSE 2 END,
                                        medicine_name ASC");

// Get babies with vaccination info (including archived)
$babies_result = mysqli_query($conn, "SELECT b.*, 
                                     (SELECT COUNT(*) FROM vaccinations v WHERE v.baby_id = b.id) + 
                                     COALESCE((SELECT COUNT(*) FROM archived_vaccinations av WHERE av.baby_id = b.id), 0) as total_vaccinations,
                                     COALESCE((SELECT COUNT(*) FROM archived_vaccinations av WHERE av.baby_id = b.id AND av.status = 'completed'), 0) as completed_vaccinations
                                     FROM babies b 
                                     ORDER BY b.full_name ASC");

// Get recent vaccinations (including active and archived - shows completed and cancelled)
$vaccinations_query = "
    (SELECT 
        v.id,
        v.baby_id,
        v.vaccine_type,
        v.schedule_date,
        v.administered_date,
        v.status,
        v.notes,
        b.full_name as baby_name,
        b.parent_guardian_name
    FROM vaccinations v 
    JOIN babies b ON v.baby_id = b.id)
    UNION ALL
    (SELECT 
        av.id,
        av.baby_id,
        av.vaccine_type,
        av.schedule_date,
        av.administered_date,
        av.status,
        av.notes,
        b.full_name as baby_name,
        b.parent_guardian_name
    FROM archived_vaccinations av
    JOIN babies b ON av.baby_id = b.id)
    ORDER BY schedule_date DESC
    LIMIT 20";
$vaccinations_result = mysqli_query($conn, $vaccinations_query);

// Debug: Check if query executed successfully
if (!$vaccinations_result) {
    error_log("Vaccination query error: " . mysqli_error($conn));
    echo "<!-- Vaccination Query Error: " . mysqli_error($conn) . " -->";
}

// Get appointments (including active and archived)
$appointments_query = "
    SELECT a.*, u.username 
    FROM appointments a 
    LEFT JOIN users u ON a.user_id = u.id 
    UNION ALL
    SELECT 
        aa.id, 
        aa.user_id, 
        aa.fullname, 
        aa.appointment_type, 
        aa.preferred_datetime, 
        aa.notes, 
        aa.status, 
        aa.created_at,
        u.username 
    FROM archived_appointments aa
    LEFT JOIN users u ON aa.user_id = u.id
    ORDER BY preferred_datetime DESC";
$appointments_result = mysqli_query($conn, $appointments_query);

// Get barangay residents data
$residents_query = "SELECT * FROM barangay_residents ORDER BY last_name, first_name";
$residents_result = mysqli_query($conn, $residents_query);

// Get residents statistics
$residents_stats_query = "SELECT 
                         COUNT(*) as total_residents,
                         COUNT(CASE WHEN is_senior = 1 THEN 1 END) as senior_citizens,
                         COUNT(CASE WHEN is_pwd = 1 THEN 1 END) as pwd_residents,
                         COUNT(CASE WHEN family_planning = 'Yes' THEN 1 END) as family_planning_participants,
                         COUNT(CASE WHEN age BETWEEN 0 AND 12 THEN 1 END) as children,
                         COUNT(CASE WHEN age BETWEEN 13 AND 59 THEN 1 END) as adults,
                         COUNT(CASE WHEN age >= 60 THEN 1 END) as elderly,
                         COUNT(CASE WHEN gender = 'Male' THEN 1 END) as male_residents,
                         COUNT(CASE WHEN gender = 'Female' THEN 1 END) as female_residents,
                         COUNT(CASE WHEN has_electricity = 1 THEN 1 END) as with_electricity,
                         COUNT(CASE WHEN has_poso = 1 OR has_nawasa = 1 THEN 1 END) as with_water_access,
                         COUNT(CASE WHEN has_cr = 1 THEN 1 END) as with_sanitation
                         FROM barangay_residents";
$residents_stats_result = mysqli_query($conn, $residents_stats_query);
$residents_stats = mysqli_fetch_assoc($residents_stats_result);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Reports</title>
    <link rel="icon" type="image/png" href="assets/img/san-benito-logo.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Navbar Styles -->
    <link rel="stylesheet" href="assets/css/navbar.css">
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="assets/css/residents_dashboard.css">
    <!-- Reports Styles -->
    <link rel="stylesheet" href="assets/css/reports.css">

    <style>

         body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%) !important;
            min-height: 100vh !important;
        }
        
        /* Professional Document Styles */
        .print-only {
            display: none;
        }

        /* Consistent Card Styling - Only for report content cards, not filter card */
        .card:not(.no-print) {
            border-radius: 8px !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            border: 1px solid #dee2e6 !important;
        }

        /* Keep filter card (Report Controls) with default Bootstrap styling */
        .card.no-print {
            /* Let it use default Bootstrap card styling */
        }

        /* Only apply header styling to report content cards */
        .card:not(.no-print) .card-header {
            background: #f8f9fa !important;
            border-bottom: 1px solid #dee2e6 !important;
            padding: 1rem 1.25rem !important;
        }

        .card:not(.no-print) .card-body {
            padding: 1.25rem !important;
        }

        /* Remove any extra containers or spacing */
        .table-card {
            margin-bottom: 1.5rem !important;
        }

        /* Ensure consistent card sizing */
        .container .card {
            width: 100% !important;
        }

        /* Fixed table layout for perfect column alignment */
        .table {
            width: 100% !important;
            table-layout: fixed !important;
            border-collapse: separate !important;
            border-spacing: 0 !important;
        }

        .table th,
        .table td {
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            padding: 0.75rem !important;
            vertical-align: middle !important;
            border-left: 1px solid #dee2e6 !important;
            border-right: 1px solid #dee2e6 !important;
        }

        /* Perfect column alignment - force exact widths */
        .table th:nth-child(1),
        .table td:nth-child(1) {
            width: 25% !important;
            min-width: 25% !important;
            max-width: 25% !important;
        }

        .table th:nth-child(2),
        .table td:nth-child(2) {
            width: 25% !important;
            min-width: 25% !important;
            max-width: 25% !important;
        }

        .table th:nth-child(3),
        .table td:nth-child(3) {
            width: 25% !important;
            min-width: 25% !important;
            max-width: 25% !important;
        }

        .table th:nth-child(4),
        .table td:nth-child(4) {
            width: 25% !important;
            min-width: 25% !important;
            max-width: 25% !important;
        }

        /* Enhanced visual alignment */
        .table-responsive {
            border: 1px solid #dee2e6 !important;
            border-radius: 0.375rem !important;
        }

        /* Consistent header styling across all tables */
        .table thead th {
            background-color: #f8f9fa !important;
            border-bottom: 2px solid #dee2e6 !important;
            font-weight: 600 !important;
            font-size: 0.875rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.025em !important;
            text-align: center !important;
        }

        /* Ensure consistent row heights */
        .table tbody tr {
            height: 60px !important;
        }

        .table tbody td {
            height: 60px !important;
            line-height: 1.4 !important;
        }

        .table td:nth-child(4) {
            width: 25% !important;
        }



        /* Override all external badge styles with high specificity */
        .table .badge,
        .table-responsive .badge,
        .card-body .badge {
            border-radius: 0 !important;
            font-weight: 400 !important;
            font-size: 12px !important;
            min-width: auto !important;
            text-align: center !important;
            display: inline-block !important;
            background: transparent !important;
            color: #495057 !important;
            border: 1px solid #dee2e6 !important;
            padding: 3px 8px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            box-shadow: none !important;
            background-image: none !important;
        }

        /* Specific badge colors with high specificity - more subtle */
        .table .badge.bg-danger,
        .table-responsive .badge.bg-danger,
        .card-body .badge.bg-danger {
            background: transparent !important;
            background-image: none !important;
            color: #dc3545 !important;
            border: 1px solid #dc3545 !important;
            box-shadow: none !important;
        }

        .table .badge.bg-warning,
        .table-responsive .badge.bg-warning,
        .card-body .badge.bg-warning {
            background: transparent !important;
            background-image: none !important;
            color: #fd7e14 !important;
            border: 1px solid #fd7e14 !important;
            box-shadow: none !important;
        }

        .table .badge.bg-success,
        .table-responsive .badge.bg-success,
        .card-body .badge.bg-success {
            background: transparent !important;
            background-image: none !important;
            color: #198754 !important;
            border: 1px solid #198754 !important;
            box-shadow: none !important;
        }

        .table .badge.bg-info,
        .table-responsive .badge.bg-info,
        .card-body .badge.bg-info {
            background: transparent !important;
            background-image: none !important;
            color: #0dcaf0 !important;
            border: 1px solid #0dcaf0 !important;
            box-shadow: none !important;
        }

        .table .badge.bg-primary,
        .table-responsive .badge.bg-primary,
        .card-body .badge.bg-primary {
            background: transparent !important;
            background-image: none !important;
            color: #0d6efd !important;
            border: 1px solid #0d6efd !important;
            box-shadow: none !important;
        }

        .table .badge.bg-secondary,
        .table-responsive .badge.bg-secondary,
        .card-body .badge.bg-secondary {
            background: transparent !important;
            background-image: none !important;
            color: #6c757d !important;
            border: 1px solid #6c757d !important;
            box-shadow: none !important;
        }

        /* Additional alignment improvements */
        .table .text-center {
            text-align: center !important;
        }

        .table .text-start {
            text-align: left !important;
        }

        /* Ensure all tables have the same visual structure */
        .table-card .table {
            margin-bottom: 0 !important;
        }

        /* Perfect column borders for visual separation */
        .table th:first-child,
        .table td:first-child {
            border-left: none !important;
        }

        .table th:last-child,
        .table td:last-child {
            border-right: none !important;
        }

        @media print {

            /* Hide all screen elements */
            .no-print,
            .print-hidden {
                display: none !important;
            }

            .print-only {
                display: block !important;
            }

            /* Show print header when printing */
            .print-header {
                display: block !important;
            }

            /* Ensure all content shows when printing */
            body.printing .statistics-section,
            body.printing #executive-summary-stats,
            body.printing #executive-summary-card,
            body.printing #problems-recommendations-card,
            body.printing #section-medicines,
            body.printing #section-babies,
            body.printing #section-vaccinations,
            body.printing #section-appointments,
            body.printing #section-residents {
                display: block !important;
                visibility: visible !important;
            }

            /* Professional document styling - remove all decorative elements */
            .fas,
            .fa,
            i[class*="fa"] {
                display: none !important;
            }

            /* Remove all rounded corners and shadows for formal document */
            .card,
            .card-header,
            .card-body,
            .table,
            th,
            td,
            .badge,
            .alert {
                border-radius: 0 !important;
                -webkit-border-radius: 0 !important;
                -moz-border-radius: 0 !important;
                box-shadow: none !important;
                -webkit-box-shadow: none !important;
                -moz-box-shadow: none !important;
            }

            /* Professional table styling */
            .table {
                border-collapse: collapse !important;
                border: 2pt solid #000000 !important;
            }

            .table th {
                border: 1pt solid #000000 !important;
                color: #000000 !important;
                background: #f8f9fa !important;
                font-weight: bold !important;
                text-align: center !important;
                padding: 8pt !important;
            }

            .table td {
                border: 1pt solid #000000 !important;
                color: #000000 !important;
                background: #ffffff !important;
                padding: 6pt !important;
            }

            /* Override colorful UI with professional print styling */
            .card-header {
                background: #f8f9fa !important;
                color: #000000 !important;
                border-bottom: 1pt solid #000000 !important;
            }

            .badge {
                background: #ffffff !important;
                color: #000000 !important;
                border: 1pt solid #000000 !important;
                padding: 2pt 4pt !important;
                font-size: 9pt !important;
                font-weight: normal !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5pt !important;
                border-radius: 0 !important;
            }

            /* Specific badge overrides for print */
            .badge.bg-danger,
            .badge.bg-warning,
            .badge.bg-success,
            .badge.bg-info,
            .badge.bg-primary {
                background: #ffffff !important;
                color: #000000 !important;
                border: 1pt solid #000000 !important;
            }

            /* Remove all background colors and force black text */
            .card-header h5,
            th,
            td,
            h5,
            h3,
            h6,
            .section-title,
            .card-header {
                color: #000000 !important;
                background: #ffffff !important;
            }

            /* Ensure all badges and status indicators are black text on white background */
            .badge,
            span[style*="background"] {
                background: #ffffff !important;
                color: #000000 !important;
                border: 1pt solid #000000 !important;
            }

            /* Remove colors from medicine status rows */
            .status-expired,
            .status-expiring,
            .status-low-stock,
            tr.status-expired,
            tr.status-expiring,
            tr.status-low-stock {
                background: #ffffff !important;
                color: #000000 !important;
            }

            /* Remove colors from all medicine-related elements */
            #section-medicines .badge,
            #section-medicines .status-expired,
            #section-medicines .status-expiring,
            #section-medicines .status-low-stock {
                background: #ffffff !important;
                color: #000000 !important;
                border: 1pt solid #000000 !important;
            }

            .card {
                border: 1pt solid #000000 !important;
                background: #ffffff !important;
            }

            .card-header {
                border-bottom: 1pt solid #000000 !important;
                background: #f8f9fa !important;
            }

            /* Respect filter selection when printing */
            body.printing .print-hidden {
                display: none !important;
                visibility: hidden !important;
            }

            /* Ensure filtered sections remain hidden during print */
            body.printing.filter-medicines #executive-summary-card,
            body.printing.filter-babies #executive-summary-card,
            body.printing.filter-vaccinations #executive-summary-card,
            body.printing.filter-appointments #executive-summary-card {
                display: none !important;
            }

            body.printing.filter-summary #section-medicines,
            body.printing.filter-summary #section-babies,
            body.printing.filter-summary #section-vaccinations,
            body.printing.filter-summary #section-appointments,
            body.printing.filter-summary #section-residents {
                display: none !important;
            }

            body.printing.filter-medicines #section-babies,
            body.printing.filter-medicines #section-vaccinations,
            body.printing.filter-medicines #section-appointments,
            body.printing.filter-medicines #section-residents {
                display: none !important;
            }

            body.printing.filter-babies #section-medicines,
            body.printing.filter-babies #section-vaccinations,
            body.printing.filter-babies #section-appointments,
            body.printing.filter-babies #section-residents {
                display: none !important;
            }

            body.printing.filter-vaccinations #section-medicines,
            body.printing.filter-vaccinations #section-babies,
            body.printing.filter-vaccinations #section-appointments,
            body.printing.filter-vaccinations #section-residents {
                display: none !important;
            }

            body.printing.filter-appointments #section-medicines,
            body.printing.filter-appointments #section-babies,
            body.printing.filter-appointments #section-vaccinations,
            body.printing.filter-appointments #section-residents {
                display: none !important;
            }

            body.printing.filter-residents #section-medicines,
            body.printing.filter-residents #section-babies,
            body.printing.filter-residents #section-vaccinations,
            body.printing.filter-residents #section-appointments {
                display: none !important;
            }

            /* Hide navbar and other screen-only elements */
            nav,
            .navbar,
            .btn,
            button {
                display: none !important;
            }

            /* Professional print styling */
            body {
                font-family: 'Times New Roman', serif !important;
                font-size: 11pt !important;
                line-height: 1.3 !important;
                color: #000000 !important;
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            body.printing {
                margin: 0 !important;
                padding: 0 !important;
            }

            body.printing .container {
                margin: 0 !important;
                padding: 0.1in !important;
                max-width: none !important;
            }

            /* Professional headings */
            h1,
            h2,
            h3,
            h4,
            h5,
            h6 {
                font-family: 'Times New Roman', serif !important;
                color: #000000 !important;
                font-weight: bold !important;
                text-transform: uppercase !important;
                letter-spacing: 1pt !important;
            }

            .report-container {
                padding: 0 !important;
                margin: 0 !important;
                max-width: none !important;
                background: white !important;
                box-shadow: none !important;
            }

            .print-header {
                text-align: center;
                margin-bottom: 0.5rem;
                padding-bottom: 0.3rem;
                border-bottom: 2px solid #000000;
            }

            .print-logo {
                width: 60px;
                height: 60px;
                margin-bottom: 0.3rem;
            }

            .print-title {
                font-size: 18pt;
                font-weight: bold;
                color: #000000;
                margin: 0;
                text-transform: uppercase;
            }

            .print-subtitle {
                font-size: 14pt;
                color: #333333;
                margin: 0.5rem 0;
            }

            .print-info {
                font-size: 10pt;
                color: #666666;
                margin-top: 0.3rem;
            }

            .section-title {
                font-size: 12pt !important;
                font-weight: bold !important;
                color: #000000 !important;
                margin: 0.2rem 0 0.2rem 0 !important;
                padding-bottom: 0.2rem !important;
                border-bottom: 2pt solid #000000 !important;
                text-transform: uppercase !important;
                letter-spacing: 1pt !important;
                page-break-after: avoid !important;
                page-break-before: auto !important;
                font-family: 'Times New Roman', serif !important;
            }

            .table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-bottom: 0.3rem !important;
                font-size: 9pt !important;
                font-family: 'Times New Roman', serif !important;
                color: #000000 !important;
                border-radius: 0 !important;
                page-break-inside: auto !important;
            }

            .table th {
                background-color: #f8f9fa !important;
                background: #f8f9fa !important;
                border: 1pt solid #000000 !important;
                padding: 6pt !important;
                text-align: center !important;
                font-weight: bold !important;
                font-size: 9pt !important;
                color: #000000 !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5pt !important;
                border-radius: 0 !important;
                -webkit-print-color-adjust: exact !important;
                font-family: 'Times New Roman', serif !important;
            }

            .table td {
                border: 1pt solid #000000 !important;
                padding: 4pt !important;
                vertical-align: middle !important;
                line-height: 1.2 !important;
                color: #000000 !important;
                background-color: #ffffff !important;
                background: #ffffff !important;
                border-radius: 0 !important;
                font-family: 'Times New Roman', serif !important;
            }

            .table tbody tr {
                background-color: #ffffff !important;
                background: #ffffff !important;
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }

            .table tbody tr:nth-child(even) {
                background-color: #ffffff !important;
                background: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
            }

            /* Allow table headers to repeat on new pages */
            .table thead {
                display: table-header-group !important;
            }

            .table tbody {
                display: table-row-group !important;
            }

            /* Prevent orphaned table headers */
            .table thead tr {
                page-break-after: avoid !important;
            }

            /* Allow cards to break across pages but keep headers with content */
            .card {
                page-break-inside: auto !important;
            }

            .card-header {
                page-break-after: avoid !important;
            }

            /* Ensure at least 3 rows stay together after a header */
            .table tbody tr:nth-child(-n+3) {
                page-break-before: avoid !important;
            }

            /* Optimize table layout for printing */
            .table-responsive {
                page-break-inside: auto !important;
                overflow: visible !important;
            }

            /* Reduce margins and padding for better space utilization */
            .card-body {
                padding: 0.5rem !important;
            }

            .card {
                margin-bottom: 0.5rem !important;
            }

            /* Allow flexible row heights for better fitting */
            .table tbody td {
                height: auto !important;
                min-height: auto !important;
                line-height: 1.1 !important;
                padding: 3pt !important;
            }

            /* Prevent widows and orphans in table content */
            .table tbody tr {
                orphans: 2 !important;
                widows: 2 !important;
            }

            .badge {
                background: transparent !important;
                color: #000000 !important;
                border: 1pt solid #000000 !important;
                padding: 1pt 3pt !important;
                font-size: 8pt !important;
                font-weight: normal !important;
                text-transform: uppercase !important;
                letter-spacing: 0.3pt !important;
                border-radius: 0 !important;
            }

            /* Professional status indicators */
            .alert {
                border: 1pt solid #000000 !important;
                background: #ffffff !important;
                color: #000000 !important;
                padding: 8pt !important;
                margin: 4pt 0 !important;
            }

            .alert-success {
                border-left: 3pt solid #000000 !important;
            }

            .alert-warning {
                border-left: 3pt solid #666666 !important;
            }

            .alert-danger {
                border-left: 3pt solid #333333 !important;
            }

            .alert-info {
                border-left: 3pt solid #999999 !important;
            }

            .stats-card {
                border: 1px solid #cccccc !important;
                box-shadow: none !important;
                background: white !important;
                page-break-inside: auto !important;
            }

            .stats-icon {
                background: #f0f0f0 !important;
                color: #000000 !important;
                box-shadow: none !important;
            }

            .stats-number {
                color: #000000 !important;
            }

            .stats-label {
                color: #666666 !important;
            }
        }

        @page {
            margin: 0.25in;
            size: letter;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            /* Hide welcome section subtitle on mobile */
            .welcome-subtitle {
                display: none !important;
            }

            /* Make card titles single line on mobile */
            .card-header h5 {
                font-size: 14px !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                line-height: 1.2 !important;
            }

            /* Adjust card header padding on mobile */
            .card-header {
                padding: 0.75rem 1rem !important;
            }

            /* Fix table layout on mobile - remove fixed widths */
            .table {
                table-layout: auto !important;
                width: 100% !important;
            }

            /* Make table headers single line on mobile */
            .table th {
                font-size: 10px !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                padding: 0.4rem 0.2rem !important;
                line-height: 1.1 !important;
                vertical-align: middle !important;
                width: auto !important;
                min-width: auto !important;
                max-width: none !important;
            }

            /* Adjust table cell padding on mobile and allow wrapping for content */
            .table td {
                font-size: 11px !important;
                padding: 0.4rem 0.2rem !important;
                line-height: 1.2 !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                white-space: normal !important;
                width: auto !important;
                min-width: auto !important;
                max-width: none !important;
            }

            /* Remove fixed column widths on mobile */
            .table th:nth-child(1),
            .table td:nth-child(1),
            .table th:nth-child(2),
            .table td:nth-child(2),
            .table th:nth-child(3),
            .table td:nth-child(3),
            .table th:nth-child(4),
            .table td:nth-child(4),
            .table th:nth-child(5),
            .table td:nth-child(5) {
                width: auto !important;
                min-width: auto !important;
                max-width: none !important;
            }

            /* Make badges smaller on mobile */
            .table .badge {
                font-size: 9px !important;
                padding: 2px 4px !important;
                white-space: nowrap !important;
            }

            /* Adjust welcome section on mobile */
            .welcome-title {
                font-size: 1.5rem !important;
            }

            .welcome-date {
                font-size: 12px !important;
                margin-top: 10px !important;
            }

            /* Make report controls more compact on mobile */
            .card.no-print .card-body {
                padding: 1rem 0.75rem !important;
            }

            /* Adjust form controls on mobile */
            .form-select,
            .form-control {
                font-size: 14px !important;
            }

            /* Make buttons more touch-friendly */
            .btn {
                padding: 0.5rem 0.75rem !important;
                font-size: 14px !important;
            }

            /* Adjust statistics cards on mobile */
            .stats-card {
                margin-bottom: 0.5rem !important;
            }

            /* Make executive summary table more compact */
            .card-body table.table {
                font-size: 10px !important;
            }

            .card-body table.table th,
            .card-body table.table td {
                padding: 0.3rem 0.15rem !important;
                font-size: 10px !important;
            }

            /* Adjust residents statistics cards */
            .card.bg-primary,
            .card.bg-info,
            .card.bg-warning,
            .card.bg-success {
                margin-bottom: 0.5rem !important;
            }

            .card.bg-primary .card-body,
            .card.bg-info .card-body,
            .card.bg-warning .card-body,
            .card.bg-success .card-body {
                padding: 0.75rem !important;
            }

            /* Make utility access cards more compact */
            .border.rounded.p-2 {
                padding: 0.5rem !important;
                font-size: 12px !important;
            }

            /* Adjust container padding on mobile */
            .container {
                padding-left: 10px !important;
                padding-right: 10px !important;
            }

            /* Make table responsive wrapper more compact */
            .table-responsive {
                font-size: 10px !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            /* Adjust print button on mobile */
            .btn-primary {
                white-space: nowrap !important;
            }

            /* Fix table row heights on mobile */
            .table tbody tr {
                height: auto !important;
            }

            .table tbody td {
                height: auto !important;
                min-height: 40px !important;
            }

            /* Improve mobile table scrolling */
            .table-responsive {
                border: 1px solid #dee2e6 !important;
                border-radius: 0.375rem !important;
                margin-bottom: 1rem !important;
            }

            /* Stack form controls vertically on mobile */
            .row .col-md-6 {
                margin-bottom: 1rem !important;
            }

            /* Make date filter inputs stack properly */
            .col-md-5 {
                margin-bottom: 0.5rem !important;
            }

            /* Adjust executive summary header on mobile */
            .card-body div[style*="display: flex"] {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.5rem !important;
            }

            /* Make health score badge responsive */
            .card-body span[style*="background:"] {
                display: block !important;
                margin-top: 0.5rem !important;
                margin-left: 0 !important;
                text-align: center !important;
                width: fit-content !important;
            }
        }

        /* Extra small mobile devices */
        @media (max-width: 576px) {
            /* Further reduce font sizes for very small screens */
            .card-header h5 {
                font-size: 12px !important;
            }

            .table th {
                font-size: 9px !important;
                padding: 0.3rem 0.15rem !important;
            }

            .table td {
                font-size: 10px !important;
                padding: 0.3rem 0.15rem !important;
            }

            .table .badge {
                font-size: 8px !important;
                padding: 1px 3px !important;
            }

            /* Make welcome title smaller on very small screens */
            .welcome-title {
                font-size: 1.25rem !important;
            }

            /* Adjust form layout for very small screens */
            .row .col-md-6 {
                margin-bottom: 1rem !important;
            }

            /* Stack date filter inputs vertically on very small screens */
            .col-md-5 {
                margin-bottom: 0.5rem !important;
            }

            /* Make executive summary even more compact */
            .card-body table.table th,
            .card-body table.table td {
                padding: 0.25rem 0.1rem !important;
                font-size: 9px !important;
            }

            /* Reduce container padding further */
            .container {
                padding-left: 5px !important;
                padding-right: 5px !important;
            }

            /* Make cards more compact */
            .card-body {
                padding: 0.75rem !important;
            }

            /* Adjust button sizes for very small screens */
            .btn {
                padding: 0.4rem 0.6rem !important;
                font-size: 12px !important;
            }

            /* Make statistics cards stack better */
            .col-md-3 {
                margin-bottom: 0.75rem !important;
            }

            /* Improve small screen table readability */
            .table-responsive {
                font-size: 9px !important;
            }

            /* Make health priority badges wrap better */
            .d-flex.flex-wrap.gap-1 {
                gap: 0.25rem !important;
            }

            .d-flex.flex-wrap.gap-1 .badge {
                font-size: 7px !important;
                padding: 1px 2px !important;
            }
        }
    </style>

</head>

<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="welcome-section mb-5 no-print">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="welcome-title">
                        <i class="fas fa-chart-bar me-2 text-success"></i>
                        Health Reports & Analytics
                    </h1>
                    <p class="welcome-subtitle">Generate comprehensive health center reports and analytics</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="welcome-date">
                        <i class="fas fa-calendar-day me-2"></i>
                        <?php
                        date_default_timezone_set('Asia/Manila');
                        echo date('l, F j, Y');
                        ?>
                        <br>
                        <i class="fas fa-clock me-2"></i>
                        <?php echo date('g:i A'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Controls Card -->
        <div class="card mb-4 table-card no-print">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-cogs me-2"></i>Report Controls
                </h5>
                <div>
                    <button onclick="printCurrentReport()" class="btn btn-primary me-2">
                        <i class="fas fa-print me-1"></i>Print Report
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="reportFilter" class="form-label fw-semibold">Select Report Category:</label>
                        <select id="reportFilter" class="form-select" onchange="filterReport()">
                            <option value="all" selected>Complete Report</option>
                            <option value="summary">Executive Summary</option>
                            <option value="medicines">Medicine Inventory</option>
                            <option value="babies">Baby Records</option>
                            <option value="vaccinations">Vaccination Records</option>
                            <option value="appointments">Appointment Records</option>
                            <option value="residents">Barangay Residents</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Date Filter:</label>
                        <div class="row">
                            <div class="col-md-5">
                                <input type="date" id="dateFrom" class="form-control form-control-sm"
                                    oninput="applyDateFilter()">
                                <small class="text-muted">From</small>
                            </div>
                            <div class="col-md-5">
                                <input type="date" id="dateTo" class="form-control form-control-sm"
                                    oninput="applyDateFilter()">
                                <small class="text-muted">To</small>
                            </div>
                            <div class="col-md-2 pt-1">
                                <button onclick="resetDateFilter()" class="btn btn-outline-secondary w-100"
                                    title="Clear dates">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Report Content -->
        <div class="report-container" id="reportContent">

            <!-- Report Header (Print Only) -->
            <div class="print-header print-only">
                <img src="assets/img/san-benito-logo.png" alt="Logo" class="print-logo">
                <h1 class="print-title">San Benito Health Center</h1>
                <h2 class="print-subtitle">Health Management Report</h2>
                <div class="print-info">
                    <div>Barangay San Benito, Victoria, Laguna</div>
                    <div>Report Generated: <?php echo date('F d, Y \a\t g:i A'); ?></div>
                    <div>Prepared by: <?php echo htmlspecialchars($_SESSION['fullname']); ?></div>
                </div>
            </div>

            <!-- Executive Summary Card -->
            <div class="card mb-4 table-card" id="executive-summary-card">
                <div class="card-header" style="background: #f8f9fa; color: #495057; border-bottom: 1px solid #dee2e6;">
                    <h5 class="mb-0" style="font-weight: 600;">
                        <i class="fas fa-chart-line me-2 text-primary"></i>Executive Summary
                    </h5>
                </div>
                <div class="card-body" style="background: #ffffff;">
                    <?php
                    // Enhanced KPI Calculations with Thresholds
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

                    <!-- Report Header -->
                    <div class="row mb-4" style="border-bottom: 1px solid #dee2e6; padding-bottom: 15px;">
                        <div class="col-md-12">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                                <div style="color: #2c3e50; font-size: 14px; font-weight: 500;">
                                    <strong>Report Period:</strong> <?php echo date('F Y'); ?>
                                </div>
                                <div style="color: #2c3e50; font-size: 14px; font-weight: 500;">
                                    <strong>Generated:</strong> <?php echo date('M d, Y'); ?>
                                </div>
                                <div style="color: #2c3e50; font-size: 14px; font-weight: 500;">
                                    <strong>Overall Health Score:</strong>
                                    <span
                                        style="background: <?php echo $health_score >= 85 ? '#28a745' : ($health_score >= 70 ? '#6c757d' : '#495057'); ?>; color: white; padding: 4px 12px; border-radius: 4px; font-weight: 600; margin-left: 8px;">
                                        <?php echo $health_score; ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Professional KPI Table -->
                    <div class="row">
                        <div class="col-md-12">
                            <table class="table table-bordered" style="margin-bottom: 20px;">
                                <thead style="background: #f8f9fa;">
                                    <tr>
                                        <th
                                            style="color: #2c3e50; font-weight: 600; text-align: center; border: 1px solid #dee2e6;">
                                            Performance Indicator</th>
                                        <th
                                            style="color: #2c3e50; font-weight: 600; text-align: center; border: 1px solid #dee2e6;">
                                            Current Value</th>
                                        <th
                                            style="color: #2c3e50; font-weight: 600; text-align: center; border: 1px solid #dee2e6;">
                                            Assessment</th>
                                        <th
                                            style="color: #2c3e50; font-weight: 600; text-align: center; border: 1px solid #dee2e6;">
                                            Target</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="color: #2c3e50; border: 1px solid #dee2e6; padding: 12px;">
                                            Vaccination Completion Rate</td>
                                        <td
                                            style="color: #2c3e50; border: 1px solid #dee2e6; text-align: center; font-weight: 600;">
                                            <?php echo $vaccination_rate; ?>%
                                        </td>
                                        <td style="color: #2c3e50; border: 1px solid #dee2e6; text-align: center;">
                                            <span
                                                style="background: <?php echo $vaccination_rate >= $kpi_thresholds['vaccination_rate_good'] ? '#6c757d' : ($vaccination_rate >= $kpi_thresholds['vaccination_rate_fair'] ? '#6c757d' : '#495057'); ?>; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px;">
                                                <?php echo $vaccination_rate >= $kpi_thresholds['vaccination_rate_good'] ? 'Excellent' : ($vaccination_rate >= $kpi_thresholds['vaccination_rate_fair'] ? 'Satisfactory' : 'Below Standard'); ?>
                                            </span>
                                        </td>
                                        <td style="color: #2c3e50; border: 1px solid #dee2e6; text-align: center;">≥90%
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="color: #2c3e50; border: 1px solid #dee2e6; padding: 12px;">
                                            Appointment Completion Rate</td>
                                        <td
                                            style="color: #2c3e50; border: 1px solid #dee2e6; text-align: center; font-weight: 600;">
                                            <?php echo $appointment_completion_rate; ?>%
                                        </td>
                                        <td style="color: #2c3e50; border: 1px solid #dee2e6; text-align: center;">
                                            <span
                                                style="background: <?php echo $appointment_completion_rate >= 80 ? '#6c757d' : ($appointment_completion_rate >= 60 ? '#6c757d' : '#495057'); ?>; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px;">
                                                <?php echo $appointment_completion_rate >= 80 ? 'Satisfactory' : ($appointment_completion_rate >= 60 ? 'Acceptable' : 'Below Standard'); ?>
                                            </span>
                                        </td>
                                        <td style="color: #2c3e50; border: 1px solid #dee2e6; text-align: center;">≥80%
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="color: #2c3e50; border: 1px solid #dee2e6; padding: 12px;">Inventory
                                            Management Score</td>
                                        <td
                                            style="color: #2c3e50; border: 1px solid #dee2e6; text-align: center; font-weight: 600;">
                                            <?php echo $inventory_health_score; ?>%
                                        </td>
                                        <td style="color: #2c3e50; border: 1px solid #dee2e6; text-align: center;">
                                            <span
                                                style="background: <?php echo $inventory_health_score >= 95 ? '#6c757d' : ($inventory_health_score >= 85 ? '#6c757d' : '#495057'); ?>; color: white; padding: 4px 8px; border-radius: 3px; font-size: 12px;">
                                                <?php echo $inventory_health_score >= 95 ? 'Excellent' : ($inventory_health_score >= 85 ? 'Satisfactory' : 'Critical'); ?>
                                            </span>
                                        </td>
                                        <td style="color: #2c3e50; border: 1px solid #dee2e6; text-align: center;">≥95%
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary Statistics -->
                    <div class="row">
                        <div class="col-md-6">
                            <h6
                                style="color: #2c3e50; font-weight: 600; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px;">
                                Service Statistics</h6>
                            <table class="table table-sm" style="font-size: 13px;">
                                <tbody>
                                    <tr>
                                        <td style="color: #2c3e50; border: none; padding: 8px 0;">Total Medicines in
                                            Inventory</td>
                                        <td style="color: #2c3e50; border: none; text-align: right; font-weight: 600;">
                                            <?php echo $stats['total_medicines']; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="color: #2c3e50; border: none; padding: 8px 0;">Completed Vaccinations
                                        </td>
                                        <td style="color: #2c3e50; border: none; text-align: right; font-weight: 600;">
                                            <?php echo $stats['completed_vaccinations']; ?>/<?php echo $stats['total_vaccinations']; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="color: #2c3e50; border: none; padding: 8px 0;">Registered Babies</td>
                                        <td style="color: #2c3e50; border: none; text-align: right; font-weight: 600;">
                                            <?php echo $stats['total_babies']; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="color: #2c3e50; border: none; padding: 8px 0;">Total Appointments
                                        </td>
                                        <td style="color: #2c3e50; border: none; text-align: right; font-weight: 600;">
                                            <?php echo $stats['total_appointments']; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>



            <!-- Medicine Inventory Overview -->
            <div class="card mb-4 table-card" id="section-medicines">
                <div class="card-header" style="background: #f8f9fa; color: #495057; border-bottom: 1px solid #dee2e6;">
                    <h5 class="mb-0" style="font-weight: 600;">
                        <i class="fas fa-pills me-2 text-info"></i>Medicine Inventory Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 20%;">Medicine Name</th>
                                    <th class="text-center" style="width: 20%;">Dosage</th>
                                    <th class="text-center" style="width: 20%;">Quantity</th>
                                    <th class="text-center" style="width: 20%;">Expiry Date</th>
                                    <th class="text-center" style="width: 20%;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($medicines_result && mysqli_num_rows($medicines_result) > 0): ?>
                                    <?php $count = 0;
                                    while (($medicine = mysqli_fetch_assoc($medicines_result)) && $count < 10):
                                        $count++; ?>
                                        <?php
                                        $lowStock = $medicine['quantity'] <= $medicine['low_stock_threshold'];
                                        $expired = strtotime($medicine['expiry_date']) < strtotime(date('Y-m-d'));
                                        $expiringSoon = !$expired && strtotime($medicine['expiry_date']) <= strtotime('+30 days');

                                        $rowClass = '';
                                        $status = 'Normal';
                                        if ($expired) {
                                            $rowClass = 'status-expired';
                                            $status = 'Expired';
                                        } elseif ($expiringSoon) {
                                            $rowClass = 'status-expiring';
                                            $status = 'Expiring Soon';
                                        } elseif ($lowStock) {
                                            $rowClass = 'status-low-stock';
                                            $status = 'Low Stock';
                                        }
                                        ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td class="text-center"><?php echo htmlspecialchars($medicine['medicine_name']); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo htmlspecialchars($medicine['dosage'] ?? 'Not specified'); ?>
                                            </td>
                                            <td class="text-center"><?php echo $medicine['quantity']; ?></td>
                                            <td class="text-center">
                                                <?php echo date('M d, Y', strtotime($medicine['expiry_date'])); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($expired): ?>
                                                    <span class="badge bg-danger">Expired</span>
                                                <?php elseif ($expiringSoon): ?>
                                                    <span class="badge bg-warning text-dark">Expiring Soon</span>
                                                <?php elseif ($lowStock): ?>
                                                    <span class="badge bg-info">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Normal</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php if (mysqli_num_rows($medicines_result) > 10): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <em>Showing first 10 entries. Use filter above to view all medicines or print
                                                    for
                                                    complete report.</em>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-exclamation-circle text-muted me-2"></i>
                                            No medicines found in inventory
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Baby Records Overview -->
            <div class="card mb-4 table-card" id="section-babies">
                <div class="card-header" style="background: #f8f9fa; color: #495057; border-bottom: 1px solid #dee2e6;">
                    <h5 class="mb-0" style="font-weight: 600;">
                        <i class="fas fa-baby me-2 text-success"></i>Baby Records Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 25%;">Baby Name</th>
                                    <th class="text-center" style="width: 25%;">Birth Date</th>
                                    <th class="text-center" style="width: 25%;">Parent/Guardian</th>
                                    <th class="text-center" style="width: 25%;">Vaccinations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($babies_result && mysqli_num_rows($babies_result) > 0): ?>
                                    <?php $count = 0;
                                    while (($baby = mysqli_fetch_assoc($babies_result)) && $count < 10):
                                        $count++; ?>
                                        <tr>
                                            <td class="text-center"><?php echo htmlspecialchars($baby['full_name']); ?></td>
                                            <td class="text-center">
                                                <?php echo date('M d, Y', strtotime($baby['date_of_birth'])); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo htmlspecialchars($baby['parent_guardian_name']); ?>
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="badge bg-primary"><?php echo $baby['completed_vaccinations']; ?>/<?php echo $baby['total_vaccinations']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php if (mysqli_num_rows($babies_result) > 10): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <em>Showing first 10 entries. Use filter above to view all baby records or print
                                                    for
                                                    complete report.</em>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <i class="fas fa-exclamation-circle text-muted me-2"></i>
                                            No baby records found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Vaccination Records Overview -->
            <div class="card mb-4 table-card" id="section-vaccinations">
                <div class="card-header" style="background: #f8f9fa; color: #495057; border-bottom: 1px solid #dee2e6;">
                    <h5 class="mb-0" style="font-weight: 600;">
                        <i class="fas fa-syringe me-2 text-primary"></i>Vaccination Records Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 25%;">Baby Name</th>
                                    <th class="text-center" style="width: 25%;">Vaccine Type</th>
                                    <th class="text-center" style="width: 25%;">Schedule Date</th>
                                    <th class="text-center" style="width: 25%;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($vaccinations_result && mysqli_num_rows($vaccinations_result) > 0): ?>
                                    <?php $count = 0;
                                    while (($vaccination = mysqli_fetch_assoc($vaccinations_result)) && $count < 10):
                                        $count++; ?>
                                        <tr>
                                            <td class="text-center"><?php echo htmlspecialchars($vaccination['baby_name']); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo htmlspecialchars($vaccination['vaccine_type']); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo date('M d, Y', strtotime($vaccination['schedule_date'])); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                // Get status and determine badge class
                                                $status = strtolower(trim($vaccination['status']));
                                                $badgeClass = 'bg-secondary';
                                                $displayStatus = ucfirst($status);
                                                
                                                if ($status === 'completed') {
                                                    $badgeClass = 'bg-success';
                                                    $displayStatus = 'Completed';
                                                } elseif ($status === 'cancelled') {
                                                    $badgeClass = 'bg-danger';
                                                    $displayStatus = 'Cancelled';
                                                } elseif ($status === 'pending' || $status === 'confirmed') {
                                                    $badgeClass = 'bg-warning text-dark';
                                                    $displayStatus = ucfirst($status);
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>">
                                                    <?php echo $displayStatus; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php if (mysqli_num_rows($vaccinations_result) > 10): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <em>Showing recent 10 entries. Use filter above to view all vaccination records
                                                    or
                                                    print for complete report.</em>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <i class="fas fa-exclamation-circle text-muted me-2"></i>
                                            No vaccination records found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Appointment Records Overview -->
            <div class="card mb-4 table-card" id="section-appointments">
                <div class="card-header" style="background: #f8f9fa; color: #495057; border-bottom: 1px solid #dee2e6;">
                    <h5 class="mb-0" style="font-weight: 600;">
                        <i class="fas fa-calendar-check me-2 text-secondary"></i>Appointment Records Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 25%;">Patient Name</th>
                                    <th class="text-center" style="width: 25%;">Type</th>
                                    <th class="text-center" style="width: 25%;">Date & Time</th>
                                    <th class="text-center" style="width: 25%;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($appointments_result && mysqli_num_rows($appointments_result) > 0): ?>
                                    <?php $count = 0;
                                    while ($appointment = mysqli_fetch_assoc($appointments_result)):
                                        if ($count >= 10)
                                            break;
                                        $count++; ?>
                                        <tr>
                                            <td class="text-center"><?php echo htmlspecialchars($appointment['fullname']); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php 
                                                $appointmentType = !empty($appointment['appointment_type']) ? $appointment['appointment_type'] : 'General';
                                                ?>
                                                <span
                                                    class="badge <?php echo $appointmentType == 'Vaccination' ? 'bg-primary' : 'bg-info'; ?>">
                                                    <?php echo htmlspecialchars($appointmentType); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php echo date('M d, Y g:i A', strtotime($appointment['preferred_datetime'])); ?>
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="badge <?php echo $appointment['status'] == 'completed' ? 'bg-success' : ($appointment['status'] == 'cancelled' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <?php if (mysqli_num_rows($appointments_result) > 10): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <em>Showing recent 10 entries. Use filter above to view all appointments or
                                                    print
                                                    for complete report.</em>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <i class="fas fa-exclamation-circle text-muted me-2"></i>
                                            No appointments scheduled
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Barangay Residents Overview -->
            <div class="card mb-4 table-card" id="section-residents">
                <div class="card-header" style="background: #f8f9fa; color: #495057; border-bottom: 1px solid #dee2e6;">
                    <h5 class="mb-0" style="font-weight: 600;">
                        <i class="fas fa-users me-2 text-success"></i>Barangay Residents Overview
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Residents Statistics Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-1"><?php echo $residents_stats['total_residents']; ?></h4>
                                    <small>Total Residents</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-1"><?php echo $residents_stats['senior_citizens']; ?></h4>
                                    <small>Senior Citizens</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-1"><?php echo $residents_stats['pwd_residents']; ?></h4>
                                    <small>PWD Residents</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h4 class="mb-1"><?php echo $residents_stats['family_planning_participants']; ?></h4>
                                    <small>Family Planning</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Demographics Breakdown -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Age Distribution</h6>
                            <div class="row">
                                <div class="col-4 text-center">
                                    <div class="border rounded p-2">
                                        <strong><?php echo $residents_stats['children']; ?></strong>
                                        <br><small>Children (0-12)</small>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="border rounded p-2">
                                        <strong><?php echo $residents_stats['adults']; ?></strong>
                                        <br><small>Adults (13-59)</small>
                                    </div>
                                </div>
                                <div class="col-4 text-center">
                                    <div class="border rounded p-2">
                                        <strong><?php echo $residents_stats['elderly']; ?></strong>
                                        <br><small>Elderly (60+)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">Gender Distribution</h6>
                            <div class="row">
                                <div class="col-6 text-center">
                                    <div class="border rounded p-2">
                                        <strong><?php echo $residents_stats['male_residents']; ?></strong>
                                        <br><small>Male</small>
                                    </div>
                                </div>
                                <div class="col-6 text-center">
                                    <div class="border rounded p-2">
                                        <strong><?php echo $residents_stats['female_residents']; ?></strong>
                                        <br><small>Female</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Utilities Access -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h6 class="fw-bold mb-3">Utilities & Infrastructure Access</h6>
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <div class="border rounded p-2">
                                        <strong><?php echo $residents_stats['with_electricity']; ?></strong>
                                        <br><small>With Electricity</small>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="border rounded p-2">
                                        <strong><?php echo $residents_stats['with_water_access']; ?></strong>
                                        <br><small>With Water Access</small>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="border rounded p-2">
                                        <strong><?php echo $residents_stats['with_sanitation']; ?></strong>
                                        <br><small>With Sanitation</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Residents Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 25%;">Full Name</th>
                                    <th class="text-center" style="width: 15%;">Age/Gender</th>
                                    <th class="text-center" style="width: 15%;">Purok</th>
                                    <th class="text-center" style="width: 20%;">Occupation</th>
                                    <th class="text-center" style="width: 25%;">Health Priority Groups</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($residents_result && mysqli_num_rows($residents_result) > 0): ?>
                                    <?php while ($resident = mysqli_fetch_assoc($residents_result)): ?>
                                        <tr>
                                            <td class="text-center">
                                                <strong><?php echo htmlspecialchars($resident['last_name'] . ', ' . $resident['first_name']); ?></strong>
                                                <?php if (!empty($resident['middle_name'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($resident['middle_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <strong><?php echo $resident['age']; ?> years</strong>
                                                <br><small class="text-muted"><?php echo $resident['gender']; ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?php echo htmlspecialchars($resident['purok']); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php echo htmlspecialchars($resident['occupation'] ?: 'N/A'); ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex flex-wrap gap-1 justify-content-center">
                                                    <?php if ($resident['is_senior']): ?>
                                                        <span class="badge bg-warning text-dark">Senior</span>
                                                    <?php endif; ?>
                                                    <?php if ($resident['is_pwd']): ?>
                                                        <span class="badge bg-info">PWD</span>
                                                    <?php endif; ?>
                                                    <?php if ($resident['family_planning'] === 'Yes'): ?>
                                                        <span class="badge bg-success">Family Planning</span>
                                                    <?php endif; ?>
                                                    <?php if (!$resident['is_senior'] && !$resident['is_pwd'] && $resident['family_planning'] !== 'Yes'): ?>
                                                        <span class="text-muted small">Regular</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-exclamation-circle text-muted me-2"></i>
                                            No residents found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hospital Footer Section (Print Only) -->
    <div class="print-only"
        style="margin-top: 30pt; page-break-inside: avoid; border-top: 2pt solid #000000; padding-top: 15pt;">
        <!-- Document Information -->
        <div style="text-align: center; font-family: 'Times New Roman', serif; margin-bottom: 20pt;">
            <div
                style="font-size: 16pt; font-weight: bold; color: #000000; margin: 0; text-transform: uppercase; letter-spacing: 0.5pt;">
                San Benito Health Center
            </div>
            <div style="font-size: 13pt; color: #333333; margin: 3pt 0; font-weight: bold;">
                Health Management Report
            </div>
            <div style="font-size: 10pt; color: #666666; margin: 8pt 0; line-height: 1.3;">
                Barangay San Benito, Victoria, Laguna<br>
                Report Generated: <?php echo date('F d, Y \a\t g:i A'); ?><br>
                Prepared by: <?php echo htmlspecialchars($_SESSION['fullname']); ?>
            </div>
        </div>

        <!-- Certification Statement -->
        <div style="margin-bottom: 25pt;">
            <p
                style="font-size: 9pt; text-align: justify; margin-bottom: 15pt; font-style: italic; color: #333333; line-height: 1.4;">
                <strong>CERTIFICATION:</strong> This document contains accurate and complete information regarding the
                health management activities of San Benito Health Center as of the date of generation. All data
                presented herein has been compiled from official health records and is certified to be true and correct
                to the best of our knowledge and belief.
            </p>
        </div>

        <!-- Signature Section -->
        <div style="display: flex; justify-content: space-between; margin-top: 20pt;">
            <div style="width: 45%; text-align: center; font-size: 9pt;">
                <div style="margin-bottom: 25pt;">
                    <div style="border-bottom: 1pt solid #000000; width: 200pt; margin: 0 auto 8pt auto;"></div>
                    <div style="font-weight: bold; text-transform: uppercase;">Health Worker</div>
                    <div style="margin-top: 3pt;">Signature over Printed Name</div>
                </div>
                <div>
                    <span style="font-weight: bold;">Date:</span>
                    <span
                        style="border-bottom: 1pt solid #000000; display: inline-block; width: 100pt; margin-left: 5pt;"></span>
                </div>
            </div>

            <div style="width: 45%; text-align: center; font-size: 9pt;">
                <div style="margin-bottom: 25pt;">
                    <div style="border-bottom: 1pt solid #000000; width: 200pt; margin: 0 auto 8pt auto;"></div>
                    <div style="font-weight: bold; text-transform: uppercase;">Officer-in-Charge</div>
                    <div style="margin-top: 3pt;">Signature over Printed Name</div>
                </div>
                <div>
                    <span style="font-weight: bold;">Date:</span>
                    <span
                        style="border-bottom: 1pt solid #000000; display: inline-block; width: 100pt; margin-left: 5pt;"></span>
                </div>
            </div>
        </div>

        <!-- Document Control -->
        <div
            style="margin-top: 25pt; text-align: center; font-size: 8pt; color: #666666; border-top: 1pt solid #cccccc; padding-top: 8pt;">
            <div>Document Control No:
                SBHC-<?php echo date('Y'); ?>-<?php echo str_pad(date('z'), 3, '0', STR_PAD_LEFT); ?>-<?php echo date('His'); ?>
            </div>
            <div style="margin-top: 2pt;">This is a computer-generated report. No signature is required unless specified
                otherwise.</div>
        </div>
    </div>

    <!-- Bottom spacing for better UX -->
    <div style="height: 60px;"></div>

    <script>
        // Simple filtering function
        function filterReport() {
            const filterElement = document.getElementById('reportFilter');
            if (!filterElement) {
                console.error('Filter element not found');
                return;
            }
            
            const filter = filterElement.value;
            console.log('Filter selected:', filter);

            // Add body class for CSS targeting
            document.body.className = document.body.className.replace(/filter-\w+/g, '');
            document.body.classList.add('filter-' + filter);

            // Toggle date filter visibility
            toggleDateFilter();

            // All section IDs (excluding summary since it's now a separate statistics section)
            const sections = [
                'section-medicines',
                'section-babies',
                'section-vaccinations',
                'section-appointments',
                'section-residents'
            ];

            // Get the executive summary card that actually exists
            const executiveSummaryCard = document.getElementById('executive-summary-card');
            
            // Debug: Check if sections exist
            console.log('Executive summary card found:', !!executiveSummaryCard);
            sections.forEach(sectionId => {
                const section = document.getElementById(sectionId);
                console.log(`Section ${sectionId} found:`, !!section);
            });

            // Hide all sections first
            sections.forEach(sectionId => {
                const section = document.getElementById(sectionId);
                if (section) {
                    section.style.display = 'none';
                    section.classList.add('print-hidden');
                }
            });

            // Also hide executive summary card initially
            if (executiveSummaryCard) {
                executiveSummaryCard.style.display = 'none';
                executiveSummaryCard.classList.add('print-hidden');
            }

            // Show selected sections
            switch (filter) {
                case 'all':
                    // Show executive summary card
                    if (executiveSummaryCard) {
                        executiveSummaryCard.style.display = 'block';
                        executiveSummaryCard.classList.remove('print-hidden');
                    }
                    // Show all report sections
                    sections.forEach(sectionId => {
                        const section = document.getElementById(sectionId);
                        if (section) {
                            section.style.display = 'block';
                            section.classList.remove('print-hidden');
                        }
                    });
                    break;

                case 'summary':
                    // Show only executive summary card
                    if (executiveSummaryCard) {
                        executiveSummaryCard.style.display = 'block';
                        executiveSummaryCard.classList.remove('print-hidden');
                    }
                    break;

                case 'medicines':
                    console.log('Showing medicines section');
                    hideAllExecutiveSummaries();
                    const medicinesSection = document.getElementById('section-medicines');
                    if (medicinesSection) {
                        medicinesSection.style.display = 'block';
                        medicinesSection.classList.remove('print-hidden');
                    }
                    break;

                case 'babies':
                    console.log('Showing babies section');
                    hideAllExecutiveSummaries();
                    const babiesSection = document.getElementById('section-babies');
                    if (babiesSection) {
                        babiesSection.style.display = 'block';
                        babiesSection.classList.remove('print-hidden');
                    }
                    break;

                case 'vaccinations':
                    console.log('Showing vaccinations section');
                    hideAllExecutiveSummaries();
                    const vaccinationsSection = document.getElementById('section-vaccinations');
                    if (vaccinationsSection) {
                        vaccinationsSection.style.display = 'block';
                        vaccinationsSection.classList.remove('print-hidden');
                    }
                    break;

                case 'appointments':
                    console.log('Showing appointments section');
                    hideAllExecutiveSummaries();
                    const appointmentsSection = document.getElementById('section-appointments');
                    if (appointmentsSection) {
                        appointmentsSection.style.display = 'block';
                        appointmentsSection.classList.remove('print-hidden');
                    }
                    break;
                case 'residents':
                    console.log('Showing residents section');
                    hideAllExecutiveSummaries();
                    const residentsSection = document.getElementById('section-residents');
                    if (residentsSection) {
                        residentsSection.style.display = 'block';
                        residentsSection.classList.remove('print-hidden');
                    }
                    break;
            }
        }

        function hideAllExecutiveSummaries() {
            // Hide executive summary card
            const executiveSummaryCard = document.getElementById('executive-summary-card');
            if (executiveSummaryCard) {
                executiveSummaryCard.style.display = 'none';
                executiveSummaryCard.classList.add('print-hidden');
            }
        }

        function resetFilter() {
            document.getElementById('reportFilter').value = 'all';
            filterReport();
        }

        function applyDateFilter() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const filter = document.getElementById('reportFilter').value;

            // Only apply date filter for time-sensitive categories
            if (filter === 'vaccinations' || filter === 'appointments') {
                // Only filter if at least one date is selected
                if (dateFrom || dateTo) {
                    console.log('Applying date filter:', { dateFrom, dateTo, filter });
                    filterByDate(dateFrom, dateTo, filter);
                }
            } else {
                // If not on a filterable category, show a subtle hint
                if ((dateFrom || dateTo) && filter !== 'all') {
                    console.log('Date filter only works for Vaccination and Appointment records');
                }
            }
        }

        function filterByDate(dateFrom, dateTo, category) {
            const rows = document.querySelectorAll(`#section-${category} tbody tr`);
            let visibleCount = 0;

            rows.forEach(row => {
                // Skip info/message rows (those with colspan)
                if (row.querySelector('td[colspan]')) {
                    row.style.display = 'none';
                    return;
                }

                // Get the date column based on category
                let dateCell;
                if (category === 'vaccinations') {
                    dateCell = row.querySelector('td:nth-child(3)'); // Schedule Date is 3rd column
                } else if (category === 'appointments') {
                    dateCell = row.querySelector('td:nth-child(3)'); // Date & Time is 3rd column
                }

                if (dateCell) {
                    const rowDateText = dateCell.textContent.trim();
                    
                    // Parse date from format "Mon dd, yyyy" or "Mon dd, yyyy h:mm AM/PM"
                    let rowDate;
                    try {
                        // Remove time portion if present
                        const dateOnly = rowDateText.split(' ').slice(0, 3).join(' ');
                        rowDate = new Date(dateOnly);
                    } catch (e) {
                        console.error('Error parsing date:', rowDateText, e);
                        return;
                    }

                    let showRow = true;

                    if (dateFrom) {
                        const fromDate = new Date(dateFrom);
                        fromDate.setHours(0, 0, 0, 0);
                        rowDate.setHours(0, 0, 0, 0);
                        if (rowDate < fromDate) {
                            showRow = false;
                        }
                    }
                    
                    if (dateTo && showRow) {
                        const toDate = new Date(dateTo);
                        toDate.setHours(23, 59, 59, 999);
                        rowDate.setHours(0, 0, 0, 0);
                        if (rowDate > toDate) {
                            showRow = false;
                        }
                    }

                    row.style.display = showRow ? '' : 'none';
                    if (showRow) visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show "no results" message if no rows are visible
            const section = document.getElementById(`section-${category}`);
            if (section && visibleCount === 0 && (dateFrom || dateTo)) {
                const tbody = section.querySelector('tbody');
                const existingMessage = tbody.querySelector('.date-filter-message');
                
                if (!existingMessage) {
                    const messageRow = document.createElement('tr');
                    messageRow.className = 'date-filter-message';
                    messageRow.innerHTML = `
                        <td colspan="4" class="text-center py-4">
                            <i class="fas fa-calendar-times text-muted me-2"></i>
                            No records found for the selected date range
                        </td>
                    `;
                    tbody.appendChild(messageRow);
                }
            } else {
                // Remove message if it exists
                const section = document.getElementById(`section-${category}`);
                if (section) {
                    const existingMessage = section.querySelector('.date-filter-message');
                    if (existingMessage) {
                        existingMessage.remove();
                    }
                }
            }
        }

        function resetDateFilter() {
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';

            // Show all rows except info messages
            const allRows = document.querySelectorAll('tbody tr');
            allRows.forEach(row => {
                // Keep info rows (with colspan) visible, hide date filter messages
                if (row.classList.contains('date-filter-message')) {
                    row.remove();
                } else if (!row.querySelector('td[colspan]')) {
                    row.style.display = '';
                } else {
                    // Show info rows that were originally there
                    row.style.display = '';
                }
            });
        }

        // Show/hide date filter based on selected category
        function toggleDateFilter() {
            const filter = document.getElementById('reportFilter').value;
            const dateFilterContainer = document.querySelector('.col-md-6:last-child');

            if (filter === 'vaccinations' || filter === 'appointments') {
                dateFilterContainer.style.opacity = '1';
                dateFilterContainer.style.pointerEvents = 'auto';
            } else {
                dateFilterContainer.style.opacity = '0.5';
                dateFilterContainer.style.pointerEvents = 'none';
                resetDateFilter();
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOM loaded, initializing filter');
            filterReport();
            toggleDateFilter();
        });

        // Handle print events to ensure proper styling
        window.addEventListener('beforeprint', function () {
            // Ensure current filter class is maintained during print
            const currentFilter = document.getElementById('reportFilter').value;
            document.body.classList.add('filter-' + currentFilter);
            document.body.classList.add('printing');
        });

        window.addEventListener('afterprint', function () {
            // Clean up print class
            document.body.classList.remove('printing');
        });

        // Print current filtered report directly from this page
        function printCurrentReport() {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = 'Preparing Print...';
            button.disabled = true;

            // Store current filter state
            const currentFilter = document.getElementById('reportFilter').value;
            console.log('Printing with filter:', currentFilter);

            // Don't change visibility - respect current filter selection
            // The filter has already set which sections should be visible

            // Add print class to body to trigger print styles
            document.body.classList.add('printing');
            document.body.classList.add('filter-' + currentFilter);

            // Small delay to ensure styles are applied
            setTimeout(() => {
                // Trigger browser print dialog for current page
                window.print();

                // Reset everything after print dialog
                setTimeout(() => {
                    document.body.classList.remove('printing');
                    button.innerHTML = originalText;
                    button.disabled = false;
                    
                    // No need to restore filter state since we didn't change it
                }, 500);
            }, 300);
        }

        // PDF generation removed - using print function only
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>