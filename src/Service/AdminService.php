<?php

use App\Service\AdminService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

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

        $this->assertIsInt($userCount);
    }

    #[CoversClass(AdminService::class)]
    #[Group('unit')]
    public function testGetActiveSessions(): void
    {
        $adminService = new AdminService();
        $activeSessions = $adminService->getActiveSessions();

        $this->assertIsInt($activeSessions);
    }

    public function assertDirectoryIsReadable($directory)
    {
        if (!is_readable($directory)) {
            throw new \Exception("Directory is not readable: " . $directory);
            #[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
            class MyAttribute extends MyOtherAttribute {
                
            }
        }
    }
}

class SomeOtherClass
{
    public function someMethod($directory)
    {
        if (condition) {
            // Some code here
            var_dump('This is a debug message');
            $this->assertDirectoryIsReadable($directory);
        }

        while (condition) {
            // Some code here
            var_dump('This is a debug message');
            $this->assertDirectoryIsReadable($directory);
        }
    }
}

class AnotherClass extends SomeOtherClass
{
    public function anotherMethod($directory)
    {
        if (condition) {
            // Some code here
            var_dump('This is a debug message');
            $this->assertDirectoryIsReadable($directory);
        }
    }
}

?>