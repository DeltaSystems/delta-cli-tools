<?php

namespace DeltaCli\Script;

use DeltaCli\Exec;
use DeltaCli\Project;
use DeltaCli\Script;
use Exception;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;

class SshInstallKey extends Script
{
    public function __construct(Project $project)
    {
        parent::__construct(
            $project,
            'ssh:install-key',
            'Install your SSH public key in the authorized_keys file on a remote environment.'
        );
    }

    protected function configure()
    {
        $this->requireEnvironment();
        parent::configure();
    }

    protected function addSteps()
    {
        $publicKey = getcwd() . '/ssh-keys/id_rsa.pub';

        /* @var $helper QuestionHelper */
        $helper = $this->getHelper('question');
        $input  = $this->getProject()->getInput();
        $output = $this->getProject()->getOutput();

        foreach ($this->getEnvironment()->getHosts() as $host) {
            $question = new Question(
                "<question>What is the SSH password for {$host->getUsername()}@{$host->getHostname()}?</question>\n"
            );

            $question->setHidden(true);

            $password = null;

            while (!$password) {
                $password = trim($helper->ask($input, $output, $question));
                $host->setSshPassword($password);
            }

            $host->getSshTunnel()->setBatchMode(false);
        }

        $this
            ->addStep($this->getProject()->getScript('ssh:fix-key-permissions'))
            ->addStep(
                'ensure-expect-script-is-executable',
                function () {
                    $path = __DIR__ . '/../_files/ssh-with-password.exp';

                    if (!is_executable($path)) {
                        Exec::run(
                            sprintf('chmod +x %s', escapeshellarg($path)),
                            $output,
                            $exitStatus
                        );
                    }
                }
            )
            ->addStep(
                'check-for-public-key',
                function () use ($publicKey) {
                    if (!file_exists($publicKey)) {
                        throw new Exception('SSH keys have not been generated.  Run ssh:generate-keys.');
                    }
                }
            )
            ->addStep(
                'copy-public-key',
                $this->getProject()->scp($publicKey, '')
            )
            ->addStep(
                'create-ssh-folder',
                $this->getProject()->ssh('mkdir -p .ssh')
            )
            ->addStep(
                'allow-authorized-keys-writes',
                $this->getProject()->ssh('touch .ssh/authorized_keys && chmod +w .ssh/authorized_keys')
            )
            ->addStep(
                'label-key',
                $this->getProject()->ssh(
                    sprintf(
                        'echo %s >> .ssh/authorized_keys',
                        escapeshellarg(
                            sprintf('# Delta CLI key for %s', $this->getProject()->getName())
                        )
                    )
                )
            )
            ->addStep(
                'add-key',
                $this->getProject()->ssh('cat id_rsa.pub >> .ssh/authorized_keys')
            )
            ->addStep(
                'change-authorized-keys-permissions',
                $this->getProject()->ssh('chmod 400 .ssh/authorized_keys')
            )
            ->addStep(
                'change-ssh-folder-permissions',
                $this->getProject()->ssh('chmod 700 .ssh')
            )
            ->addStep($this->getProject()->logAndSendNotifications());
    }
}
