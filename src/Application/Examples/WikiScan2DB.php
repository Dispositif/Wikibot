<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\WikiBotConfig;
use App\Application\WikiPageAction;
use App\Domain\Utils\TemplateParser;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\ServiceFactory;
use Exception;

//use App\Application\CLI;

include __DIR__.'/../myBootstrap.php';

/**
 * From json list of articles => add to SQL page_ouvrages
 */
$process = new WikiScan2DB();

$articles = file(__DIR__.'/../resources/importISBN_nov.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($articles as $article) {
    $article = trim($article);
    if (empty($article)) {
        continue;
    }
    $process->pageScan($article, 0);
    sleep(4);
}

class WikiScan2DB
{
    private $wiki;
    private $db;
    private $bot;

    public function __construct()
    {
        $this->wiki = ServiceFactory::wikiApi();
        $this->db = new DbAdapter();
        $this->bot = new WikiBotConfig();
    }

    public function pageScan(string $title, ?int $priority = 0): bool
    {
        sleep(2);
        echo "\n-------------------------------------\n\n";
        echo date("Y-m-d H:i:s")."\n";
        echo $title."\n";

        $page = new WikiPageAction($this->wiki, $title);
        $ns = $page->getNs();
        if ($ns !== 0) {
            echo "SKIP : namespace $ns";

            return false;
        }
        $text = $page->getText();
        if (empty($text)) {
            echo "SKIP : texte vide\n";

            return false;
        }

        try {
            $parse = TemplateParser::parseAllTemplateByName('ouvrage', $text);
        } catch (Exception $e) {
            dump($e);

            return false;
        }

        if (empty($parse)) {
            return false;
        }
        $data = [];
        foreach ($parse['ouvrage'] as $res) {
            $thisdata = [
                'page' => $title,
                'raw' => $res['raw'],
                'priority' => $priority,
            ];
            // filtre doublon
            if (!in_array($thisdata, $data)) {
                $data[] = $thisdata;
            }
        }

        $result = $this->db->insertPageOuvrages($data);
        dump($result);

        return !empty($result);
    }

}
