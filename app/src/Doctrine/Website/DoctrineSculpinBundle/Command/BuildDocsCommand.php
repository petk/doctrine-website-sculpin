<?php

namespace Doctrine\Website\DoctrineSculpinBundle\Command;

use Sculpin\Core\Console\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildDocsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('build-docs')
            ->setDescription('Build the RST and API docs.')
            ->addOption(
                'project',
                null,
                InputOption::VALUE_REQUIRED,
                'The project to build the docs for.'
            )
            ->addOption(
                'v',
                null,
                InputOption::VALUE_REQUIRED,
                'The project version to build the docs for.'
            )
            ->addOption(
                'search',
                null,
                InputOption::VALUE_NONE,
                'Build the search indexes.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $projectToBuild = (string) $input->getOption('project');
        $versionToBuild = (string) $input->getOption('v');
        $buildSearchIndexes = (bool) $input->getOption('search');

        $buildDocs = $this->getContainer()->get('doctrine.docs.build_docs');

        $buildDocs->build(
            $output,
            $projectToBuild,
            $versionToBuild,
            $buildSearchIndexes
        );
    }
}
