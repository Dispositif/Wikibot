<?php

declare(strict_types=1);

namespace App\Application;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class WorkerCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'bot:worker';

    protected function configure()
    {
        $this->setDescription('run a worker')->setHelp(
            'launch a worker script'
        );
        $this->addArgument('workerName', InputArgument::OPTIONAL, 'worker name');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Prevent multiple executions of the same command in a single server
        // todo: install symfony/lock or implement naive SQL lock
        //        if (!$this->lock()) {
        //            $output->writeln('The command is already running in another process.');
        //            return 0;
        //        }

        //        $helper = $this->getHelper('question');
        //        $question = new Question('Please select a limit for this execution: ', 25);
        //        $limit = $helper->ask($input, $output, $question);

        $workerName = $input->getArgument('workerName');
        $io = new SymfonyStyle($input, $output);

        if (!$workerName) {
            $workerName = $io->ask('Quel worker ?');
            $workerName = strtolower($workerName);
        }

        $bot = new Bot();

        if ('wstat' === $workerName) {
            $worker = new WstatWorker();
            $worker->run();
        }
        if ('fileworker' === $workerName) {
            $worker = new FileWorker(); // todo inject $io dans construct() ?
            $worker->run($io);
        }

        echo "Pas de $workerName ici !\n";
        exit('EXIT');
    }
}
