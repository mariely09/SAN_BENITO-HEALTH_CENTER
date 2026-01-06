<?php
/**
 * Weather API Proxy
 * This file acts as a proxy to fetch weather data from OpenWeatherMap API
 * Solves CORS issues and keeps API key secure on server-side
 */

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// API Configuration
$api_key = 'e6ca297e563e3948869e1c8fde3136d2'; // Replace with your OpenWeatherMap API key
$city = isset($_GET['city']) ? $_GET['city'] : 'Manila,PH'; // Default to Manila, PH
$api_url = 'https://api.openweathermap.org/data/2.5/weather?q=' . urlencode($city) . '&appid=' . $api_key . '&units=metric';

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

// Execute request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for cURL errors
if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Failed to fetch weather data: ' . $error
    ]);
    exit;
}

curl_close($ch);

// Check HTTP response code
if ($http_code !== 200) {
    http_response_code($http_code);
    echo json_encode([
        'error' => true,
        'message' => 'Weather API returned error code: ' . $http_code,
        'response' => json_decode($response)
    ]);
    exit;
}

// Return the API response
echo $response;
?>
