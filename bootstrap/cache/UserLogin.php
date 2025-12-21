<?php

namespace App\Cache;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Traits\FilesystemProxy;
use Symfony\Component\Cache\Traits\RedisProxy;
use Symfony\Component\Cache\Traits\MemcachedProxy;
use Symfony\Component\Cache\Traits\MemcacheProxy;
use Symfony\Component\Cache\Traits\MemcacheDProxy;
use Symfony\Component\Cache\Traits\MemcacheIProxy;
use Symfony\Component\Cache\Traits\MemcacheI2Proxy;
use Symfony\Component\Cache\Traits\MemcacheI3Proxy;
use Symfony\Component\Cache\Traits\MemcacheI4Proxy;
use Symfony\Component\Cache\Traits\MemcacheI5Proxy;
use Symfony\Component\Cache\Traits\MemcacheI6Proxy;

class UserLogin
{
    public function __construct()
    {
        $this->cache = new Cache();
        $this->cache->set('user_login', 'user_login');
        $this->cache->get('user_login');
    }
}

class Cache
{
    public function set($key, $value)
    {
        return $value;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login System</title>
</head>
<body>
    <h1>User Login System</h1>
    <form action="login.php" method="post">
        <input type="text" name="username" placeholder="Username">
        <input type="password" name="password" placeholder="Password">
        <button type="submit">Login</button>
    </form>
</body>
</html>