<?php

/**
 * ============================================================================
 * Wrapper API Script
 * Developer: @ComRed2786
 * Telegram: https://t.me/ComRed2786
 * ============================================================================
 */

// 1. Set headers to output JSON and allow Cross-Origin Requests (CORS)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// 2. Define API credentials and Base URL
$secretKey = 'mysecretkey123';
$baseUrl = 'https://movements-invoice-amanda-victoria.trycloudflare.com/search/number';

// 3. Get the 'number' parameter from the incoming GET request
$number = isset($_GET['number']) ? trim($_GET['number']) : '';

// 4. Validate input
if (empty($number)) {
    http_response_code(400); // Bad Request
    echo json_encode([
        "developer" => "@ComRed2786",
        "telegram"  => "https://t.me/ComRed2786",
        "status"    => "error",
        "message"   => "Missing 'number' parameter. Example usage: ?number=9876543210"
    ]);
    exit;
}

// 5. Safely construct the target API URL
$targetUrl = $baseUrl . '?number=' . urlencode($number) . '&key=' . urlencode($secretKey);

// 6. Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPGET, true);
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Uncomment if facing SSL issues

// 7. Execute the request
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 8. Handle cURL and HTTP errors
if ($response === false || $httpCode >= 400) {
    http_response_code($httpCode > 0 ? $httpCode : 500);
    echo json_encode([
        "developer"   => "@ComRed2786",
        "telegram"    => "https://t.me/ComRed2786",
        "status"      => "error",
        "message"     => "Failed to fetch data from the upstream API.",
        "http_code"   => $httpCode,
        "curl_error"  => $curlError,
        "request_url" => $targetUrl
    ]);
    exit;
}

// 9. Decode the response from the target API
$decodedData = json_decode($response, true);

// Fallback if the target API doesn't return valid JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    $decodedData = $response; // Return raw string if not JSON
}

// 10. Output the final JSON response
echo json_encode([
    "developer"   => "@ComRed2786",
    "telegram"    => "https://t.me/ComRed2786",
    "status"      => "success",
    "request_url" => $targetUrl,
    "data"        => $decodedData
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>
