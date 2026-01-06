<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// NewsAPI configuration
$API_KEY = '822a747222374c9e957cececd52167aa';
$API_URL = 'https://newsapi.org/v2/everything?q=health+philippines+OR+DOH&language=en&sortBy=publishedAt&pageSize=10&apiKey=' . $API_KEY;

try {
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'San Benito Health Center/1.0');
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // Check for cURL errors
    if ($error) {
        throw new Exception('cURL Error: ' . $error);
    }
    
    // Check HTTP status
    if ($httpCode !== 200) {
        throw new Exception('HTTP Error: ' . $httpCode);
    }
    
    // Decode and validate JSON
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON Decode Error: ' . json_last_error_msg());
    }
    
    // Check API response status
    if (isset($data['status']) && $data['status'] === 'error') {
        throw new Exception('API Error: ' . ($data['message'] ?? 'Unknown error'));
    }
    
    // Return the data
    echo json_encode($data);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>