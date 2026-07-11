<?php
/**
 * CallApp Phone Number Lookup
 * Complete solution with beautiful UI
 */

// Configuration
define('ENVIRONMENT', 'production');
define('ENABLE_CACHE', true);
define('CACHE_DURATION', 3600);
define('MAX_REQUESTS_PER_MINUTE', 30);
define('LOG_ERRORS', true);
define('LOG_FILE', __DIR__ . '/logs/callapp-errors.log');

// Start session for rate limiting
session_start();

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Clear cache if requested
if (isset($_GET['clear_cache'])) {
    $cacheDir = __DIR__ . '/cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit();
}

/**
 * Log errors to file
 */
function logError($message, $data = []) {
    if (!LOG_ERRORS) return;
    
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message} " . json_encode($data) . PHP_EOL;
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}

/**
 * Rate limiting
 */
function checkRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $timeWindow = 60;
    $maxRequests = MAX_REQUESTS_PER_MINUTE;
    
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $now = time();
    $requests = $_SESSION['rate_limit'];
    $requests = array_filter($requests, function($timestamp) use ($now, $timeWindow) {
        return ($now - $timestamp) < $timeWindow;
    });
    
    if (count($requests) >= $maxRequests) {
        return false;
    }
    
    $requests[] = $now;
    $_SESSION['rate_limit'] = $requests;
    return true;
}

/**
 * Get cached response
 */
function getCached($key) {
    if (!ENABLE_CACHE) return false;
    
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . '/' . md5($key) . '.cache';
    if (!file_exists($cacheFile)) return false;
    
    $data = unserialize(file_get_contents($cacheFile));
    if (!$data || !isset($data['expires']) || time() > $data['expires']) {
        @unlink($cacheFile);
        return false;
    }
    
    return $data['response'];
}

/**
 * Set cache
 */
function setCache($key, $response) {
    if (!ENABLE_CACHE) return;
    
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . '/' . md5($key) . '.cache';
    $data = [
        'expires' => time() + CACHE_DURATION,
        'response' => $response
    ];
    file_put_contents($cacheFile, serialize($data));
}

/**
 * Fetch CallApp information
 */
function fetchCallAppInfo($number) {
    $cacheKey = 'callapp_' . $number;
    $cached = getCached($cacheKey);
    if ($cached !== false) {
        return $cached;
    }
    
    // Clean number
    $cleanedNumber = preg_replace('/[^0-9+]/', '', $number);
    if (strpos($cleanedNumber, '+') !== 0) {
        $cleanedNumber = '+' . $cleanedNumber;
    }
    
    // Prepare request
    $params = [
        'cpn' => $cleanedNumber,
        'myp' => 'fb.877409278562861',
        'ibs' => '0',
        'cid' => '0',
        'tk' => '0080528975',
        'cvc' => '2268'
    ];
    
    $queryString = http_build_query($params);
    $fullUrl = 'https://s.callapp.com/callapp-server/csrch?' . $queryString;
    
    // Headers
    $headers = [
        'Host: s.callapp.com',
        'User-Agent: Mozilla/5.0 (Linux; Android 12; ONEPLUS A6013 Build/SQ3A.220705.004; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/108.0.5359.128 Mobile Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Accept-Encoding: gzip, deflate, br',
        'Connection: keep-alive'
    ];
    
    // cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_ENCODING => '',
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Handle errors
    if ($curlError) {
        $result = [
            'success' => false,
            'status' => 500,
            'error' => 'Connection error: ' . $curlError,
            'number' => $cleanedNumber
        ];
        setCache($cacheKey, $result);
        return $result;
    }
    
    // Process response
    if ($httpCode === 200) {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result = [
                'success' => true,
                'status' => 200,
                'data' => $decoded,
                'number' => $cleanedNumber
            ];
        } else {
            $result = [
                'success' => false,
                'status' => 500,
                'error' => 'Invalid response format',
                'number' => $cleanedNumber
            ];
        }
        setCache($cacheKey, $result);
        return $result;
    }
    
    $errorMessages = [
        401 => 'Unauthorized - Token expired',
        403 => 'Forbidden - Access denied',
        404 => 'Not Found - Endpoint may have changed',
        429 => 'Too Many Requests',
        525 => 'SSL Handshake Failed'
    ];
    
    $result = [
        'success' => false,
        'status' => $httpCode,
        'error' => $errorMessages[$httpCode] ?? 'Request failed with status: ' . $httpCode,
        'number' => $cleanedNumber
    ];
    
    setCache($cacheKey, $result);
    return $result;
}

