<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\Notification;


use App\Application\ExternLink\ExternRefWorker;
use App\Application\GoogleBooksWorker;
use App\Application\InfrastructurePorts\PageListForAppInterface;
use App\Application\OuvrageScan\ScanWiki2DB;
use App\Application\WikiBotConfig;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use Throwable;

class CodexNotificationWorker extends NotificationWorker
{
    public const ARTICLE_ANALYZED_FILENAME = __DIR__.'/../resources/article_externRef_edited.txt';
    public const PROCESS_TASKNAME          = '🔔 Amélioration de références : URL ⇒ ';

    /**
     * todo Refac that stupid idea :) — forces a re-pass by un-marking the title as
     * analyzed for the extern-ref task (ARTICLE_ANALYZED_FILENAME is that task's
     * journal file), even though processExternLinks() below actually runs
     * GoogleBooksWorker, not ExternRefWorker. Pre-existing mismatch, preserved as-is —
     * not this migration's call to fix.
     */
    private function deleteEditedArticleFile(string $title): void
    {
        ServiceFactory::getBotEditJournal(self::ARTICLE_ANALYZED_FILENAME)
            ->forgetAnalyzed($title, ExternRefWorker::JOURNAL_TASK);
    }

    /**
     * Process external URL completion to wiki-template.
     */
    private function processExternLinks(PageListForAppInterface $pageList)
    {
        try {
            $wiki = ServiceFactory::getMediawikiFactory(); // todo inject+interface
            $botConfig = new WikiBotConfig($wiki, $this->logger);
            $botConfig->setTaskName(self::PROCESS_TASKNAME);
            //new ExternRefWorker($botConfig, $wiki, new PageList([$article], null, new InternetDomainParser()));

            new GoogleBooksWorker($botConfig, $wiki, $pageList);
            sleep(10);
        } catch (Throwable $e) {
            unset($e);
        }
    }

    /**
     * Process wikiSan for future {ouvrage} completion
     */
    private function processWikiscanForOuvrage(string $article): void
    {
        try {
            $wiki = ServiceFactory::getMediawikiFactory(); // todo inject+interface
            $list = new PageList([$article]);
            // todo inject+interface DbAdapterInterface
            new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig($wiki, $this->logger), $list, 15);
        } catch (Throwable $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @param $notif
     */
    protected function processSpecialActions($notif)
    {
        if (isset($notif['title']) && in_array($notif['title']['namespace'], ['', 'Discussion'])) {
            // PROCESS ARTICLES
            $article = $notif['title']['text'];

            // wikiScan for {ouvrage} completion
            $this->processWikiscanForOuvrage($article);


            // URL => wiki-template completion
            $this->deleteEditedArticleFile($article);
            $this->processExternLinks(new PageList([$article])); // todo pagelist factory
        }
    }
}
