<?php

namespace DeltaCli\Script\Step;

use DeltaCli\Script as ScriptObject;

class LogAndSendNotifications extends DeltaApiAbstract
{
    public function getName()
    {
        if ($this->name) {
            return $this->name;
        } else {
            return 'log-and-send-notifications';
        }
    }

    public function run()
    {
        $response = $this->apiClient->getProject($this->apiClient->getProjectKey());

        if (200 === $response->getStatusCode()) {
            $result = new Result($this, Result::SUCCESS);
            $result->setStatusMessage('is ready to run at the end of this script');
        } else {
            if ('application/json' !== $response->getHeaderLine('Content-Type')) {
                $output = $response->getReasonPhrase();
            } else {
                $json   = json_decode($response->getBody(), true);
                $output = sprintf('%s (%s)', $json['message'], $json['code']);
            }

            $result = new Result($this, Result::FAILURE, $output);
            $result->setExplanation('because there was a problem communicating with Delta API');
        }

        return $result;
    }

    public function postRun(ScriptObject $script)
    {
        $this->output->writeln('<comment>Logging and sending notifications via Delta API...</comment>');

        $response = $this->apiClient->postResults($script->getApiResults());

        if (200 === $response->getStatusCode()) {
            $this->output->writeln('<info>Successfully logged results and sent notifications.</info>');
        } else {
            $this->output->writeln('<error>There was an error sending the results to the Delta API</error>');

            if ('application/json' !== $response->getHeaderLine('Content-Type')) {
                $this->output->writeln('  ' . $response->getReasonPhrase());
            } else {
                $json = json_decode($response->getBody(), true);
                $this->output->writeln(sprintf('  %s (%s)', $json['message'], $json['code']));
            }
        }
    }
}