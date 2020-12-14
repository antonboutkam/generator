<?php

namespace Generator\Admin\Module\Controller\Overview;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Symfony\Component\Console\Output\OutputInterface;

class Generator {

    private GeneratorConfigInterface $config;
    private OutputInterface $output;

    public function __construct(GeneratorConfigInterface $config, OutputInterface $oOutput) {
        $this->config = $config;
        $this->output = $oOutput;
    }

    /**
     * @return string
     */
    public function generate(): string {

        $oNamespace = new PhpNamespace($this->config->getNamespace());
        $oNamespace->addUse($this->config->getBaseNamespace());

        $oClass = new ClassType('OverviewController');
        $oClass->setFinal(true);
        $oClass->addExtend($this->config->getBaseNamespace() . '\\OverviewController');

        $oClass->setComment("Skeleton subclass for drawing a list of " . $this->config->getPhpName() . " records.");
        $oClass->addComment(str_repeat(PHP_EOL, 2));
        $oClass->addComment("You should add additional methods to this class to meet the");
        $oClass->addComment("application requirements.  This class will only be generated as");
        $oClass->addComment("long as it does not already exist in the output directory.");

        $oNamespace->add($oClass);
        return $oNamespace;
    }
}