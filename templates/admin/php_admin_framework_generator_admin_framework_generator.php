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

// routes.php
$routes = <<<'PHP'
<?php
// Basit yönlendirme: path -> controller@method
return [
    '/' => 'HomeController@index',
    '/admin' => 'Admin\AdminController@index',
    '/admin/login' => 'Admin\AuthController@login',
    '/admin/logout' => 'Admin\AuthController@logout',
    '/admin/users' => 'Admin\UserController@index',
    '/admin/users/create' => 'Admin\UserController@create',
    '/admin/users/store' => 'Admin\UserController@store',
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
class AdminController {
    protected $config;
    public function __construct($config){ $this->config = $config; }
    protected function guard(){ if (empty($_SESSION['admin_logged'])) { header('Location: /admin/login'); exit; } }
    public function index(){ $this->guard(); include __DIR__ . '/../../Views/admin/dashboard.php'; }
}
PHP;
file_put_contents("$out/app/Controllers/Admin/AdminController.php", $adminController);

// UserController (örnek CRUD)
$userController = <<<'PHP'
<?php
namespace Admin;
use PDO;
class UserController {
    protected $config;
    protected $pdo;
    public function __construct($config){
        $this->config = $config;
        $db = $config['db'];
        $this->pdo = new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass']);
    }
    protected function guard(){ if (empty($_SESSION['admin_logged'])) { header('Location: /admin/login'); exit; } }
    public function index(){ $this->guard();
        $stmt = $this->pdo->query('SELECT id,username,email FROM users LIMIT 100');
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        include __DIR__ . '/../../Views/admin/users/index.php';
    }
    public function create(){ $this->guard(); include __DIR__ . '/../../Views/admin/users/create.php'; }
    public function store(){ $this->guard();
        $u = $_POST['username'] ?? ''; $e = $_POST['email'] ?? ''; $p = $_POST['password'] ?? '';
        if (!$u || !$e) { echo 'Eksik alan'; return; }
        $hash = password_hash($p, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO users (username,email,password) VALUES (?,?,?)');
        $stmt->execute([$u,$e,$hash]);
        header('Location: /admin/users'); exit;
    }
}
PHP;
file_put_contents("$out/app/Controllers/Admin/UserController.php", $userController);

// Views: layouts header/footer
$header = <<<'PHP'
<!doctype html>
<html><head><meta charset="utf-8"><title>Admin</title></head><body>
<nav><a href="/">Anasayfa</a> | <a href="/admin">Admin</a> | <a href="/admin/logout">Çıkış</a></nav>
<hr/>
PHP;
file_put_contents("$out/app/Views/layouts/header.php", $header);
$footer = <<<'PHP'
<hr/>
<footer><small>Oluşturuldu: admin_framework_generator</small></footer>
</body></html>
PHP;
file_put_contents("$out/app/Views/layouts/footer.php", $footer);

// admin views
$loginView = <<<'PHP'
<!doctype html>
<html><head><meta charset="utf-8"><title>Admin Login</title></head><body>
<h2>Admin Girişi</h2>
<?php if (!empty($error)) echo '<p style="color:red">'.htmlspecialchars($error).'</p>'; ?>
<form method="post">
  <label>Kullanıcı: <input name="user"/></label><br/>
  <label>Parola: <input name="pass" type="password"/></label><br/>
  <button>Giriş</button>
</form>
</body></html>
PHP;
file_put_contents("$out/app/Views/admin/login.php", $loginView);

$dashboard = <<<'PHP'
<h1>Admin Paneli</h1>
<p><a href="/admin/users">Kullanıcılar</a></p>
PHP;
file_put_contents("$out/app/Views/admin/dashboard.php", $dashboard);

ensure_dir("$out/app/Views/admin/users");
$usersIndex = <<<'PHP'
<h2>Kullanıcılar</h2>
<p><a href="/admin/users/create">Yeni</a></p>
<table border="1" cellpadding="6"><tr><th>ID</th><th>Kullanıcı</th><th>E-mail</th></tr>
<?php foreach($users as $u): ?>
<tr><td><?=htmlspecialchars($u['id'])?></td><td><?=htmlspecialchars($u['username'])?></td><td><?=htmlspecialchars($u['email'])?></td></tr>
<?php endforeach; ?>
</table>
PHP;
file_put_contents("$out/app/Views/admin/users/index.php", $usersIndex);

$usersCreate = <<<'PHP'
<h2>Yeni Kullanıcı</h2>
<form method="post" action="/admin/users/store">
  <label>Kullanıcı: <input name="username" required></label><br/>
  <label>Email: <input name="email" required></label><br/>
  <label>Parola: <input name="password" required></label><br/>
  <button>Ekle</button>
</form>
PHP;
file_put_contents("$out/app/Views/admin/users/create.php", $usersCreate);

// README
$readme = "# $project\n\nBu proje admin panel iskeleti olarak admin_framework_generator tarafından oluşturuldu.\n\nNOT: Üretilen kod örnek amaçlıdır; dağıtmadan önce güvenlik kontrolleri uygulayın.\n";
file_put_contents("$out/README.md", $readme);

// Basit SQL örneği
$sql = "-- users tablosu örneği\nCREATE TABLE `users` (\n  `id` int NOT NULL AUTO_INCREMENT,\n  `username` varchar(100) NOT NULL,\n  `email` varchar(200) NOT NULL,\n  `password` varchar(255) NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";
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

?>
