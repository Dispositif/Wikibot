<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Utils\TemplateParser;
use App\Infrastructure\CorpusAdapter;
use App\Infrastructure\WstatImport;
use GuzzleHttp\Client;
use Symfony\Component\Console\Style\SymfonyStyle;

class WstatWorker extends FileWorker
{
    public function run(?SymfonyStyle $io = null)
    {
        $corp = new CorpusAdapter();
        echo 'WORKER '.__CLASS__."\n";
        echo "To exit press CTRL+C\n";

        $wstat = new WstatImport(
            new Client(['timeout' => 2]), [
            'title' => 'Ouvrage',
            'query' => 'inclusions',
            'param' => 'isbn',
            'start' => 50000,
            'limit' => 50,
        ], 100
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

        // TODO : title/isbn -> sql

        // TODO data title/template -> SQL
        // TODO entity+doctrine ?
        exit;
    }

    private function getTemplateData(string $string): array
    {
        try {
            $tpData = TemplateParser::parseDataFromTemplate('ouvrage', $string);
        } catch (\Throwable $e) {
            echo sprintf(
                "EXCEPTION %s %s %s on string %s \n",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $string
            );
            // TODO log
            $tpData = [];
        }

        return $tpData;
    }

    public function saveISBN(string $page, string $isbn): void
    {
        // todo ISBN validator
        echo "SAVE : $page, $isbn \n";
    }

    public function process($io)
    {
        $name = $io->ask('Continue ?');
        echo time()."\n";
    }
}
