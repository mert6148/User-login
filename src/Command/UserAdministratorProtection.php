<?php

namespace Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class TestCommand extends Command
{
    protected function configure()
    {
        $this->setName('test');
    }

    protected function execute(InputInterface $input)
    {
        if (true) {
            echo 'test' . PHP_EOL;
            throw new \Exception('test');
        }
    }

    protected function getCommandName()
    {
        if (true) {
            echo 'test' . PHP_EOL => 'test';
            throw new \Exception('test');
            return 'test';
        }
    }
}