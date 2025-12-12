<?php
/**
 * api/network.php
 * Ağ profilleri için REST API
 * 
 * Endpointler:
 *  GET  /api/network/list
 *  GET  /api/network/active
 *  POST /api/network/switch
 * 
 * Güvenlik:
 *  - Basit API key kontrolü: ?key=YOUR_API_KEY
 *  - Daha gelişmiş JWT istersen ekleyebilirim.
 */

// ---- CONFIG ---- //
$API_KEY = "12345"; // burayı değiştir
$networkFile = __DIR__ . "/../config/network.php";
$activeFile = __DIR__ . "/../config/active_network.txt";
$logFile = __DIR__ . "/../logs/network_api.log";
$cacheFile = __DIR__ . "/../cache/network_cache.json";

// Veritabanı koruma seçenekleri
$DB_CONFIG = [
    "enable_logging" => true,          // API çağrılarını logla
    "enable_caching" => true,          // Sonuçları cache'le
    "cache_ttl" => 300,                // Cache süresi (5 dakika)
    "rate_limit" => 100,               // Dakikada maksimum istek
    "rate_limit_window" => 60,         // Rate limit penceresi (saniye)
    "max_request_size" => 5242880,     // Max request (5MB)
    "allowed_origins" => [             // CORS izni
        "http://localhost",
        "http://localhost:8000",
        "https://localhost"
    ],
    "backup_enabled" => true,          // Otomatik yedek
    "backup_dir" => __DIR__ . "/../backups"
];

// Logging helper
function logApiRequest(
    string $endpoint,
    string $method,
    int $status,
    string $logFile,
    ?string $message = null
): void {
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

    $logEntry = json_encode([
        'timestamp' => $timestamp,
        'endpoint' => $endpoint,
        'method' => $method,
        'status' => $status,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'message' => $message
    ]);

    error_log($logEntry . PHP_EOL, 3, $logFile);
}

// Cache helper
function getCachedNetworks(string $cacheFile, int $ttl = 300): ?array {
    if (!file_exists($cacheFile)) {
        return null;
    }

    $fileTime = filemtime($cacheFile);
    $currentTime = time();

    if ($currentTime - $fileTime > $ttl) {
        return null; // Cache süresi doldu
    }

    $cached = json_decode(file_get_contents($cacheFile), true);
    return $cached;
}

function setCachedNetworks(string $cacheFile, array $networks): bool {
    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0755, true);
    }

    return file_put_contents(
        $cacheFile,
        json_encode($networks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    ) !== false;
}

// Rate limiting helper
function checkRateLimit(string $ip, int $limit = 100, int $window = 60): bool {
    $rateFile = sys_get_temp_dir() . "/rate_limit_{$ip}.json";

    if (!file_exists($rateFile)) {
        $data = ['count' => 1, 'timestamp' => time()];
        file_put_contents($rateFile, json_encode($data));
        return true;
    }

    $data = json_decode(file_get_contents($rateFile), true);
    $currentTime = time();

    // Pencere dışında mı?
    if ($currentTime - $data['timestamp'] > $window) {
        $data = ['count' => 1, 'timestamp' => $currentTime];
        file_put_contents($rateFile, json_encode($data));
        return true;
    }

    // Limit aşıldı mı?
    if ($data['count'] >= $limit) {
        return false;
    }

    $data['count']++;
    file_put_contents($rateFile, json_encode($data));
    return true;
}

// CORS helper
function checkCorsOrigin(array $allowedOrigins): bool {
    if (empty($_SERVER['HTTP_ORIGIN'])) {
        return true; // CORS check yok
    }

    return in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins, true);
}

// Backup helper
function createBackup(string $sourceFile, string $backupDir): bool {
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    if (!file_exists($sourceFile)) {
        return false;
    }

    $timestamp = date('Y-m-d_H-i-s');
    $filename = basename($sourceFile);
    $backupFile = "{$backupDir}/{$filename}.{$timestamp}.bak";

    return copy($sourceFile, $backupFile);
}

// ---- AUTH ---- //
if (!isset($_GET["key"]) || !validateNetworkAccess($_GET["key"], $API_KEY)) {
    logApiRequest('unknown', 'UNKNOWN', 401, $logFile, 'Unauthorized access');
    sendJsonResponse(["error" => "Unauthorized"], 401);
}

// ---- RATE LIMITING ---- //
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($clientIp, $DB_CONFIG['rate_limit'], $DB_CONFIG['rate_limit_window'])) {
    logApiRequest('rate_limit', $_SERVER['REQUEST_METHOD'], 429, $logFile, 'Rate limit exceeded');
    http_response_code(429);
    sendJsonResponse(
        ["error" => "Rate limit exceeded. Max {$DB_CONFIG['rate_limit']} requests per {$DB_CONFIG['rate_limit_window']} seconds"],
        429
    );
}

