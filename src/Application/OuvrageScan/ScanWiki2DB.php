<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageScan;

use App\Application\InfrastructurePorts\DbAdapterInterface;
use App\Application\InfrastructurePorts\PageListForAppInterface as PageListInterface;
use App\Application\WikiBotConfig;
use App\Application\WikiPageAction;
use App\Domain\Utils\TemplateParser;
use Exception;
use Mediawiki\Api\MediawikiFactory;


/**
 * From a titles list, scan the wiki and add the {ouvrage} citations into the database.
 */
class ScanWiki2DB
{
    private $wiki;
    private $db;
    private $bot;
    /**
     * @var PageListInterface
     */
    private $pageList;
    private $priority;

    public function __construct(
        MediawikiFactory   $wiki,
        DbAdapterInterface $dbAdapter,
        WikiBotConfig      $bot,
        PageListInterface  $list,
        ?int               $priority
        = 0
    ) {
        $this->wiki = $wiki; // ServiceFactory::wikiApi();
        $this->db = $dbAdapter;
        $this->bot = $bot;
        $this->pageList = $list;
        $this->priority = $priority;

        $this->process();
    }

    /**
     * @throws Exception
     */
    public function process():void
    {
        $titles = $this->pageList->getPageTitles();
        if ($titles === []) {
            echo "pageList vide.\n";

            return;
        }
        foreach ($titles as $title) {
            $this->pageScan($title);
            sleep(4);
        }
    }

    /**
     * @param string $title
     *
     * @return bool
     * @throws Exception
     */
    public function pageScan(string $title): bool
    {
        sleep(2);
        echo "\n-------------------------------------\n\n";
        echo date("Y-m-d H:i:s")."\n";
        echo $title."\n";

        $page = new WikiPageAction($this->wiki, $title); // todo injection
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
            $parsedTemplates = TemplateParser::parseAllTemplateByName('ouvrage', $text);
        } catch (Exception $e) {
            dump($e);

            return false;
        }

        if ($parsedTemplates === []) {
            return false;
        }

        $result = $this->insertDB($parsedTemplates['ouvrage'], $title);

        return !empty($result);
    }

    /**
     * @param        $ouvrages
     * @param string $title
     *
     * @return mixed
     */
    protected function insertDB($ouvrages, string $title)
    {
        $data = [];
        foreach ($ouvrages as $res) {
            $oneData = [
                'page' => $title,
                'raw' => $res['raw'],
                'priority' => $this->priority,
            ];
            // filtre doublon
            if (!in_array($oneData, $data)) {
                $data[] = $oneData;
            }
        }

        $result = $this->db->insertPageOuvrages($data);
        dump($result);

        return $result;
    }

}
