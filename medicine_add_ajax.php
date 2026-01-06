<?php
try {
    require_once 'config/database.php';
    require_once 'config/session.php';
    require_once 'config/functions.php';

    // Set content type to JSON
    header('Content-Type: application/json');

    // Check if user is approved (this will redirect if not approved, so we need a different approach)
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    
    if (!isAdmin() && !isApprovedWorker()) {
        echo json_encode(['success' => false, 'message' => 'Access denied - insufficient permissions']);
        exit;
    }

    $response = ['success' => false, 'message' => ''];

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Check if all required POST data exists
        if (!isset($_POST['medicine_name']) || !isset($_POST['batch_number']) || 
            !isset($_POST['expiry_date']) || !isset($_POST['quantity'])) {
            $response['message'] = 'Missing required form data';
            echo json_encode($response);
            exit;
        }

        // Get form data
        $medicine_name = sanitize($_POST['medicine_name']);
        $dosage = isset($_POST['dosage']) ? sanitize($_POST['dosage']) : null;
        $quantity = (int)$_POST['quantity'];
        $expiry_date = sanitize($_POST['expiry_date']);
        $batch_number = sanitize($_POST['batch_number']);
        $low_stock_threshold = isset($_POST['low_stock_threshold']) ? (int)$_POST['low_stock_threshold'] : 10;
        
        // Validate input
        if (empty($medicine_name) || empty($batch_number) || empty($expiry_date)) {
            $response['message'] = 'Please fill in all required fields';
        } elseif ($quantity <= 0) {
            $response['message'] = 'Quantity must be greater than zero';
        } elseif ($low_stock_threshold <= 0) {
            $response['message'] = 'Low stock threshold must be greater than zero';
        } elseif (strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
            $response['message'] = 'Cannot add medicine with an expired date. Please select a future expiry date.';
        } else {
            // Format expiry date
            $expiry_date = formatDate($expiry_date);
            
            // Check for duplicate medicine (same name, batch number, and expiry date)
            $check_query = "SELECT id FROM medicines 
                           WHERE medicine_name = '$medicine_name' 
                           AND batch_number = '$batch_number' 
                           AND expiry_date = '$expiry_date'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $response['message'] = 'This medicine already exists with the same batch number and expiry date. Please update the existing record instead.';
            } else {
                // Insert into database
                $dosage_value = $dosage ? "'$dosage'" : "NULL";
                $query = "INSERT INTO medicines (medicine_name, dosage, quantity, expiry_date, batch_number, low_stock_threshold) 
                          VALUES ('$medicine_name', $dosage_value, $quantity, '$expiry_date', '$batch_number', $low_stock_threshold)";
                
                if (mysqli_query($conn, $query)) {
                    $response['success'] = true;
                    $response['message'] = 'Medicine added successfully';
                } else {
                    $response['message'] = 'Database error: ' . mysqli_error($conn);
                }
            }
        }
    } else {
        $response['message'] = 'Invalid request method';
    }

    echo json_encode($response);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>