// ---- CORS CHECK ---- //
if (!checkCorsOrigin($DB_CONFIG['allowed_origins'])) {
    logApiRequest('cors', $_SERVER['REQUEST_METHOD'], 403, $logFile, 'CORS origin not allowed');
    sendJsonResponse(["error" => "CORS origin not allowed"], 403);
}

// ---- ROUTER ---- //
$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Ağ profilleri (cache ile)
if ($DB_CONFIG['enable_caching']) {
    $networks = getCachedNetworks($cacheFile, $DB_CONFIG['cache_ttl']);
    if ($networks === null) {
        $networks = require_once $networkFile;
        setCachedNetworks($cacheFile, $networks);
    }
} else {
    $networks = require_once $networkFile;
}

// Aktif profil
$current = file_exists($activeFile)
    ? trim(file_get_contents($activeFile))
    : null;


// =========================
//  GET /api/network/list
// =========================
if ($uri === "/api/network/list" && $method === "GET") {
    logApiRequest($uri, $method, 200, $logFile);

    sendJsonResponse([
        "status" => "success",
        "count" => count($networks),
        "profiles" => $networks,
        "cached" => !empty($DB_CONFIG['enable_caching']),
        "cache_ttl" => $DB_CONFIG['cache_ttl']
    ], 200);
}

// =========================
//  GET /api/network/active
// =========================
if ($uri === "/api/network/active" && $method === "GET") {
    logApiRequest($uri, $method, 200, $logFile);

    $response = [
        "status" => "success",
        "active" => $current,
    ];

    if ($current && isset($networks[$current])) {
        $response["data"] = $networks[$current];
    }

    sendJsonResponse($response, 200);
}

// =========================
//  GET /api/network/dashboard
// =========================
if ($uri === "/api/network/dashboard" && $method === "GET") {
    logApiRequest($uri, $method, 200, $logFile);

    // Sistem bilgileri topla
    $statistics = [
        "total_profiles" => count($networks),
        "active_profile" => $current,
        "cache_enabled" => $DB_CONFIG['enable_caching'],
        "cache_ttl" => $DB_CONFIG['cache_ttl'],
        "rate_limiting" => [
            "enabled" => true,
            "limit" => $DB_CONFIG['rate_limit'],
            "window" => $DB_CONFIG['rate_limit_window'] . "s"
        ],
        "backup_enabled" => $DB_CONFIG['backup_enabled'],
        "cors_origins" => count($DB_CONFIG['allowed_origins']),
        "log_file" => file_exists($logFile) ? filesize($logFile) . " bytes" : "Not created",
        "cache_file" => file_exists($cacheFile) ? filesize($cacheFile) . " bytes" : "Not created",
    ];

    // Profil detayları
    $profileDetails = [];
    foreach ($networks as $name => $config) {
        $profileDetails[$name] = [
            "name" => $config['name'] ?? $name,
            "ip" => $config['ip'] ?? null,
            "dns" => $config['dns'] ?? null,
            "is_active" => ($name === $current),
            "type" => $config['type'] ?? "standard"
        ];
    }

    // Log istatistikleri
    $logStats = [
        "total_requests" => 0,
        "success_count" => 0,
        "error_count" => 0,
        "file_size" => file_exists($logFile) ? filesize($logFile) : 0
    ];

    if (file_exists($logFile)) {
        $logs = file($logFile, FILE_SKIP_EMPTY_LINES);
        $logStats['total_requests'] = count($logs);

        foreach ($logs as $logLine) {
            if (preg_match('/"status":\s*([2345]\d{2})/', $logLine, $matches)) {
                if ($matches[1][0] === '2') {
                    $logStats['success_count']++;
                } else {
                    $logStats['error_count']++;
                }
            }
        }
    }

    sendJsonResponse([
        "status" => "success",
        "timestamp" => date('Y-m-d H:i:s'),
        "api_version" => "2.0",
        "statistics" => $statistics,
        "profiles" => $profileDetails,
        "logs" => $logStats,
        "server" => [
            "php_version" => PHP_VERSION,
            "os" => PHP_OS,
            "memory_limit" => ini_get('memory_limit'),
            "max_execution_time" => ini_get('max_execution_time')
        ]
    ], 200);
}

