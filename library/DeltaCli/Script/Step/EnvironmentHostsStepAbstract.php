<?php

namespace DeltaCli\Script\Step;

use DeltaCli\Environment;
use DeltaCli\Exception\EnvironmentNotAvailableForStep;
use DeltaCli\Host;

abstract class EnvironmentHostsStepAbstract extends StepAbstract implements EnvironmentAwareInterface
{
    /**
     * @var Environment
     */
    protected $environment;

    /**
     * @var bool
     */
    protected $limitToOnlyFirstHost = false;

    abstract public function runOnHost(Host $host);

    public function setSelectedEnvironment(Environment $environment)
    {
        $this->environment = $environment;

        return $this;
    }

    public function run()
    {
        return $this->runOnAllHosts();
    }

    public function limitToOnlyFirstHost($limitToOnlyFirstHost = true)
    {
        $this->limitToOnlyFirstHost = $limitToOnlyFirstHost;

        return $this;
    }

    protected function runOnAllHosts()
    {
        if (!$this->environment) {
            throw new EnvironmentNotAvailableForStep();
        }

        $output        = [];
        $verboseOutput = [];

        $failedHosts        = [];
        $misconfiguredHosts = [];

        /* @var $host Host */
        foreach ($this->environment->getHosts() as $index => $host) {
            if (!$host->hasRequirementsForSshUse()) {
                $misconfiguredHosts[] = $host;
                continue;
            }

            if ($this->limitToOnlyFirstHost && $index) {
                $output[] = sprintf(
                    "<fg=cyan>%s skipped because this step is limited to the first host only.</>",
                    $host->getHostname()
                );

                continue;
            }

            $hostResult = $this->runOnHost($host);

            if ($hostResult instanceof Result) {
                return $hostResult;
            } elseif (3 === count($hostResult)) {
                list($hostOutput, $exitStatus, $verboseHostOutput) = $hostResult;
            } else {
                list($hostOutput, $exitStatus) = $hostResult;
                $verboseHostOutput = [];
            }

            if ($exitStatus) {
                $failedHosts[] = $host;
            }

            $hostnameComment = '<comment>' . $host->getHostname() . '</comment>';

            if ($hostOutput && count($hostOutput)) {
                $output[] = $hostnameComment;

                foreach ($hostOutput as $line) {
                    $output[] = '  ' . $line;
                }
            }

            if (count($verboseHostOutput)) {
                $verboseOutput[] = $hostnameComment;

                foreach ($verboseHostOutput as $line) {
                    $verboseOutput[] = '  ' . $line;
                }
            }
        }

        return $this->generateResult($failedHosts, $misconfiguredHosts, $output, $verboseOutput);
    }

    protected function generateResult(array $failedHosts, array $misconfiguredHosts, array $output, array $verboseOutput)
    {
        if (count($this->environment->getHosts()) && !count($failedHosts) && !count($misconfiguredHosts)) {
            $result = new Result($this, Result::SUCCESS, $output);
            $result->setExplanation($this->getSuccessfulResultExplanation($this->environment->getHosts()));
        } else {
            $result = new Result($this, Result::FAILURE, $output);

            if (!count($this->environment->getHosts())) {
                $result->setExplanation('because no hosts were added in the environment');
            } else {
                $explanations = [];

                if (count($failedHosts)) {
                    $explanations[] = count($failedHosts) . ' host(s) failed';
                }

                if (count($misconfiguredHosts)) {
                    $explanations[] = count($misconfiguredHosts) . ' host(s) were not configured for SSH';
                }

                $result->setExplanation('because ' . implode(' and ', $explanations));
            }
        }

        $result->setVerboseOutput($verboseOutput);

        return $result;
    }

    protected function getSuccessfulResultExplanation(array $hosts)
    {
        if (1 !== count($hosts)) {
            return sprintf('on all %d hosts', count($hosts));
        } else {
            /* @var $host Host */
            $host = current($hosts);
            return sprintf('on %s', $host->getHostname());
        }
    }
}
