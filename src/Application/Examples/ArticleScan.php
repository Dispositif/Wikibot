<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\Bot;
use App\Application\WikiPageAction;
use App\Domain\Utils\TemplateParser;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\ServiceFactory;
use Exception;

//use App\Application\CLI;

include __DIR__.'/../myBootstrap.php';

/**
 * From json list of articles => add to SQL TempRawOpti
 */
$process = new ArticleScan();
//$json = file_get_contents(__DIR__.'/../resources/titles.json');
//$articles = json_decode($json, true);

$articles = file(__DIR__.'/../resources/importISBN_nov.txt');

foreach ($articles as $article) {
    $article = trim($article);
    if (empty($article)) {
        continue;
    }
    $process->pageScan($article, 0);
    sleep(4);
}

class ArticleScan
{
    private $wiki;
    private $db;
    private $bot;

    public function __construct()
    {
        $this->wiki = ServiceFactory::wikiApi();
        $this->db = new DbAdapter();
        $this->bot = new Bot();
    }

    public function pageScan(string $title, ?int $priority = 0): bool
    {
        sleep(2);
        echo "\n-------------------------------------\n\n";
        echo date("Y-m-d H:i:s")."\n";
        echo $title."\n";

        $page = new WikiPageAction($this->wiki, $title);
        $text = $page->getText();
        if (empty($text)) {
            echo "SKIP : texte vide\n";

            return false;
        }

        // Skip AdQ
        if (preg_match('#{{ ?En-tête label#i', $text) > 0) {
            echo "SKIP : AdQ ou BA.\n";

            //$this->db->skipArticle($title);

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

        $result = $this->db->insertTempRawOpti($data);
        dump($result);

        return !empty($result);
    }

}
