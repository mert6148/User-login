<?php
/**
 * admin_framework_generator.php
 *
 * Basit bir "framework + admin panel" iskeleti oluşturucu.
 * Kullanım:
 *  - Web: dosyayı sunucunuzun public klasörüne koyun ve tarayıcıdan açın.
 *  - CLI: php admin_framework_generator.php --project=MyApp --db_host=... etc.
 *
 * Ne oluşturur:
 *  - public/index.php
 *  - public/.htaccess
 *  - app/{Controllers,Models,Views}
 *  - config/config.php
 *  - routes.php
 *  - README.md
 *  - basit Auth (session tabanlı) ve örnek CRUD Controller/Model/View
 *
 * NOT: Üretilecek projeyi dağıtmadan önce güvenlik ve parola yönetimini gözden geçirin.
 */

// Basit yardımcı fonksiyonlar
function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// CLI parametreleri al
$options = [];
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--') === 0) {
        $kv = explode('=', substr($arg,2), 2);
        $options[$kv[0]] = $kv[1] ?? true;
    }
}

// Eğer web üzerinden erişildiyse form göster
if (php_sapi_name() !== 'cli' && empty($options)) {
    // Basit HTML form
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><title>Admin Framework Oluşturucu</title></head>
    <body style="font-family:system-ui, sans-serif; padding:20px;">
      <h1>Admin Framework Oluşturucu</h1>
      <form method="post">
        <label>Proje Adı: <input name="project" required value="MyAdmin" /></label><br/><br/>
        <label>Çıktı Klasörü (sunucuda yazılabilir): <input name="out" value="./generated" required /></label><br/><br/>
        <label>DB Host: <input name="db_host" value="127.0.0.1" /></label><br/>
        <label>DB Name: <input name="db_name" value="myapp" /></label><br/>
        <label>DB User: <input name="db_user" value="root" /></label><br/>
        <label>DB Pass: <input name="db_pass" value="" type="password" /></label><br/><br/>
        <label>Admin kullanıcı adı: <input name="admin_user" value="admin" /></label><br/>
        <label>Admin parolası: <input name="admin_pass" value="admin" /></label><br/><br/>
        <button type="submit">Oluştur</button>
      </form>
    </body></html>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $options = array_merge($options, $_POST);
        // Post-redirect-get değil: doğrudan oluştur
    } else {
        exit;
    }
}

// Zorunlu alanlar ve varsayılanlar
$project = $options['project'] ?? 'MyAdmin';
$out = rtrim($options['out'] ?? './generated', '/');
$db_host = $options['db_host'] ?? '127.0.0.1';
$db_name = $options['db_name'] ?? 'myapp';
$db_user = $options['db_user'] ?? 'root';
$db_pass = $options['db_pass'] ?? '';
$admin_user = $options['admin_user'] ?? 'admin';
$admin_pass = $options['admin_pass'] ?? 'admin';

// Basit güvenlik: path temizleme
$out = str_replace(['..','//'], '', $out);

// Create dir helper
function ensure_dir($d){ if (!is_dir($d)) { mkdir($d, 0755, true); } }

$structure = [
    "$out/public",
    "$out/app/Controllers",
    "$out/app/Models",
    "$out/app/Views/layouts",
    "$out/config",
];
foreach ($structure as $d) ensure_dir($d);

// Write config
$configPhp = "<?php\nreturn [\n    'project' => '".addslashes($project)."',\n    'db' => [\n        'host' => '".addslashes($db_host)."',\n        'name' => '".addslashes($db_name)."',\n        'user' => '".addslashes($db_user)."',\n        'pass' => '".addslashes($db_pass)."',\n    ],\n    'admin' => [\n        'user' => '".addslashes($admin_user)."',\n        // NOT: plain text password sadece demo amaçlı. Gerçek projede hash kullanın ve güvenli saklayın.
        'pass' => '".addslashes($admin_pass)."',\n    ],\n];\n";
file_put_contents("$out/config/config.php", $configPhp);

