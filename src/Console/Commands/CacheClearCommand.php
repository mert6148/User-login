<?php

namespace App\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CacheClearCommand extends Command
{
    protected static $defaultName = 'cache:clear';
    protected static $defaultDescription = 'Tüm cache dosyalarını temizle';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $cleared = $this->clearCache();
            
            $io->success(sprintf('%d cache dosyası temizlendi', $cleared));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Hata: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function clearCache(): int
    {
        $cacheFiles = [
            __DIR__ . '/../../../api_cache.json',
            __DIR__ . '/../../../cache',
        ];
        
        $cleared = 0;
        
        foreach ($cacheFiles as $cachePath) {
            if (file_exists($cachePath)) {
                if (is_file($cachePath)) {
                    unlink($cachePath);
                    $cleared++;
                } elseif (is_dir($cachePath)) {
                    $this->deleteDirectory($cachePath);
                    $cleared++;
                }
            }
        }
        
        return $cleared;
    }
    
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}

