<?php

namespace DeltaCli\FileWatcher;

use DeltaCli\Script;
use DeltaCli\Script\Step\Script as ScriptStep;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FileWatcherFactory
{
    public static function factory(InputInterface $input, OutputInterface $output)
    {
        // We should get "Darwin" on any Mac OS install, indicating we can run the Fsevents watcher
        if ('Darwin' === php_uname('s')) {
            return new Fsevents($input, $output);
        } else if (extension_loaded('inotify')) {
            return new Inotify($input, $output);
        } else {
            return new NullWatcher();
        }
    }

    public static function createWatchCallback(
        FileWatcherInterface $fileWatcher,
        Script $script,
        $onlyNotifyOnFailure,
        $stopOnFailure
    ) {
        $previousRunFailed = false;

        return function () use (&$previousRunFailed, $fileWatcher, $script, $onlyNotifyOnFailure, $stopOnFailure) {
            $timestamp = date('Y-m-d G:i:s');

            $fileWatcher->getOutput()->writeln(
                "<comment>Running {$script->getName()} script at {$timestamp}...</comment>"
            );

            $scriptStep = new ScriptStep($script, $fileWatcher->getInput(), $fileWatcher->getOutput());
            $scriptStep->setUseConsoleOutput(true);

            if ($script->getEnvironment()) {
                $scriptStep->setSelectedEnvironment($script->getEnvironment());
            }

            try {
                $result = $scriptStep->run();
                $result->render($fileWatcher->getOutput());

                $fileWatcher->getOutput()->writeln(['']);

                if (!$onlyNotifyOnFailure || $result->isFailure() || $previousRunFailed) {
                    $fileWatcher->displayNotification($script, $result);
                }

                if ($result->isFailure()) {
                    $previousRunFailed = true;
                } else {
                    $previousRunFailed = false;
                }
            } catch (Exception $e) {
                $fileWatcher->getOutput()->writeln(
                    [
                        '<error>Error encountered when running script during watch.</error>',
                        '',
                        $e->getMessage()
                    ]
                );
            }
        };
    }
}
