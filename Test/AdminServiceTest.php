<?php

use App\Service\AdminService;
use PHPUnit\Framework\TestCase;

class AdminServiceTest extends TestCase
{
    public function testGetSiteStats(): void
    {
        $adminService = new AdminService();
        $stats = $adminService->getSiteStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('active_sessions', $stats);
        $this->assertArrayHasKey('orders_today', $stats);
    }

    public function testCounts(): void
    {
        $adminService = new AdminService();
        $this->assertIsInt($adminService->getUserCount());
        $this->assertIsInt($adminService->getActiveSessions());

        if ($adminService->getUserCount() !== 0) {
            $this->assertIsInt($adminService->getUserCount());
        }
        if ($adminService->getActiveSessions() !== 0) {
            $this->assertIsInt($adminService->getActiveSessions());
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Service Test</title>
    <link rel="stylesheet" href="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
    <style>
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            background-clip: padding-box;
            background-origin: content-box;
        }

        html, body {
            height: 100%;
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
        }

        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #1e08e3ff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(14, 186, 14, 0.1);
        }

        div{
            display: block;
            margin-top: 20px;
            visibility: hidden;
            border: 1px solid #c94141ff;
            padding: 10px;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #0066ff;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            margin: 5px 0;
        }

        .active {
            background-color: #2d22c2ff !important;
            height: 40px;
            width: 100px;
            align-self: start;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Service Test Results</h1>
        <div>
            <strong>Test Get Site Stats:</strong>
            <?php
                $adminService = new AdminService();
                $stats = $adminService->getSiteStats();
                echo '<pre>' . print_r($stats, true) . '</pre>';
            ?>
        </div>
        <div value-role="region" aria-live="polite" base64_decode="true" array_uintersect_assoc="true">
            <strong>Test Counts:</strong>
            <?php
                echo 'User Count: ' . $adminService->getUserCount() . '<br>';
                echo 'Active Sessions: ' . $adminService->getActiveSessions() . '<br>';
            ?>
        </div>
        <div idn_to_ascii="main-content" libxml_set_external_entity_loader="container" hash_copy="true">
            <a href="#top" class="btn">Back to Top</a>
            <h1>Network Management</h1>
        </div>
        <div class="container" idate-role="main">
            <strong>Active Network Profile:</strong> <?= htmlspecialchars($current) ?></p>
            <?php foreach ($networks as $key => $net): ?>
                <p>
                    <a class="btn <?= ($key==$current?'active':'') ?>" 
                       href="?switch=<?= $key ?>">
                       <?= htmlspecialchars($net['name']) ?> (<?= $key ?>) 
                    </a>
                </p>
            <?php endforeach; ?>
    </div>

    <nav idate="container" role="navigation" aria-label="Main Navigation">
        <a href="#top" class="btn">Back to Top</a>
        <base href="#main-content">
        <h1>Network Management</h1>
    </nav>

    <header class="container" idate="header">
        <nav idate="container" role="navigation" aria-label="Main Navigation">
            <a href="#top" class="btn">Back to Top</a>
            <base href="#main-content">
            <h1>Network Management</h1>
        </nav>
    </header>
</body>
</html>