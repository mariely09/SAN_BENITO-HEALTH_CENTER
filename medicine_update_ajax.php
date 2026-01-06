<?php
try {
    require_once 'config/database.php';
    require_once 'config/session.php';
    require_once 'config/functions.php';

    // Set content type to JSON
    header('Content-Type: application/json');

    // Check if user is approved
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
        if (!isset($_POST['medicine_id']) || !isset($_POST['medicine_name']) || 
            !isset($_POST['batch_number']) || !isset($_POST['expiry_date']) || 
            !isset($_POST['quantity'])) {
            $response['message'] = 'Missing required form data';
            echo json_encode($response);
            exit;
        }

        // Get form data
        $medicine_id = (int)$_POST['medicine_id'];
        $medicine_name = sanitize($_POST['medicine_name']);
        $dosage = isset($_POST['dosage']) ? sanitize($_POST['dosage']) : null;
        $quantity = (int)$_POST['quantity'];
        $expiry_date = sanitize($_POST['expiry_date']);
        $batch_number = sanitize($_POST['batch_number']);
        $low_stock_threshold = isset($_POST['low_stock_threshold']) ? (int)$_POST['low_stock_threshold'] : 10;
        
        // Validate input
        if (empty($medicine_name) || empty($batch_number) || empty($expiry_date)) {
            $response['message'] = 'Please fill in all required fields';
        } elseif ($medicine_id <= 0) {
            $response['message'] = 'Invalid medicine ID';
        } elseif ($quantity < 0) {
            $response['message'] = 'Quantity cannot be negative';
        } elseif ($low_stock_threshold <= 0) {
            $response['message'] = 'Low stock threshold must be greater than zero';
        } else {
            // Check if medicine exists
            $check_query = "SELECT id FROM medicines WHERE id = $medicine_id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) == 0) {
                $response['message'] = 'Medicine not found';
            } else {
                // Format expiry date
                $expiry_date = formatDate($expiry_date);
                
                // Update database
                $dosage_value = $dosage ? "'$dosage'" : "NULL";
                $query = "UPDATE medicines SET 
                          medicine_name = '$medicine_name', 
                          dosage = $dosage_value,
                          quantity = $quantity, 
                          expiry_date = '$expiry_date', 
                          batch_number = '$batch_number', 
                          low_stock_threshold = $low_stock_threshold 
                          WHERE id = $medicine_id";
                
                if (mysqli_query($conn, $query)) {
                    $response['success'] = true;
                    $response['message'] = 'Medicine updated successfully';
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