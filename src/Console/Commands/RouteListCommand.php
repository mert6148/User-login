<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RouteListCommand extends Command
{
    protected static $defaultName = 'route:list';
    protected static $defaultDescription = 'Tüm route\'ları listele';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $routes = $this->getRoutes();
            
            if (empty($routes)) {
                $io->warning('Hiç route bulunamadı');
                return Command::SUCCESS;
            }
            
            $rows = [];
            foreach ($routes as $route) {
                $rows[] = [
                    $route['method'],
                    $route['path'],
                    $route['name'] ?? '-',
                    $route['controller'] ?? '-'
                ];
            }
            
            $io->table(
                ['Method', 'Path', 'Name', 'Controller'],
                $rows
            );
            
            $io->info(sprintf('Toplam %d route bulundu', count($routes)));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Hata: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function getRoutes(): array
    {
        // Symfony route'ları
        $routes = [
            ['method' => 'GET', 'path' => '/admin', 'name' => 'admin_dashboard', 'controller' => 'AdminController@dashboard'],
            ['method' => 'GET|POST', 'path' => '/admin/login', 'name' => 'admin_login', 'controller' => 'AdminController@login'],
            ['method' => 'POST', 'path' => '/admin/logout', 'name' => 'admin_logout', 'controller' => 'AdminController@logout'],
            ['method' => 'GET', 'path' => '/admin/users', 'name' => 'admin_users', 'controller' => 'AdminController@users'],
            ['method' => 'GET', 'path' => '/admin/logs', 'name' => 'admin_logs', 'controller' => 'AdminController@logs'],
            ['method' => 'GET', 'path' => '/admin/security', 'name' => 'admin_security', 'controller' => 'AdminController@security'],
            ['method' => 'GET', 'path' => '/admin/database', 'name' => 'admin_database', 'controller' => 'AdminController@database'],
        ];
        
        return $routes;
    }
}