// routes.php (Geliştirilmiş)
$routes = <<<'PHP'
<?php
// RESTful yönlendirme: path -> controller@method
return [
    '/' => 'HomeController@index',
    '/admin' => 'Admin\AdminController@index',
    '/admin/login' => 'Admin\AuthController@login',
    '/admin/logout' => 'Admin\AuthController@logout',
    
    // Kullanıcı Yönetimi (CRUD)
    '/admin/users' => 'Admin\UserController@index',
    '/admin/users/create' => 'Admin\UserController@create',
    '/admin/users/store' => 'Admin\UserController@store',
    '/admin/users/edit' => 'Admin\UserController@edit',
    '/admin/users/update' => 'Admin\UserController@update',
    '/admin/users/delete' => 'Admin\UserController@delete',
    
    // Rol Yönetimi
    '/admin/roles' => 'Admin\RoleController@index',
    '/admin/roles/create' => 'Admin\RoleController@create',
    '/admin/roles/edit' => 'Admin\RoleController@edit',
    
    // Ayarlar
    '/admin/settings' => 'Admin\SettingsController@index',
    '/admin/settings/save' => 'Admin\SettingsController@save',
    
    // Günlükler
    '/admin/logs' => 'Admin\LogController@index',
];
PHP;
file_put_contents("$out/routes.php", $routes);

// public/index.php (front controller)
$index = <<<'PHP'
<?php
session_start();
// Basit autoload
spl_autoload_register(function($class){
    $path = __DIR__ . '/../app/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) require $path;
});

$config = require __DIR__ . '/../config/config.php';
$routes = require __DIR__ . '/../routes.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script = $_SERVER['SCRIPT_NAME'];
// Basit temizleme: varsayılan root
if ($path !== '/' && substr($path,0,7) === '/public/') {
    // Eğer public klasörünüz sunucu root değilse; bu hacky.
}

if (!isset($routes[$path])) {
    http_response_code(404);
    echo "404 - Not Found";
    exit;
}

list($controller, $method) = explode('@', $routes[$path]);
// Kontrolörleri app/Controllers altında ararız
$controllerClass = $controller;
if (!class_exists($controllerClass)) {
    http_response_code(500);
    echo "Controller bulunamadı: $controllerClass";
    exit;
}
$ctrl = new $controllerClass($config);
call_user_func([$ctrl, $method]);
PHP;
file_put_contents("$out/public/index.php", $index);

// public/.htaccess (özet)
$ht = "RewriteEngine On\n# Tüm istekleri index.php'ye yönlendir\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^(.*)$ index.php [QSA,L]\n";
file_put_contents("$out/public/.htaccess", $ht);

// Basit base Controller
$baseController = <<<'PHP'
<?php
class Controller {
    protected $config;
    public function __construct($config){ $this->config = $config; }
    protected function render($view, $data = []){
        extract($data, EXTR_SKIP);
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        if (!file_exists($viewFile)) { echo "View bulunamadı: $viewFile"; return; }
        include __DIR__ . '/../Views/layouts/header.php';
        include $viewFile;
        include __DIR__ . '/../Views/layouts/footer.php';
    }
}
PHP;
file_put_contents("$out/app/Controllers/Controller.php", $baseController);

// HomeController
$home = <<<'PHP'
<?php
class HomeController extends Controller {
    public function index(){
        echo "<h1>" . htmlspecialchars($this->config['project']) . "</h1>";
        echo "<p><a href=\"/admin\">Admin</a></p>";
    }
}
PHP;
file_put_contents("$out/app/Controllers/HomeController.php", $home);

// Admin namespace: simple AuthController, AdminController, UserController
ensure_dir("$out/app/Controllers/Admin");
ensure_dir("$out/app/Models/Admin");
ensure_dir("$out/app/Views/admin");

$authController = <<<'PHP'
<?php
namespace Admin;
class AuthController {
    protected $config;
    public function __construct($config){ $this->config = $config; }
    public function login(){
        if ($_SERVER['REQUEST_METHOD']==='POST'){
            $u = $_POST['user'] ?? '';
            $p = $_POST['pass'] ?? '';
            // Demo amaçlı: plain text. Gerçek projede hash ve güvenli doğrulama gerekir.
            if ($u === $this->config['admin']['user'] && $p === $this->config['admin']['pass']){
                $_SESSION['admin_logged'] = true;
                header('Location: /admin'); exit;
            } else {
                $error = 'Kullanıcı adı veya parola yanlış.';
            }
        }
        include __DIR__ . '/../../Views/admin/login.php';
    }
    public function logout(){ session_destroy(); header('Location: /'); exit; }
}
PHP;
file_put_contents("$out/app/Controllers/Admin/AuthController.php", $authController);

