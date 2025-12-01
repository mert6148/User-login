<?php
/**
 * admin.php
 * Network geçiş yöneticisi
 * - Ağ profillerini config/network.php içinden alır
 * - Aktif ağı değiştirir
 * - Değişiklikleri logs/network.log dosyasına yazar
 */

session_start();

// Giriş kontrolü
if (empty($_SESSION['admin_logged'])) {
    header("Location: /admin/login");
    exit;
}

$config = require __DIR__ . "/../config/config.php";
$networks = require __DIR__ . "/../config/network.php";

// Log dosyası yolu
$logFile = __DIR__ . "/../logs/network.log";

// Aktif profil dosyası
$activeFile = __DIR__ . "/../config/active_network.txt";

// Şu anki aktif profil
$current = file_exists($activeFile) 
    ? trim(file_get_contents($activeFile)) 
    : "none";

// Ağ değişikliği isteği
if (isset($_GET['switch'])) {
    $target = $_GET['switch'];

    if (!isset($networks[$target])) {
        die("Geçersiz ağ profili!");
    }

    // Yeni ağ profilini aktif olarak yaz
    file_put_contents($activeFile, $target);

    // Log kaydı
    $log = date("Y-m-d H:i:s") . " | Admin: {$_SESSION['admin_user']} | Network switched to: {$target}\n";
    file_put_contents($logFile, $log, FILE_APPEND);

    $current = $target;
    $message = "Ağ profili '$target' olarak değiştirildi!";
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Network Yönetimi</title>
<style>
body { font-family:Arial; background:#f5f5f5; padding:20px; }
.box { background:#fff; padding:20px; border-radius:10px; max-width:500px; }
.btn { padding:10px 15px; display:inline-block; background:#0066ff; color:#fff; text-decoration:none; border-radius:6px; margin:5px 0; }
.active { background:#00aa00 !important; }
</style>
</head>
<body>

<div class="box">
    <h2>Network Yönetimi</h2>

    <?php if (!empty($message)) echo "<p style='color:green'><b>$message</b></p>"; ?>

    <p><b>Aktif Ağ Profili:</b> <?= htmlspecialchars($current) ?></p>

    <h3>Ağ Profilleri</h3>
    <?php foreach ($networks as $key => $net): ?>
        <p>
            <a class="btn <?= ($key==$current?'active':'') ?>" 
               href="?switch=<?= $key ?>">
               <?= htmlspecialchars($net['name']) ?> (<?= $key ?>)
            </a>
        </p>
    <?php endforeach; ?>
</div>

</body>
</html>
