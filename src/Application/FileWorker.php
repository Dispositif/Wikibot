<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\CorpusAdapter;
use Symfony\Component\Console\Style\SymfonyStyle;

class FileWorker
{
    /**
     * @param SymfonyStyle|null $io
     */
    public function run(?SymfonyStyle $io = null)
    {
        $corp = new CorpusAdapter();

        echo 'WORKER '.__CLASS__."\n";
        echo "To exit press CTRL+C\n";

        while (true) {
            $this->process($io);
            sleep(5);
        }
    }

    public function process($io)
    {
        $name = $io->ask('Continue ?');
        echo time()."\n";
    }
}
//
//$console = new Application('worker', '0.1');
//$console->addCommand(new WorkerCommand());
//$worker = new FileWorker();
//$worker->run();