$adminController = <<<'PHP'
<?php
namespace Admin;
use PDO;
class AdminController {
    protected $config;
    protected $pdo;
    
    public function __construct($config){
        $this->config = $config;
        $db = $config['db'];
        try {
            $this->pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die('Veritabanı bağlantısı başarısız');
        }
    }
    
    protected function guard(){
        if (empty($_SESSION['admin_logged'])) {
            header('Location: /admin/login');
            exit;
        }
    }
    
    public function index(){
        $this->guard();
        
        // İstatistik bilgilerini al
        $stats = [];
        $results = $this->pdo->query('SELECT status, COUNT(*) as count FROM users GROUP BY status');
        foreach ($results as $row) {
            $stats[$row['status']] = $row['count'];
        }
        $stats['total'] = array_sum($stats);
        
        include __DIR__ . '/../../Views/admin/dashboard.php';
    }
}
PHP;
file_put_contents("$out/app/Controllers/Admin/AdminController.php", $adminController);

// Geliştirilmiş UserController (CRUD + Advanced Features)
$userController = <<<'PHP'
<?php
namespace Admin;
use PDO;
class UserController {
    protected $config;
    protected $pdo;
    private $itemsPerPage = 20;
    
    public function __construct($config){
        $this->config = $config;
        $db = $config['db'];
        try {
            $this->pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die('Veritabanı bağlantısı başarısız: ' . $e->getMessage());
        }
    }
    
    protected function guard(){ 
        if (empty($_SESSION['admin_logged'])) { header('Location: /admin/login'); exit; } 
    }
    
    protected function logActivity($action, $description, $userId = null) {
        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId ?? ($_SESSION['admin_id'] ?? null),
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    public function index(){ 
        $this->guard();
        $page = max(1, (int)($_GET['page'] ?? 1));
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $offset = ($page - 1) * $this->itemsPerPage;
        
        $where = [];
        $params = [];
        if ($search) {
            $where[] = '(username LIKE ? OR email LIKE ? OR full_name LIKE ?)';
            $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
        }
        if ($status && in_array($status, ['active', 'inactive', 'banned'])) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Toplam sayı
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM users $whereClause");
        $countStmt->execute($params);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Veriler
        $query = "SELECT id, username, email, full_name, status, created_at, last_login FROM users $whereClause ORDER BY created_at DESC LIMIT $this->itemsPerPage OFFSET $offset";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalPages = ceil($totalCount / $this->itemsPerPage);
        $stats = $this->getUserStats();
        include __DIR__ . '/../../Views/admin/users/index.php';
    }
    
    public function create(){ 
        $this->guard(); 
        include __DIR__ . '/../../Views/admin/users/create.php'; 
    }
    
    public function store(){ 
        $this->guard();
        $u = trim($_POST['username'] ?? '');
        $e = trim($_POST['email'] ?? '');
        $p = $_POST['password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (!$u || !$e || !$p) { 
            $_SESSION['error'] = 'Kullanıcı adı, e-posta ve parola zorunludur';
            header('Location: /admin/users/create'); exit;
        }
        
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Geçersiz e-posta adresi';
            header('Location: /admin/users/create'); exit;
        }
        
        try {
            $hash = password_hash($p, PASSWORD_BCRYPT);
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, email, password, full_name, phone, status) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$u, $e, $hash, $full_name, $phone, 'active']);
            
            $userId = $this->pdo->lastInsertId();
            // Varsayılan rol (user) ekle
            $roleStmt = $this->pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
            $roleStmt->execute([$userId, 3]); // 3 = user role
            
            $this->logActivity('USER_CREATED', "Yeni kullanıcı oluşturuldu: $u", $_SESSION['admin_id'] ?? null);
            $_SESSION['success'] = 'Kullanıcı başarıyla oluşturuldu';
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Hata: ' . ($e->getCode() === '23000' ? 'Kullanıcı adı veya e-posta zaten kullanılıyor' : $e->getMessage());
            header('Location: /admin/users/create'); exit;
        }
        
        header('Location: /admin/users'); exit;
    }
    
    public function edit($id = null) {
        $this->guard();
        $id = (int)($id ?? $_GET['id'] ?? 0);
        if (!$id) { header('Location: /admin/users'); exit; }
        
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $_SESSION['error'] = 'Kullanıcı bulunamadı';
            header('Location: /admin/users'); exit;
        }
        
        include __DIR__ . '/../../Views/admin/users/edit.php';
    }
    
    public function update($id = null) {
        $this->guard();
        $id = (int)($id ?? $_POST['id'] ?? 0);
        
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $password = $_POST['password'] ?? '';
        
        if (!in_array($status, ['active', 'inactive', 'banned'])) {
            $status = 'active';
        }
        
        try {
            if ($password) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $this->pdo->prepare('UPDATE users SET full_name = ?, phone = ?, status = ?, password = ? WHERE id = ?');
                $stmt->execute([$full_name, $phone, $status, $hash, $id]);
            } else {
                $stmt = $this->pdo->prepare('UPDATE users SET full_name = ?, phone = ?, status = ? WHERE id = ?');
                $stmt->execute([$full_name, $phone, $status, $id]);
            }
            
            $this->logActivity('USER_UPDATED', "Kullanıcı güncellendi: ID=$id", $_SESSION['admin_id'] ?? null);
            $_SESSION['success'] = 'Kullanıcı başarıyla güncellendi';
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Güncelleme hatası: ' . $e->getMessage();
        }
        
        header('Location: /admin/users'); exit;
    }
    
    public function delete($id = null) {
        $this->guard();
        $id = (int)($id ?? $_GET['id'] ?? 0);
        
        try {
            $this->pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$id]);
            $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $this->logActivity('USER_DELETED', "Kullanıcı silindi: ID=$id", $_SESSION['admin_id'] ?? null);
                $_SESSION['success'] = 'Kullanıcı başarıyla silindi';
            }
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Silme hatası: ' . $e->getMessage();
        }
        
        header('Location: /admin/users'); exit;
    }
    
    private function getUserStats() {
        $stats = [];
        $results = $this->pdo->query('
            SELECT status, COUNT(*) as count FROM users GROUP BY status
        ')->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $stats[$row['status']] = $row['count'];
        }
        
        $stats['total'] = array_sum($stats);
        return $stats;
    }
}
PHP;
file_put_contents("$out/app/Controllers/Admin/UserController.php", $userController);

