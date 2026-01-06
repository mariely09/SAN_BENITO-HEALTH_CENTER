<?php
/**
 * Google Calendar API Configuration
 * 
 * Setup Instructions:
 * 1. Go to https://console.cloud.google.com/
 * 2. Create a new project or select existing one
 * 3. Enable Google Calendar API
 * 4. Create OAuth 2.0 credentials (Web application)
 * 5. Add authorized redirect URIs: http://yourdomain.com/google_calendar_callback.php
 * 6. Download credentials and update the values below
 */

// Google OAuth 2.0 Credentials
define('GOOGLE_CLIENT_ID', '442738811189-h6gbokps4krl6b9jrm1rng9s9maeoeml.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', ''); // Copy the google client secret

// Dynamic Redirect URI - works on localhost, local network IP, and production
function getRedirectUri() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseDir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove /api or /config from path if present
    $baseDir = preg_replace('#/(api|config)$#', '', $baseDir);
    
    return $protocol . '://' . $host . $baseDir . '/google_calendar_callback.php';
}

define('GOOGLE_REDIRECT_URI', getRedirectUri());

// Google Calendar API Endpoints
define('GOOGLE_OAUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_CALENDAR_API_URL', 'https://www.googleapis.com/calendar/v3');

// OAuth Scopes
define('GOOGLE_SCOPES', 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events');

/**
 * Generate Google OAuth URL for user authentication
 */
function getGoogleAuthUrl($userId) {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => GOOGLE_SCOPES,
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => base64_encode(json_encode(['user_id' => $userId]))
    ];
    
    return GOOGLE_OAUTH_URL . '?' . http_build_query($params);
}

/**
 * Exchange authorization code for access token
 */
function exchangeCodeForToken($code) {
    $data = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return false;
}

/**
 * Refresh access token using refresh token
 */
function refreshAccessToken($refreshToken) {
    $data = [
        'refresh_token' => $refreshToken,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'grant_type' => 'refresh_token'
    ];
    
    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return false;
}

/**
 * Get valid access token (refresh if expired)
 */
function getValidAccessToken($conn, $userId) {
    $query = "SELECT access_token, refresh_token, token_expiry FROM user_google_tokens WHERE user_id = $userId";
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        return false;
    }
    
    $tokenData = mysqli_fetch_assoc($result);
    
    // Check if token is expired (with 5 minute buffer)
    if (strtotime($tokenData['token_expiry']) <= time() + 300) {
        // Token expired, refresh it
        $newTokens = refreshAccessToken($tokenData['refresh_token']);
        
        if ($newTokens && isset($newTokens['access_token'])) {
            $accessToken = mysqli_real_escape_string($conn, $newTokens['access_token']);
            $expiresAt = date('Y-m-d H:i:s', time() + $newTokens['expires_in']);
            
            $updateQuery = "UPDATE user_google_tokens 
                           SET access_token = '$accessToken', 
                               token_expiry = '$expiresAt' 
                           WHERE user_id = $userId";
            mysqli_query($conn, $updateQuery);
            
            return $newTokens['access_token'];
        }
        
        return false;
    }
    
    return $tokenData['access_token'];
}
