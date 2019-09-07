<?php

namespace App\Command;

use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PageContentCommand extends Command
{
    // the name of the command
    protected static $defaultName = 'bot:page';

    protected function configure()
    {
        $this->setDescription('get text content from page')->setHelp(
            'get text content from a page'
        );
        $this->addArgument('title', InputArgument::REQUIRED, 'page title');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $api = new MediawikiApi($_ENV['API_URL']);
        try{
            $api->login(
                new ApiUser($_ENV['API_USERNAME'], $_ENV['API_PASSWORD'])
            );
        }catch (\Throwable $e){
            die('Exception '.$e);
        }
        $services = new MediawikiFactory($api);

        $page = $services->newPageGetter()->getFromTitle(
            $input->getArgument('title')
        );
        $text = $page->getRevisions()->getLatest()->getContent()->getData();

        $output->writeln('*** TEXT ***');
        $output->writeln($text);
    }
}
