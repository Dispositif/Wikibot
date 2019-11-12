<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\ServiceFactory;
use Mediawiki\DataModel\EditInfo;

/**
 * Freaky customization of Bot class
 * Class ZiBot.
 */
class ZiBot extends Bot
{
    const BOT_TALK_SUMMARY = 'Réponse super intelligente';

    /**
     * Add a freaky response in the bottom of the talk page.
     *
     * @return bool
     *
     * @throws \Mediawiki\Api\UsageException
     */
    protected function botTalk(): bool
    {
        // ugly dependency
        $wiki = ServiceFactory::wikiApi();
        $page = new WikiPageAction($wiki, getenv('BOT_USER_PAGE'));
        $last = $page->page->getRevisions()->getLatest();

        // No response if the last edition from bot or bot owner
        if (in_array($last->getUser(), [getenv('BOT_NAME'), getenv('BOT_OWNER')])) {
            // compare with timestamp
            return false;
        }

        $allSentences = explode("\n", file_get_contents(__DIR__.'/resources/phrases_bot.csv'));
        @shuffle($allSentences);
        $sentence = (string) ucfirst($allSentences[0]);
        $contrib = sprintf(
            ':@%s : %s —~~~~',
            $sentence,
            $last->getUser()
        );

        echo "Prepare to talk...\n";
        echo sprintf('-> %s \n', $contrib);
        sleep(60);

        $summary = static::BOT_TALK_SUMMARY;
        $editInfo = new EditInfo($summary);
        $success = $page->addToBottomOfThePage($contrib, $editInfo);

        return $success;
    }
}
