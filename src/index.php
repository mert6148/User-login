<?php

use App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Dotenv\Dotenv;

namespace Symfony\Component\ClassLoader\ApcClassLoader;

if (file_exists(dirname(__DIR__).'/vendor/autoload.php')) {
    require_once dirname(__DIR__).'/vendor/autoload.php';
} elseif (file_exists(dirname(__DIR__).'/../autoload.php')) {
    require_once dirname(__DIR__).'/../autoload.php';
} else {
    throw new \RuntimeException('Install dependencies using Composer.');
}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
</head>
<body>

    <!-- Navigation Bar with Login Form -->
    <nav class="navbar">
        <div class="login-form">
            <form action="/login" method="POST">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
                <br>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <br>
                <button type="submit">Login</button>
            </form>
        </div>
    </nav>
</body>
</html>

src könfürbasyonu için PHP kodu geliştirip admin paneli ekleyebilir misin?