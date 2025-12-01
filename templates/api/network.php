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

header("Content-Type: application/json; charset=utf-8");

// ---- CONFIG ---- //
$API_KEY = "12345"; // burayı değiştir
$networkFile = __DIR__ . "/../config/network.php";
$activeFile  = __DIR__ . "/../config/active_network.txt";

// ---- AUTH ---- //
if (!isset($_GET["key"]) || $_GET["key"] !== $API_KEY) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// ---- ROUTER ---- //
$uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Ağ profilleri
$networks = require $networkFile;

// Aktif profil
$current = file_exists($activeFile) 
    ? trim(file_get_contents($activeFile)) 
    : null;


// =========================
//  GET /api/network/list
// =========================
if ($uri === "/api/network/list") {
    echo json_encode([
        "status" => "success",
        "profiles" => $networks
    ], JSON_PRETTY_PRINT);
    exit;
}


// =========================
//  GET /api/network/active
// =========================
if ($uri === "/api/network/active") {

    echo json_encode([
        "status" => "success",
        "active" => $current,
        "data"   => $current ? $networks[$current] : null
    ], JSON_PRETTY_PRINT);
    exit;
}


// =========================
//  POST /api/network/switch
// =========================
if ($uri === "/api/network/switch" && $_SERVER["REQUEST_METHOD"] === "POST") {
    
    $json = json_decode(file_get_contents("php://input"), true);
    $profile = $json["profile"] ?? null;

    if (!$profile || !isset($networks[$profile])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid or missing profile"]);
        exit;
    }

    file_put_contents($activeFile, $profile);

    echo json_encode([
        "status"  => "success",
        "message" => "Active network switched to '$profile'",
        "active"  => $profile
    ], JSON_PRETTY_PRINT);

    exit;
}


// =========================
//  404
// =========================
http_response_code(404);
echo json_encode(["error" => "Endpoint not found"]);
exit;

