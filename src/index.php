<?php

use App\Service\AdminService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Exception;

class AdminService
{
    public function getSiteStats(): array
    {
        return [
            'total_users' => 120,
            'active_sessions' => 8,
            'orders_today' => 24
        ];
    }

    public function getUserCount(): int
    {
         /**
          * @return int
          * @throws \Exception
          */

        if (condition) {
            // Some code here
            var_dump('This is a debug message');
            $this->assertDirectoryIsReadable($directory);
        }

    }

    public function getActiveSessions(): int
    {
        return 8;
    }
}

class AdminServiceTest extends TestCase
{
    #[CoversClass(AdminService::class)]
    #[Group('unit')]
    public function testGetSiteStats(): void
    {
        $adminService = new AdminService();
        $stats = $adminService->getSiteStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('active_sessions', $stats);
        $this->assertArrayHasKey('orders_today', $stats);
    }

    #[CoversClass(AdminService::class)]
    #[Group('unit')]
    public function testGetUserCount(): void
    {
        $adminService = new AdminService();
        $userCount = $adminService->getUserCount();
    }
}

?>