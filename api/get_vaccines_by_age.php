<?php
require_once '../config/database.php';
require_once '../config/session.php';

header('Content-Type: application/json');

// Get baby_id from request
$baby_id = isset($_GET['baby_id']) ? (int)$_GET['baby_id'] : 0;

if ($baby_id <= 0) {
    echo json_encode(['error' => true, 'message' => 'Invalid baby ID']);
    exit;
}

// Get baby's date of birth
$query = "SELECT date_of_birth FROM babies WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $baby_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$baby = mysqli_fetch_assoc($result);

if (!$baby) {
    echo json_encode(['error' => true, 'message' => 'Baby not found']);
    exit;
}

// Calculate age in months
$dob = new DateTime($baby['date_of_birth']);
$now = new DateTime();
$age_months = $dob->diff($now)->m + ($dob->diff($now)->y * 12);

// Define vaccine groups based on age
$vaccines = [];

// Birth - 2 Months (0-2 months)
if ($age_months <= 2) {
    $vaccines[] = [
        'group' => 'Birth - 2 Months',
        'options' => [
            ['value' => 'BCG', 'label' => 'BCG (Bacillus Calmette-Guérin)'],
            ['value' => 'Hepatitis B', 'label' => 'Hepatitis B']
        ]
    ];
}

// 2 - 6 Months
if ($age_months >= 2 && $age_months <= 6) {
    $vaccines[] = [
        'group' => '2 - 6 Months',
        'options' => [
            ['value' => 'DTaP', 'label' => 'DTaP (Diphtheria, Tetanus, Pertussis)'],
            ['value' => 'Hib', 'label' => 'Hib (Haemophilus influenzae type b)'],
            ['value' => 'IPV', 'label' => 'IPV (Inactivated Poliovirus)'],
            ['value' => 'PCV', 'label' => 'PCV (Pneumococcal Conjugate)'],
            ['value' => 'Rotavirus', 'label' => 'Rotavirus']
        ]
    ];
}

// 12+ Months
if ($age_months >= 12) {
    $vaccines[] = [
        'group' => '12+ Months',
        'options' => [
            ['value' => 'MMR', 'label' => 'MMR (Measles, Mumps, Rubella)'],
            ['value' => 'Varicella', 'label' => 'Varicella (Chickenpox)'],
            ['value' => 'Hepatitis A', 'label' => 'Hepatitis A']
        ]
    ];
}

// Annual (for all ages)
$vaccines[] = [
    'group' => 'Annual',
    'options' => [
        ['value' => 'Influenza', 'label' => 'Influenza (Flu Shot)']
    ]
];

echo json_encode([
    'error' => false,
    'age_months' => $age_months,
    'vaccines' => $vaccines
]);
