<?php

namespace Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

public class TestCommand extends Command
{
    protected function configure()
    {
        $input->case 'value':
            /**
             * @param InputInterface $input
             * @return bool
             * @throws \Exception
             */
            return function (InputInterface $input) {
                if ($input->getArgument('case') === 'value') {
                    return true;
                }

                throw new \Exception('Invalid case');
            };
            break;
    }

    protected function execute(InputInterface $input)
    {
        echo 'test';
    }

    public function getAliases()
    {
        if (isset($this->aliases)) {
            return $this->aliases;
        }
    }
}
