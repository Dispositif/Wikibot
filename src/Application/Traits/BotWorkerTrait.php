<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Traits;

use App\Application\WikiBotConfig;
use Codedungeon\PHPCliColors\Color;
use Mediawiki\Api\UsageException;

trait BotWorkerTrait
{
    /**
     * @throws UsageException
     */
    protected function checkAllowedNowEdition(string $title, string $text): bool
    {
        $this->bot->checkStopOnTalkpage(true);

        if (WikiBotConfig::isEditionTemporaryRestrictedOnWiki($text)) {
            echo "SKIP : protection/3R/travaux.\n";

            return false;
        }
        if ($this->bot->minutesSinceLastEdit($title) < static::MINUTES_DELAY_AFTER_LAST_HUMAN_EDIT) {
            echo sprintf(
                "SKIP : édition humaine dans les dernières %s minutes\n",
                static::MINUTES_DELAY_AFTER_LAST_HUMAN_EDIT
            );

            return false;
        }
        if (static::SKIP_ADQ && preg_match('#{{ ?En-tête label ?\| ?AdQ#i', $text)) {
            echo "SKIP : AdQ.\n"; // BA ??

            return false;
        }

        return true;
    }

    protected function printTitle(string $title): void
    {
        echo "---------------------\n";
        echo date('d-m-Y H:i:s') . ' ' . Color::BG_CYAN . "  $title " . Color::NORMAL . "\n";
    }

    protected function canProcessTitleArticle(string $title, ?string $text): bool
    {
        if (empty($text)) {
            echo "Skip : page vide\n";
            $this->memorizeAndSaveAnalyzedTitle($title);

            return false;
        }
        if (static::SKIP_LASTEDIT_BY_BOT && $this->pageAction->getLastEditor() === getenv('BOT_NAME')) {
            echo "Skip : bot est le dernier éditeur\n";
            $this->memorizeAndSaveAnalyzedTitle($title);

            return false;
        }
        return (bool) $this->checkAllowedNowEdition($title, $text);
    }

    protected function isSomethingToChange(?string $text, ?string $newText): bool
    {
        if (empty($newText)) {
            echo "Skip: vide\n";

            return false;
        }
        if ($newText === $text) {
            echo "Skip: pas de changement\n";

            return false;
        }

        return true;
    }
}