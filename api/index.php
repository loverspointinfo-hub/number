<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Yahan aapne apni API key 'satyam' set ki hai
define('API_KEY', 'satyam');

$apikey = $_GET['apikey'] ?? '';

// Agar URL me '?apikey=satyam' nahi hoga, toh ye error aayega
if ($apikey !== API_KEY) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid API Key",
        "developer" => "https://t.me/satyamgupta9999",
        "credit" => "https://t.me/satyamgupta9999",
        "private" => "https://t.me/osintbysatyam"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$number = $_GET['number'] ?? '';

if (empty($number)) {
    echo json_encode([
        "success" => false,
        "message" => "Please provide number",
        "example" => "?apikey=satyam&number=9570187989",
        "developer" => "https://t.me/satyamgupta9999",
        "credit" => "https://t.me/osintsatyam",
        "private" => "https://t.me/osintbysatyam"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$number = preg_replace('/\D/', '', $number);

/* New Cloudflare API */
$url = "https://movements-invoice-amanda-victoria.trycloudflare.com/search/number?number=" . urlencode($number) . "&key=mysecretkey123";

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to fetch data",
        "developer" => "https://t.me/satyamosint",
        "credit" => "https://t.me/satyamgupta9999",
        "private" => "https://t.me/satyamosint"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// JSON ko decode karte hain
$data = json_decode($response, true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid response from server",
        "developer" => "https://t.me/osintbysatyam",
        "credit" => "satyamgupta",
        "private" => "https://t.me/osintbysatyam"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Screenshot "60013.jpg" ke hisaab se Cloudflare API 'result' key ke andar array bhej rahi hai. 
// Us 'result' ko bahar nikalte hain taaki output clean rahe.
$finalResult = isset($data['result']) ? $data['result'] : $data;

echo json_encode([
    "success" => true,
    "developer" => "Satyam Gupta",
    "credit" => "https://t.me/osintbysatyam",
    "private" => "https://t.me/+14rDlunTEzwwZGY1",
    "result" => $finalResult
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
