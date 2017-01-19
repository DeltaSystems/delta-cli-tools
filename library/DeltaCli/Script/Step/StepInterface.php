<?php

namespace DeltaCli\Script\Step;

use DeltaCli\Environment;
use DeltaCli\Script;
use Symfony\Component\Console\Input\InputDefinition;

interface StepInterface
{
    public function setEnvironments(array $environments);

    public function configure(InputDefinition $inputDefinition);

    public function preRun(Script $script);

    /**
     * @return Result
     */
    public function run();

    public function addStepToScript(Script $script, StepInterface $step);

    public function postRun(Script $script);

    public function getName();

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name);

    public function appliesToEnvironment(Environment $environment);
}
