<?php

/**
 * ============================================================================
 * API Search Script
 * Developer: @ComRed2786
 * Telegram: https://t.me/ComRed2786
 * ============================================================================
 */

/**
 * Fetches data from the Search API for a specific number.
 *
 * @param string|int $number The number to search for.
 * @return array|null Returns the decoded JSON response as an array, or null on failure.
 */
function fetchNumberData($number) {
    // 1. Define your API credentials and base URL
    $secretKey = 'mysecretkey123';
    
    // 2. Safely construct the URL
    $url = 'https://movements-invoice-amanda-victoria.trycloudflare.com/search/number' . 
           '?number=' . urlencode($number) . 
           '&key=' . urlencode($secretKey);

    // Show the example URL to the user for reference
    echo "<p><strong>Requesting URL:</strong> <a href=\"{$url}\" target=\"_blank\">{$url}</a></p>";

    // 3. Initialize the cURL session
    $ch = curl_init();

    // 4. Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);          
    curl_setopt($ch, CURLOPT_HTTPGET, true);        
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Uncomment if you get SSL certificate errors

    // 5. Execute the request
    $response = curl_exec($ch);

    // 6. Handle potential cURL errors
    if (curl_errno($ch)) {
        echo "<strong>cURL Error:</strong> " . curl_error($ch) . "<br>";
        curl_close($ch);
        return null;
    }

    // 7. Get the HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        echo "<strong>API Error:</strong> The server responded with a status code of {$httpCode}.<br>";
        return null;
    }

    // 8. Decode and return the JSON response
    return json_decode($response, true);
}

// ==========================================
// Usage Example
// ==========================================

// The number you want to look up
$searchQuery = "9876543210"; 

echo "<h2>API Test for Number: {$searchQuery}</h2>";

// Call the function
$apiData = fetchNumberData($searchQuery);

// Output the results
if ($apiData !== null) {
    echo "<h3>API Response:</h3>";
    echo "<pre style='background: #f4f4f4; padding: 10px; border-radius: 5px;'>";
    print_r($apiData);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>Failed to retrieve data from the API.</p>";
}

?>