// =========================
//  POST /api/network/switch
// =========================
if ($uri === "/api/network/switch" && $method === "POST") {
    // Request size kontrolü
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $DB_CONFIG['max_request_size']) {
        logApiRequest($uri, $method, 413, $logFile, 'Request too large');
        http_response_code(413);
        sendJsonResponse(
            ["error" => "Request payload too large. Max size: {$DB_CONFIG['max_request_size']} bytes"],
            413
        );
    }

    $json = json_decode(file_get_contents("php://input"), true);
    $profile = $json["profile"] ?? null;

    if (!$profile) {
        logApiRequest($uri, $method, 400, $logFile, 'Missing profile parameter');
        sendJsonResponse(["error" => "Missing profile parameter"], 400);
    }

    if (!isset($networks[$profile])) {
        logApiRequest($uri, $method, 404, $logFile, "Profile '$profile' not found");
        sendJsonResponse(
            ["error" => "Profile '$profile' not found"],
            404
        );
    }

    // Yedek oluştur
    if ($DB_CONFIG['backup_enabled']) {
        createBackup($activeFile, $DB_CONFIG['backup_dir']);
    }

    // Profili kaydet
    if (!file_put_contents($activeFile, $profile)) {
        logApiRequest($uri, $method, 500, $logFile, 'Failed to write active profile');
        sendJsonResponse(
            ["error" => "Failed to write active profile"],
            500
        );
    }

    // Cache'i temizle
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }

    logApiRequest($uri, $method, 200, $logFile, "Switched to '$profile'");

    sendJsonResponse([
        "status" => "success",
        "message" => "Active network switched to '$profile'",
        "active" => $profile,
        "data" => $networks[$profile],
        "backup_created" => $DB_CONFIG['backup_enabled']
    ], 200);
}

// =========================
//  GET /api/network/validate
// =========================
if ($uri === "/api/network/validate" && $method === "GET") {
    $profile = $_GET["profile"] ?? null;

    if (!$profile) {
        logApiRequest($uri, $method, 400, $logFile, 'Missing profile parameter');
        sendJsonResponse(["error" => "Missing profile parameter"], 400);
    }

    $exists = isset($networks[$profile]);

    logApiRequest($uri, $method, 200, $logFile, "Validated profile: $profile");

    sendJsonResponse([
        "status" => "success",
        "profile" => $profile,
        "valid" => $exists,
        "message" => $exists ? "Profile exists" : "Profile not found",
        "is_active" => ($profile === $current)
    ], 200);
}

// =========================
//  GET /api/network/health
// =========================
if ($uri === "/api/network/health" && $method === "GET") {
    $health = [
        "status" => "healthy",
        "api_version" => "2.0",
        "timestamp" => time(),
        "checks" => [
            "config_file" => file_exists($networkFile),
            "active_file" => file_exists($activeFile),
            "log_file" => is_writable(dirname($logFile)),
            "cache_dir" => is_writable(dirname($cacheFile)) || !$DB_CONFIG['enable_caching'],
            "backup_dir" => is_writable($DB_CONFIG['backup_dir']) || !$DB_CONFIG['backup_enabled'],
            "memory" => memory_get_peak_usage() < (int)ini_get('memory_limit') * 1024 * 1024,
        ]
    ];

    $allHealthy = !in_array(false, $health['checks']);
    $health['status'] = $allHealthy ? "healthy" : "degraded";

    logApiRequest($uri, $method, 200, $logFile, "Health check: {$health['status']}");

    sendJsonResponse($health, 200);
}

// =========================
//  GET /api/network/logs
// =========================
if ($uri === "/api/network/logs" && $method === "GET") {
    $limit = (int)($_GET['limit'] ?? 50);
    $limit = min($limit, 1000); // Max 1000

    if (!file_exists($logFile)) {
        logApiRequest($uri, $method, 200, $logFile, 'No logs available');
        sendJsonResponse([
            "status" => "success",
            "total" => 0,
            "logs" => []
        ], 200);
    }

    $allLogs = file($logFile, FILE_SKIP_EMPTY_LINES);
    $logs = array_slice(array_reverse($allLogs), 0, $limit);

    $parsedLogs = [];
    foreach ($logs as $line) {
        $parsed = json_decode(trim($line), true);
        if ($parsed) {
            $parsedLogs[] = $parsed;
        }
    }

    logApiRequest($uri, $method, 200, $logFile, "Retrieved " . count($parsedLogs) . " logs");

    sendJsonResponse([
        "status" => "success",
        "total" => count($allLogs),
        "returned" => count($parsedLogs),
        "limit" => $limit,
        "logs" => $parsedLogs
    ], 200);
}

// =========================
//  404 - Endpoint not found
// =========================
logApiRequest($uri, $method, 404, $logFile, "Endpoint not found: $uri");

sendJsonResponse(
    [
        "error" => "Endpoint not found",
        "path" => $uri,
        "method" => $method,
        "available_endpoints" => [
            "GET /api/network/list",
            "GET /api/network/active",
            "GET /api/network/dashboard",
            "GET /api/network/validate?profile=NAME",
            "GET /api/network/health",
            "GET /api/network/logs?limit=50",
            "POST /api/network/switch"
        ]
    ],
    404
);




