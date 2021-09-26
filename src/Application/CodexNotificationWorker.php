<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application;


use App\Infrastructure\DbAdapter;
use App\Infrastructure\Logger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;

class CodexNotificationWorker extends NotificationWorker
{
    const ARTICLE_ANALYZED_FILENAME = __DIR__.'/resources/article_externRef_edited.txt';
    const PROCESS_TASKNAME          = '🔔🌐 Amélioration de références : URL ⇒ ';

    /**
     * todo Refac that stupid idea :)
     * Delete article title in the log text file.
     *
     * @param $title
     */
    private function deleteEditedArticleFile(string $title): void
    {
        $text = file_get_contents(self::ARTICLE_ANALYZED_FILENAME);
        $newText = str_replace($title."\n", '', $text);
        if (!empty($text) && $text !== $newText) {
            @file_put_contents(self::ARTICLE_ANALYZED_FILENAME, $newText);
        }
    }

    /**
     * Process external URL completion to wiki-template.
     *
     * @param string      $article
     * @param string|null $username
     */
    private function processExternLinks(string $article, ?string $username = '')
    {
        try {
            $wiki = ServiceFactory::wikiApi();
            $logger = new Logger();
            //$logger->debug = true;
            $botConfig = new WikiBotConfig($logger);
            $botConfig->taskName = self::PROCESS_TASKNAME;
            new ExternRefWorker($botConfig, $wiki, new PageList([$article]));

            new GoogleBooksWorker($botConfig, $wiki, new PageList([$article]));
            sleep(10);
        } catch (\Throwable $e) {
            unset($e);
        }
    }

    /**
     * Process wikiSan for future {ouvrage} completion
     *
     * @param string $article
     */
    private function processWikiscanForOuvrage(string $article): void
    {
        try {
            $wiki = ServiceFactory::wikiApi();
            $list = new PageList([$article]);
            new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig(), $list, 15);
        } catch (\Throwable $e) {
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
            $this->processExternLinks($article);
        }
    }
}
