<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PageContentCommand extends Command
{
    protected static $defaultName = 'bot:page';

    protected function configure()
    {
        $this->setDescription('get text content from page')->setHelp(
            'get text content from a page'
        );
        $this->addArgument('title', InputArgument::REQUIRED, 'page title');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $page = new PageAction($input->getArgument('title'));
        $text = $page->getText();

        $output->writeln('*** TEXT ***');
        $output->writeln($text);
    }

}