/**
 * Validate phone number
 */
function validatePhoneNumber($number) {
    $cleaned = preg_replace('/[^0-9+]/', '', $number);
    $digitsOnly = preg_replace('/[^0-9]/', '', $cleaned);
    
    if (strlen($digitsOnly) < 10) {
        return false;
    }
    
    return $cleaned;
}

// --- API Response for AJAX ---
if (isset($_GET['ajax']) || isset($_GET['number'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'message' => 'Maximum ' . MAX_REQUESTS_PER_MINUTE . ' requests per minute'
        ]);
        exit();
    }
    
    $number = trim($_GET['number'] ?? '');
    if (empty($number)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Phone number required']);
        exit();
    }
    
    $validated = validatePhoneNumber($number);
    if ($validated === false) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid phone number format',
            'provided' => $number
        ]);
        exit();
    }
    
    $result = fetchCallAppInfo($validated);
    http_response_code($result['status'] ?? 500);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit();
}

// --- Display Web Interface ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📞 CallApp Phone Lookup</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 900px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 8px;
        }
        
        .header .emoji {
            font-size: 40px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .search-section {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }
        
        .input-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .input-group input {
            flex: 1;
            padding: 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            min-width: 200px;
            background: white;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .input-group button {
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .input-group button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .input-group button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .examples {
            margin-top: 14px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .examples span {
            font-size: 13px;
            color: #666;
        }
        
        .examples .example-btn {
            padding: 4px 14px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            color: #333;
        }
        
        .examples .example-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: scale(1.05);
        }
        
        .result-container {
            margin-top: 20px;
            display: none;
        }
        
        .result-container.show {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .result-box {
            border-radius: 16px;
            padding: 20px;
            overflow: auto;
            max-height: 500px;
        }
        
        .result-box.success {
            background: #f0fdf4;
            border: 2px solid #22c55e;
        }
        
        .result-box.error {
            background: #fef2f2;
            border: 2px solid #ef4444;
        }
        
        .result-box.loading {
            background: #f3f4f6;
            border: 2px solid #9ca3af;
            text-align: center;
            padding: 40px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .status-badge.success {
            background: #22c55e;
            color: white;
        }
        
        .status-badge.error {
            background: #ef4444;
            color: white;
        }
        
        .status-badge.info {
            background: #3b82f6;
            color: white;
        }
        
        .result-box pre {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 0;
            background: transparent;
            padding: 0;
        }
        
        .result-box .error-details {
            margin-top: 10px;
        }
        
        .result-box .error-details p {
            margin: 6px 0;
            font-size: 14px;
        }
        
        .result-box .error-details strong {
            color: #333;
        }
        
        .stats {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
            flex-wrap: wrap;
        }
        
        .stats .stat-item {
            font-size: 13px;
            color: #666;
        }
        
        .stats .stat-item strong {
            color: #333;
        }
        
        .clear-cache-btn {
            background: none;
            border: none;
            color: #9ca3af;
            font-size: 12px;
            cursor: pointer;
            text-decoration: underline;
            transition: color 0.2s;
            margin-left: auto;
        }
        
        .clear-cache-btn:hover {
            color: #333;
        }
        
        .loader {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid #e0e0e0;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .input-group input {
                min-width: 100%;
            }
            
            .input-group button {
                width: 100%;
            }
            
            .stats {
                flex-direction: column;
                gap: 8px;
            }
            
            .clear-cache-btn {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="emoji">📞</div>
            <h1>CallApp Lookup</h1>
            <p>Find caller information by phone number</p>
        </div>
        
        <div class="search-section">
            <div class="input-group">
                <input type="text" 
                       id="phoneInput" 
                       placeholder="Enter phone number with country code"
                       autofocus>
                <button id="searchBtn">🔍 Search</button>
            </div>
            
            <div class="examples">
                <span>Try:</span>
                <button class="example-btn" data-number="919872678971">+91 9872678971</button>
                <button class="example-btn" data-number="919876543210">+91 9876543210</button>
                <button class="example-btn" data-number="919999999999">+91 9999999999</button>
                <button class="example-btn" data-number="11234567890">+1 1234567890</button>
            </div>
        </div>
        
        <div id="resultContainer" class="result-container">
            <div id="resultBox" class="result-box"></div>
            
            <div class="stats">
                <div class="stat-item">
                    <strong>Status:</strong> <span id="statusText">Ready</span>
                </div>
                <div class="stat-item">
                    <strong>Number:</strong> <span id="numberText">-</span>
                </div>
                <button class="clear-cache-btn" id="clearCacheBtn">🗑️ Clear Cache</button>
            </div>
        </div>
    </div>
    
    <script>
        const phoneInput = document.getElementById('phoneInput');
        const searchBtn = document.getElementById('searchBtn');
        const resultContainer = document.getElementById('resultContainer');
        const resultBox = document.getElementById('resultBox');
        const statusText = document.getElementById('statusText');
        const numberText = document.getElementById('numberText');
        const clearCacheBtn = document.getElementById('clearCacheBtn');
        
        // Example buttons
        document.querySelectorAll('.example-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                phoneInput.value = this.dataset.number;
                performSearch();
            });
        });
        
        // Enter key
        phoneInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
        
        // Search button
        searchBtn.addEventListener('click', performSearch);
        
        // Clear cache
        clearCacheBtn.addEventListener('click', function() {
            if (confirm('Clear cache?')) {
                fetch('?clear_cache=1')
                    .then(() => {
                        alert('Cache cleared!');
                        location.reload();
                    });
            }
        });
        
        function performSearch() {
            const number = phoneInput.value.trim();
            if (!number) {
                showError('Please enter a phone number');
                return;
            }
            
            // Show loading
            showLoading();
            
            // Make API request
            fetch(`?number=${encodeURIComponent(number)}&ajax=1`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(data);
                    } else {
                        showError(data.error || 'Unknown error', data);
                    }
                })
                .catch(error => {
                    showError('Network error: ' + error.message);
                });
        }
        
        function showLoading() {
            resultContainer.classList.add('show');
            resultBox.className = 'result-box loading';
            resultBox.innerHTML = `
                <div class="loader"></div>
                <p style="margin-top: 16px; color: #666;">Searching for caller information...</p>
            `;
            statusText.textContent = 'Searching...';
            numberText.textContent = phoneInput.value;
            searchBtn.disabled = true;
            searchBtn.textContent = '⏳ Searching...';
        }
        
        function showSuccess(data) {
            resultBox.className = 'result-box success';
            resultBox.innerHTML = `
                <div class="status-badge success">✅ Success</div>
                <pre>${JSON.stringify(data.data, null, 2)}</pre>
            `;
            statusText.textContent = '✅ Success';
            numberText.textContent = data.number || phoneInput.value;
            searchBtn.disabled = false;
            searchBtn.textContent = '🔍 Search';
        }
        
        function showError(message, data = null) {
            resultBox.className = 'result-box error';
            let html = `
                <div class="status-badge error">❌ Error</div>
                <div class="error-details">
                    <p><strong>Message:</strong> ${escapeHtml(message)}</p>
            `;
            
            if (data && data.status) {
                html += `<p><strong>Status:</strong> ${data.status}</p>`;
            }
            
            if (data && data.provided) {
                html += `<p><strong>Provided:</strong> ${escapeHtml(data.provided)}</p>`;
            }
            
            html += `</div>`;
            
            resultBox.innerHTML = html;
            statusText.textContent = '❌ Error';
            numberText.textContent = phoneInput.value;
            searchBtn.disabled = false;
            searchBtn.textContent = '🔍 Search';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Check if number was provided in URL
        const urlParams = new URLSearchParams(window.location.search);
        const initialNumber = urlParams.get('number');
        if (initialNumber) {
            phoneInput.value = initialNumber;
            performSearch();
        }
    </script>
</body>
</html>