// Views: Geliştirilmiş layouts header/footer
$header = <<<'PHP'
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; color: #333; }
        
        .navbar {
            background: #2196F3;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .navbar-brand { font-size: 20px; font-weight: bold; }
        .navbar-menu { display: flex; gap: 20px; align-items: center; }
        .navbar-menu a { color: white; text-decoration: none; padding: 8px 12px; }
        .navbar-menu a:hover { background: rgba(255,255,255,0.2); border-radius: 4px; }
        
        .container { max-width: 1200px; margin: 20px auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        footer { background: #333; color: white; padding: 20px; text-align: center; margin-top: 40px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">🔐 Admin Panel</div>
        <div class="navbar-menu">
            <a href="/">Anasayfa</a>
            <a href="/admin">Paneli</a>
            <?php if (!empty($_SESSION['admin_logged'])): ?>
                <a href="/admin/logout">Çıkış</a>
            <?php endif; ?>
        </div>
    </nav>
    <div class="container">
PHP;
file_put_contents("$out/app/Views/layouts/header.php", $header);

$footer = <<<'PHP'
    </div>
    <footer>
        <small>&copy; 2025 Admin Panel - Güvenli Yönetim Sistemi</small>
    </footer>
</body>
</html>
PHP;
file_put_contents("$out/app/Views/layouts/footer.php", $footer);

// admin views - Login
$loginView = <<<'PHP'
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Girişi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        
        .login-container { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); max-width: 400px; width: 100%; }
        
        .login-header { text-align: center; margin-bottom: 30px; }
        .login-header h2 { color: #333; margin-bottom: 10px; }
        .login-header p { color: #999; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 5px rgba(102, 126, 234, 0.3); }
        
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 4px; }
        .alert-error { background: #ffcdd2; color: #c62828; border-left: 4px solid #f44336; }
        
        .login-button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .login-button:hover { background: #5568d3; }
        
        .login-footer { text-align: center; margin-top: 20px; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>🔐 Admin Girişi</h2>
            <p>Güvenli yönetim paneli</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" name="user" required autofocus />
            </div>
            
            <div class="form-group">
                <label>Parola</label>
                <input type="password" name="pass" required />
            </div>
            
            <button type="submit" class="login-button">Giriş Yap</button>
        </form>
        
        <div class="login-footer">
            <p>Demo Kullanıcı: admin / admin</p>
        </div>
    </div>
</body>
</html>
PHP;
file_put_contents("$out/app/Views/admin/login.php", $loginView);

$dashboard = <<<'PHP'
<div class="admin-dashboard">
    <h1>Admin Paneli</h1>
    
    <div class="dashboard-stats">
        <div class="stat-card">
            <h3>Toplam Kullanıcı</h3>
            <p class="stat-number"><?php echo $stats['total'] ?? 0; ?></p>
        </div>
        <div class="stat-card active">
            <h3>Aktif</h3>
            <p class="stat-number"><?php echo $stats['active'] ?? 0; ?></p>
        </div>
        <div class="stat-card inactive">
            <h3>İnaktif</h3>
            <p class="stat-number"><?php echo $stats['inactive'] ?? 0; ?></p>
        </div>
        <div class="stat-card banned">
            <h3>Yasaklı</h3>
            <p class="stat-number"><?php echo $stats['banned'] ?? 0; ?></p>
        </div>
    </div>
    
    <div class="dashboard-menu">
        <h2>Yönetim Menüsü</h2>
        <ul>
            <li><a href="/admin/users">👥 Kullanıcı Yönetimi</a></li>
            <li><a href="/admin/roles">🔐 Rol Yönetimi</a></li>
            <li><a href="/admin/settings">⚙️ Ayarlar</a></li>
            <li><a href="/admin/logs">📋 Etkinlik Günlükleri</a></li>
        </ul>
    </div>
</div>

<style>
.admin-dashboard { padding: 20px; }
.dashboard-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
.stat-card { background: #f5f5f5; padding: 20px; border-radius: 8px; text-align: center; }
.stat-card.active { border-left: 4px solid #4CAF50; }
.stat-card.inactive { border-left: 4px solid #FF9800; }
.stat-card.banned { border-left: 4px solid #f44336; }
.stat-number { font-size: 32px; font-weight: bold; color: #333; margin: 10px 0; }
.dashboard-menu h2 { margin-top: 30px; }
.dashboard-menu ul { list-style: none; padding: 0; }
.dashboard-menu li { margin: 10px 0; }
.dashboard-menu a { display: inline-block; padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 4px; }
.dashboard-menu a:hover { background: #1976D2; }
</style>
PHP;
file_put_contents("$out/app/Views/admin/dashboard.php", $dashboard);

ensure_dir("$out/app/Views/admin/users");
$usersIndex = <<<'PHP'
<div class="users-section">
    <h2>Kullanıcı Yönetimi</h2>
    
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <div class="users-controls">
        <a href="/admin/users/create" class="btn btn-primary">+ Yeni Kullanıcı</a>
        
        <form method="get" class="search-form">
            <input type="text" name="search" placeholder="Ara..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" />
            <select name="status">
                <option value="">Durum: Tümü</option>
                <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Aktif</option>
                <option value="inactive" <?php echo ($_GET['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>İnaktif</option>
                <option value="banned" <?php echo ($_GET['status'] ?? '') === 'banned' ? 'selected' : ''; ?>>Yasaklı</option>
            </select>
            <button type="submit">Filtrele</button>
        </form>
    </div>
    
    <table class="users-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Kullanıcı Adı</th>
                <th>E-posta</th>
                <th>Ad Soyad</th>
                <th>Durum</th>
                <th>Son Giriş</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $u): ?>
            <tr class="status-<?php echo htmlspecialchars($u['status']); ?>">
                <td><?php echo htmlspecialchars($u['id']); ?></td>
                <td><?php echo htmlspecialchars($u['username']); ?></td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td><?php echo htmlspecialchars($u['full_name'] ?? '-'); ?></td>
                <td><span class="status-badge status-<?php echo htmlspecialchars($u['status']); ?>"><?php echo htmlspecialchars($u['status']); ?></span></td>
                <td><?php echo $u['last_login'] ? date('d.m.Y H:i', strtotime($u['last_login'])) : 'Asla'; ?></td>
                <td>
                    <a href="/admin/users/edit?id=<?php echo $u['id']; ?>" class="btn-small btn-edit">Düzenle</a>
                    <a href="/admin/users/delete?id=<?php echo $u['id']; ?>" class="btn-small btn-delete" onclick="return confirm('Silmek istediğinize emin misiniz?');">Sil</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php if (empty($users)): ?>
        <p class="no-data">Kullanıcı bulunamadı.</p>
    <?php endif; ?>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?php echo $p; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>" 
               class="<?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.users-section { padding: 20px; }
.users-controls { margin: 20px 0; display: flex; gap: 10px; }
.search-form { display: flex; gap: 10px; flex: 1; }
.search-form input, .search-form select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
.search-form button { padding: 8px 16px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer; }
.users-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
.users-table th { background: #f5f5f5; padding: 12px; text-align: left; border-bottom: 2px solid #ddd; }
.users-table td { padding: 12px; border-bottom: 1px solid #eee; }
.users-table tr:hover { background: #f9f9f9; }
.status-badge { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
.status-badge.status-active { background: #4CAF50; color: white; }
.status-badge.status-inactive { background: #FF9800; color: white; }
.status-badge.status-banned { background: #f44336; color: white; }
.btn-small { padding: 4px 8px; margin: 0 4px; font-size: 12px; text-decoration: none; border-radius: 4px; }
.btn-edit { background: #2196F3; color: white; }
.btn-delete { background: #f44336; color: white; }
.btn, .btn-primary { padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; display: inline-block; }
.pagination { margin-top: 20px; text-align: center; }
.pagination a { margin: 0 4px; padding: 8px 12px; border: 1px solid #ddd; text-decoration: none; color: #2196F3; }
.pagination a.active { background: #2196F3; color: white; border-color: #2196F3; }
.no-data { text-align: center; padding: 40px; color: #999; }
.alert { padding: 12px; margin-bottom: 20px; border-radius: 4px; }
.alert-success { background: #c8e6c9; color: #2e7d32; border-left: 4px solid #4CAF50; }
.alert-error { background: #ffcdd2; color: #c62828; border-left: 4px solid #f44336; }
</style>
PHP;
file_put_contents("$out/app/Views/admin/users/index.php", $usersIndex);

$usersCreate = <<<'PHP'
<div class="user-form-section">
    <h2>Yeni Kullanıcı Oluştur</h2>
    
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <form method="post" action="/admin/users/store" class="user-form">
        <div class="form-group">
            <label>Kullanıcı Adı *</label>
            <input type="text" name="username" required pattern="[a-zA-Z0-9_]{3,}" placeholder="En az 3 karakter" />
        </div>
        
        <div class="form-group">
            <label>E-posta *</label>
            <input type="email" name="email" required placeholder="ornek@example.com" />
        </div>
        
        <div class="form-group">
            <label>Parola *</label>
            <input type="password" name="password" required minlength="6" placeholder="En az 6 karakter" />
        </div>
        
        <div class="form-group">
            <label>Ad Soyad</label>
            <input type="text" name="full_name" placeholder="Ad ve Soyadı girin" />
        </div>
        
        <div class="form-group">
            <label>Telefon</label>
            <input type="tel" name="phone" placeholder="+90 555 123 4567" />
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Oluştur</button>
            <a href="/admin/users" class="btn btn-secondary">İptal</a>
        </div>
    </form>
</div>

<style>
.user-form-section { padding: 20px; max-width: 500px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
.form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
.form-group input:focus { outline: none; border-color: #2196F3; box-shadow: 0 0 5px rgba(33, 150, 243, 0.3); }
.form-actions { display: flex; gap: 10px; margin-top: 30px; }
.btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
.btn-primary { background: #4CAF50; color: white; }
.btn-primary:hover { background: #45a049; }
.btn-secondary { background: #999; color: white; }
.btn-secondary:hover { background: #888; }
</style>
PHP;
file_put_contents("$out/app/Views/admin/users/create.php", $usersCreate);

// Edit View'ı
$usersEdit = <<<'PHP'
<div class="user-form-section">
    <h2>Kullanıcı Düzenle</h2>
    
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <form method="post" action="/admin/users/update" class="user-form">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>" />
        
        <div class="form-group">
            <label>Kullanıcı Adı</label>
            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled />
            <small>Değiştirilemez</small>
        </div>
        
        <div class="form-group">
            <label>E-posta</label>
            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled />
            <small>Değiştirilemez</small>
        </div>
        
        <div class="form-group">
            <label>Yeni Parola (Boş bırakınız değiştirmemek için)</label>
            <input type="password" name="password" minlength="6" placeholder="Boş bırakınız" />
        </div>
        
        <div class="form-group">
            <label>Ad Soyad</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" />
        </div>
        
        <div class="form-group">
            <label>Telefon</label>
            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" />
        </div>
        
        <div class="form-group">
            <label>Durum</label>
            <select name="status">
                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Aktif</option>
                <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>İnaktif</option>
                <option value="banned" <?php echo $user['status'] === 'banned' ? 'selected' : ''; ?>>Yasaklı</option>
            </select>
        </div>
        
        <div class="form-info">
            <p><strong>Oluşturulma Tarihi:</strong> <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></p>
            <p><strong>Son Güncelleme:</strong> <?php echo date('d.m.Y H:i', strtotime($user['updated_at'])); ?></p>
            <p><strong>Son Giriş:</strong> <?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Asla'; ?></p>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Güncelle</button>
            <a href="/admin/users" class="btn btn-secondary">İptal</a>
        </div>
    </form>
</div>

<style>
.user-form-section { padding: 20px; max-width: 500px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
.form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
.form-group input:disabled { background: #f5f5f5; color: #999; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: #2196F3; box-shadow: 0 0 5px rgba(33, 150, 243, 0.3); }
.form-group small { display: block; color: #999; margin-top: 4px; }
.form-info { background: #f5f5f5; padding: 15px; border-radius: 4px; margin: 20px 0; }
.form-info p { margin: 8px 0; }
.form-actions { display: flex; gap: 10px; margin-top: 30px; }
.btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
.btn-primary { background: #4CAF50; color: white; }
.btn-primary:hover { background: #45a049; }
.btn-secondary { background: #999; color: white; }
.btn-secondary:hover { background: #888; }
</style>
PHP;
file_put_contents("$out/app/Views/admin/users/edit.php", $usersEdit);

// README
$readme = <<<'MD'
# $project - Admin Panel Framework

Güvenli ve profesyonel yönetim paneli iskeleti.

## Özellikler

- ✅ **Kullanıcı Yönetimi**: Eksiksiz CRUD işlemleri
- ✅ **Rol Tabanlı Erişim**: Kullanıcı rolleri ve izinler
- ✅ **Etkinlik Günlükleri**: Tüm işlemlerin kaydı
- ✅ **Arama & Filtreleme**: Gelişmiş kullanıcı araması
- ✅ **Responsive Design**: Mobil uyumlu arayüz
- ✅ **Güvenli Doğrulama**: Session tabanlı otentikasyon
- ✅ **Veritabanı Yönetimi**: İlişkili tablolar ve indeksler

## Kurulum

### 1. Dosyaları Yükleyin
Projeyi web sunucunuza aktarın.

### 2. Veritabanını Hazırlayın
MySQLde yeni bir veritabanı oluşturun ve `setup.sql` dosyasını çalıştırın:

\`\`\`bash
mysql -u root -p yeni_veritabani_adi < setup.sql
\`\`\`

### 3. Yapılandırması Yapın
`config/config.php` dosyasını düzenleyerek veritabanı bilgilerinizi girin.

### 4. Sunucu Başlatın
\`\`\`bash
# Yerel test için
php -S localhost:8000 -t ./public

# Veya Apache/Nginx ile (public klasörü document root)
\`\`\`

## İlk Giriş

**Kullanıcı Adı**: admin  
**Parola**: admin

**NOT**: Üretim ortamına dağıtmadan önce parolayı değiştirin!

## Klasör Yapısı

\`\`\`
$project/
├── public/              # Kamu erişimli klasör
│   ├── index.php        # Front Controller
│   └── .htaccess        # URL Rewriting
├── app/
│   ├── Controllers/     # Denetleyiciler
│   │   ├── Admin/
│   │   └── HomeController.php
│   ├── Models/          # Veri modelleri
│   └── Views/           # İzlemeler
├── config/
│   └── config.php       # Yapılandırma
├── routes.php           # Yönlendirme tanımları
├── setup.sql            # Veritabanı şeması
└── README.md            # Bu dosya
\`\`\`

## Yönetim Özellikleri

### Kullanıcı Yönetimi
- Tüm kullanıcıları listele
- Yeni kullanıcı oluştur
- Kullanıcı bilgilerini düzenle
- Kullanıcıyı sil
- Durum yönetimi (Aktif/İnaktif/Yasaklı)
- Arama ve filtreleme

### Veritabanı Tabloları

**users**: Kullanıcı bilgileri  
**roles**: Sistem rolleri  
**user_roles**: Kullanıcı-rol ilişkileri  
**activity_logs**: İşlem günlükleri  
**settings**: Sistem ayarları  

## Güvenlik Notları

⚠️ **Önemli**: Aşağıdaki adımları izleyin:

1. Admin parolasını değiştirin
2. Dosyaları HTTPS üzerinde sunun
3. Veritabanı güvenliğini kontrol edin
4. `.htaccess` dosyasını aktif edin (Apache)
5. Düzenli backuplar alın

## API Endpoints

\`\`\`
GET  /admin/users              - Kullanıcıları listele
GET  /admin/users/create       - Oluşturma formu
POST /admin/users/store        - Yeni kullanıcı kaydet
GET  /admin/users/edit?id=N    - Düzenleme formu
POST /admin/users/update       - Kullanıcı güncelle
GET  /admin/users/delete?id=N  - Kullanıcı sil
\`\`\`

## Teknolojiler

- **PHP** 7.4+
- **MySQL** 5.7+
- **HTML5 & CSS3**
- **PDO** (Veritabanı)
- **Session** (Otentikasyon)

## Lisans

MIT License - Özgürce kullanabilirsiniz.

---

**Oluşturucu**: admin_framework_generator v2.0  
**Son Güncelleme**: Aralık 2025
MD;
file_put_contents("$out/README.md", $readme);

// Geliştirilmiş SQL şeması
$sql = <<<'SQL'
-- users tablosu
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL UNIQUE,
  `email` varchar(200) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(200),
  `phone` varchar(20),
  `avatar_url` varchar(500),
  `status` enum('active','inactive','banned') DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_username` (`username`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- roles tablosu
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL UNIQUE,
  `description` text,
  `permissions` json DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- user_roles tablosu
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  `assigned_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `role_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- activity_logs tablosu
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int,
  `action` varchar(100),
  `description` text,
  `ip_address` varchar(45),
  `user_agent` varchar(500),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- settings tablosu
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL UNIQUE,
  `value` longtext,
  `type` enum('string','number','boolean','json') DEFAULT 'string',
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan roller
INSERT IGNORE INTO `roles` (`id`, `name`, `description`, `permissions`) VALUES
(1, 'admin', 'Sistem Yöneticisi', '{"users":"*","roles":"*","settings":"*","logs":"read"}'),
(2, 'moderator', 'Moderatör', '{"users":"read,update","roles":"read","logs":"read"}'),
(3, 'user', 'Standart Kullanıcı', '{"users":"read_own,update_own"}');

-- Varsayılan ayarlar
INSERT IGNORE INTO `settings` (`key`, `value`, `type`) VALUES
('site_name', 'Admin Panel', 'string'),
('site_logo', '/images/logo.png', 'string'),
('items_per_page', '20', 'number'),
('enable_registration', 'true', 'boolean'),
('maintenance_mode', 'false', 'boolean');
SQL;
file_put_contents("$out/setup.sql", $sql);

// Başarılı mesajı
$message = "Oluşturuldu: $out\n";
if (php_sapi_name() === 'cli'){
    echo $message;
    echo "Sunucuya koyup public klasörünü document root yapın. Örnek: php -S localhost:8000 -t $out/public\n";
} else {
    echo "<p>Projeye başarıyla oluşturuldu: <strong>".e($out)."</strong></p>";
    echo "<p>Sunucu root'unuzu <code>".e($out)."/public</code> olarak ayarlayın veya şu komutu çalıştırın:<br/><code>php -S localhost:8000 -t ".e($out)."/public</code></p>";
}

// Veri tabanı kurulum talimatları
echo "<h3>Veritabanı Kurulumu</h3>";
echo "<p>Veritabanınızı oluşturun ve aşağıdaki SQL komutlarını çalıştırın:</p>";
echo  "<pre>".e($sql)."</pre>";

// Son


?>
