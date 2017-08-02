<?php

namespace DeltaCli\Command;

use DeltaCli\Exception\SshKeysAlreadyExists;
use DeltaCli\Exec;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SshKeyGen extends Command
{
    protected function configure()
    {
        $this
            ->setName('ssh:generate-key')
            ->setDescription('Generate SSH keys for this project.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists(getcwd() . '/ssh-keys')) {
            mkdir(getcwd() . '/ssh-keys');
        }

        if (file_exists(getcwd() . '/ssh-keys/id_rsa')) {
            throw new SshKeysAlreadyExists('SSH keys have already been generated.');
        }

        Exec::run("ssh-keygen -trsa -b2048 -fssh-keys/id_rsa -q -N ''", $output, $exitStatus);
    }
}