// =========================
// Utility Functions
// =========================

/**
 * Encoding tipi için uygun fonksiyonu seç
 */
function getEncodingFunction(int $encoding_type): callable {
    return ($encoding_type === PHP_QUERY_RFC3986)
        ? 'rawurlencode'
        : 'urlencode';
}

/**
 * Scalar parametreyi encode et
 */
function encodeScalarParam(mixed $value, int $encoding_type): string {
    $encoder = getEncodingFunction($encoding_type);
    return $encoder((string)$value);
}

/**
 * Array parametresini query string'e dönüştür
 */
function encodeArrayParam(
    string $encoded_key,
    array $value,
    int $encoding_type
): array {
    $encoder = getEncodingFunction($encoding_type);
    $result = [];

    foreach ($value as $sub_key => $sub_value) {
        $encoded_sub_key = $encoder($sub_key);
        $encoded_sub_value = $encoder((string)$sub_value);
        $result[] = "{$encoded_key}[{$encoded_sub_key}]={$encoded_sub_value}";
    }

    return $result;
}

/**
 * Ağ parametrelerini query string'e dönüştür
 *
 * @param array $data Dönüştürülecek veri
 * @param string $numeric_prefix Sayısal key'ler için prefix
 * @param string $arg_separator Parametreler arasında ayıraç
 * @param int $encoding_type Encoding tipi (RFC1738 veya RFC3986)
 * @return string Query string
 */
function buildNetworkQuery(
    array $data,
    string $numeric_prefix = "",
    string $arg_separator = "&",
    int $encoding_type = PHP_QUERY_RFC1738
): string {
    $encoder = getEncodingFunction($encoding_type);
    $query = [];

    foreach ($data as $key => $value) {
        // Numeric prefix'i ekle
        $param_key = is_numeric($key) ? $numeric_prefix . $key : $key;
        $encoded_key = $encoder($param_key);

        // Array ise helper kullan
        if (is_array($value)) {
            $query = array_merge($query, encodeArrayParam($encoded_key, $value, $encoding_type));
        } else {
            // Scalar value
            $encoded_value = encodeScalarParam($value, $encoding_type);
            $query[] = "{$encoded_key}={$encoded_value}";
        }
    }

    return implode($arg_separator, $query);
}

/**
 * Ağ profilini URL parametrelerine dönüştür
 * 
 * @param array $profile Ağ profili verisi
 * @return string URL-safe parametreler
 */
function encodeNetworkProfile(array $profile): string {
    $parts = [];

    foreach ($profile as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $encoded_key = urlencode($key);
        $encoded_value = urlencode((string)$value);
        $parts[] = "{$encoded_key}={$encoded_value}";
    }

    return implode("&", $parts);
}

/**
 * Query string'i array'e dönüştür
 * 
 * @param string $query_string Query string
 * @return array Decoded parametreler
 */
function parseNetworkQuery(string $query_string): array {
    $result = [];
    parse_str($query_string, $result);
    return $result;
}

/**
 * Ağ kontrollerini doğrula
 * 
 * @param string $key API key
 * @param string $valid_key Geçerli API key
 * @return bool Doğrulama sonucu
 */
function validateNetworkAccess(string $key, string $valid_key): bool {
    return hash_equals($valid_key, $key);
}

/**
 * HTTP başlıklarını ayarla (Ağ API için)
 * 
 * @param string $content_type Content-Type başlığı
 * @param int|null $content_length Content-Length (opsiyonel)
 * @return void
 */
function setNetworkHeaders(string $content_type = "application/json", ?int $content_length = null): void {
    header("Content-Type: {$content_type}; charset=utf-8");
    header("X-API-Version: 2.0");
    header("Cache-Control: no-cache, no-store, must-revalidate");

    if ($content_length !== null) {
        header("Content-Length: {$content_length}");
    }
}

/**
 * JSON yanıtını güvenli bir şekilde gönder
 * 
 * @param array $data Gönderilecek veri
 * @param int $http_code HTTP durum kodu
 * @param int $json_options JSON encoding seçenekleri
 * @return void
 */
function sendJsonResponse(
    array $data,
    int $http_code = 200,
    int $json_options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
): void {
    http_response_code($http_code);
    setNetworkHeaders("application/json");

    $json_output = json_encode($data, $json_options);

    if ($json_output === false) {
        // JSON encoding hatası
        $data = ["error" => "JSON encoding failed"];
        $json_output = json_encode($data);
    }

    echo $json_output;
    exit;
}