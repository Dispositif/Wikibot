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
use Psr\Log\LoggerInterface;


/**
 * From a titles list, scan the wiki and add the {ouvrage} citations into the database.
 */
class ScanWiki2DB
{
    protected LoggerInterface $logger;

    public function __construct(
        private readonly MediawikiFactory   $wiki,
        private readonly DbAdapterInterface $db,
        private readonly WikiBotConfig      $bot,
        private readonly PageListInterface  $pageList,
        private readonly int                $priority = 0
    )
    {
        $this->logger = $this->bot->getLogger();
        $this->process();
    }

    /**
     * @throws Exception
     */
    public function process(): void
    {
        $titles = $this->pageList->getPageTitles();
        if ($titles === []) {
            $this->logger->info("pageList vide.");

            return;
        }
        foreach ($titles as $title) {
            $this->pageScan($title);
            sleep(4);
        }
    }

    /**
     * @throws Exception
     */
    public function pageScan(string $title): bool
    {
        sleep(2);
        $this->logger->notice(sprintf('%s - %s', date("Y-m-d H:i:s"), $title));

        $page = new WikiPageAction($this->wiki, $title); // todo injection
        $ns = $page->getNs();
        if ($ns !== 0) {
            $this->logger->debug("SKIP : namespace $ns");

            return false;
        }
        $text = $page->getText();
        if (empty($text)) {
            $this->logger->debug("SKIP : empty text");

            return false;
        }

        try {
            $parsedTemplates = TemplateParser::parseAllTemplateByName('ouvrage', $text);
        } catch (Exception $e) {
            $this->logger->error("SKIP : parse error " . $e->getMessage());

            return false;
        }

        if (empty($parsedTemplates)) {
            return false;
        }

        $result = $this->insertDB($parsedTemplates['ouvrage'], $title);

        return !empty($result);
    }

    protected function insertDB(array $ouvrages, string $title): bool|array
    {
        $data = [];
        foreach ($ouvrages as $res) {
            $oneData = [
                'page' => $title,
                'raw' => $res['raw'],
                'priority' => $this->priority,
            ];

            if ((strlen($title) > 250) || empty($oneData['raw']) || strlen($oneData['raw']) > 2500) {
                $this->logger->warning("Skip : string to long : ", $oneData);
                continue;
            }

            // filter duplicates
            if (!in_array($oneData, $data)) {
                $data[] = $oneData;
            }
        }

        if (empty($data)) {
            $this->logger->notice("Skip : empty data");
            return false;
        }

        $result = $this->db->insertPageOuvrages($data);

        if ($result === false) {
            $this->logger->error("Insert DB failed");
        } else {
            $this->logger->notice("Insert DB : ", $result);
        }

        return $result;
    }
}
