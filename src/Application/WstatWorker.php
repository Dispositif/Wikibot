<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\IsbnFacade;
use App\Domain\Utils\TemplateParser;
use App\Infrastructure\CorpusAdapter;
use App\Infrastructure\MessageAdapter;
use App\Infrastructure\WstatImport;
use GuzzleHttp\Client;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class WstatWorker extends FileWorker
{
    public function run(?SymfonyStyle $io = null)
    {
        $corp = new CorpusAdapter();
        echo 'WORKER '.__CLASS__."\n";
        echo "To exit press CTRL+C\n";

        $wstat = new WstatImport(
            new Client(['timeout' => 10]), [
            'title' => 'Ouvrage',
            'query' => 'inclusions',
            'param' => 'isbn',
            'start' => 50000,
            'limit' => 500,
        ], 1000
        );
        $datas = $wstat->getData();

        // TODO : data -> getISBN -> sql
        // todo generator?
        foreach ($datas as $data) {
            if (empty($data['template'])) {
                continue;
            }
            $tpData = $this->getTemplateData($data['template']);

            if (!empty($tpData['isbn'])) {
                // todo ISBN validator
                $this->saveIsbn($data['title'], $tpData['isbn']);
            }
            if (!empty($tpData['isbn2'])) {
                $this->saveIsbn($data['title'], $tpData['isbn2']);
            }
        }

        // TODO data title/template -> SQL
        // TODO entity+doctrine ?
        exit;
    }

    private function getTemplateData(string $string): array
    {
        try {
            $tpData = TemplateParser::parseDataFromTemplate('ouvrage', $string);
        } catch (Throwable $e) {
            echo sprintf(
                "EXCEPTION %s %s %s on string %s \n",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $string
            );
            // TODO log?
            $tpData = [];
        }

        return $tpData;
    }

    public function saveISBN(string $page, string $isbn): void
    {
        $isbnMachine = new IsbnFacade($isbn);
        // validate ISBN
        if (!$isbnMachine->isValid()) {
            echo sprintf("Notice : ISBN %s not valid \n", $isbn);

            return;
        }

        $data = ['page' => $page, 'isbn' => $isbn];
        $msg = json_encode($data);

        (new MessageAdapter())->amqpMsg('isbnRequest', $msg);
        echo "Queue isbnRequest: $page, $isbn \n";
        sleep(1);
    }

    public function process($io)
    {
        $name = $io->ask('Continue ?');
        echo time()."\n";
    }
}
