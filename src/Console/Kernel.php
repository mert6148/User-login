<?php

namespace App\Console;

use App\Console\Commands\AdminCreateCommand;
use App\Console\Commands\AdminListCommand;
use App\Console\Commands\CacheClearCommand;
use App\Console\Commands\DbMigrateCommand;
use App\Console\Commands\DbSeedCommand;
use App\Console\Commands\DbStatusCommand;
use App\Console\Commands\KeyGenerateCommand;
use App\Console\Commands\RouteListCommand;
use App\Console\Commands\UserCreateCommand;
use App\Console\Commands\UserListCommand;
use App\Console\Commands\UserDeleteCommand;
use App\Console\Commands\ConfigCacheCommand;
use App\Console\Commands\OptimizeCommand;
use Symfony\Component\Console\Application;

class Kernel
{
    /**
     * Register all console commands
     */
    public function registerCommands(Application $app): void
    {
        // Admin Commands
        $app->add(new AdminCreateCommand());
        $app->add(new AdminListCommand());
        
        // User Commands
        $app->add(new UserCreateCommand());
        $app->add(new UserListCommand());
        $app->add(new UserDeleteCommand());
        
        // Database Commands
        $app->add(new DbMigrateCommand());
        $app->add(new DbSeedCommand());
        $app->add(new DbStatusCommand());
        
        // Cache Commands
        $app->add(new CacheClearCommand());
        $app->add(new ConfigCacheCommand());
        
        // System Commands
        $app->add(new KeyGenerateCommand());
        $app->add(new RouteListCommand());
        $app->add(new OptimizeCommand());
    }
}

