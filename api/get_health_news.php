<?php
/**
 * PHP Proxy for NewsAPI Health News
 * Fetches health news from NewsAPI to avoid CORS issues on mobile devices
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// NewsAPI Configuration
$API_KEY = '822a747222374c9e957cececd52167aa'; // Your NewsAPI key
$API_URL = "https://newsapi.org/v2/top-headlines?category=health&language=en&pageSize=6&apiKey={$API_KEY}";

try {
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    // Check HTTP response code
    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: {$httpCode}");
    }
    
    // Decode and validate response
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON Decode Error: ' . json_last_error_msg());
    }
    
    // Check for API errors
    if (isset($data['status']) && $data['status'] === 'error') {
        throw new Exception($data['message'] ?? 'NewsAPI Error');
    }
    
    // Return successful response
    echo json_encode($data);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Health News API Error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'articles' => []
    ]);
}
