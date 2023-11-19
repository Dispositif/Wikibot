<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

namespace App\Application;

use App\Infrastructure\ServiceFactory;
use Mediawiki\DataModel\EditInfo;

/**
 * Add a banner {{bot day}} to the top of one page.
 * Call by cron during june.
 */
class BotDayAction
{
    protected const ADD_STRING = '{{bot day}}';
    protected const TEMPLATE = 'Modèle:Bot day';
    protected const EDIT_SUMMARY = 'Ajout {bot day}';
    protected const BOT_FLAG = true;

    public function __construct(private readonly WikiBotConfig $bot, array $titles = [])
    {
        if (!$this->checkBannerExists()) {
            echo sprintf("> Problème avec le modèle %s \n", self::TEMPLATE);
            sleep(60);
        } else {
            shuffle($titles);
            foreach ($titles as $title) {
                $this->checkAddBanner($title);
            }
        }
    }

    private function checkBannerExists(): bool
    {
        $pageAction = ServiceFactory::wikiPageAction(self::TEMPLATE);
        $text = $pageAction->getText();
        if (empty($text) || $pageAction->isRedirect()) {
            return false;
        }

        return true;
    }

    protected function checkAddBanner(string $title): void
    {
        $pageAction = ServiceFactory::wikiPageAction($title);
        $text = $pageAction->getText();
        if ($pageAction->getNs() === 0) {
            echo sprintf("> Not main namespace on %s \n", $title);
            sleep(10);
            return;
        }
        if ($pageAction->isRedirect()) {
            echo sprintf("> Redirect on %s \n", $title);
            return;
        }
        if (empty($text)) {
            echo sprintf("> Empty text on %s \n", $title);
            return;
        }
        if (WikiBotConfig::isEditionTemporaryRestrictedOnWiki($text, 'CodexBot')) {
            echo sprintf("> Skip : protection/3R/nobots on %s \n", $title);
            return;
        }
        if ($text && !$this->hasBanner($text)) {
            $res = false;
            $editInfo = new EditInfo(self::EDIT_SUMMARY, false, self::BOT_FLAG);
            $res = $pageAction->addToTopOfThePage(self::ADD_STRING, $editInfo);
            echo sprintf("> Add banner on %s : %s \n", $title, $res ? 'OK' : 'ERROR');

            exit; // other pages, other days (crontab)
        }

        sleep(30);
    }

    protected function hasBanner(string $text): bool
    {
        return preg_match('#\{\{ ?bot[_ ]day ?\}\}#i', $text) > 0;
    }
}
