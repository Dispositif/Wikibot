<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Utils\TextUtil;
use App\Infrastructure\ServiceFactory;
use Exception;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;

/**
 * Freaky customization of Bot class
 * Class ZiziBot.
 */
class ZiziBot extends Bot
{
    const BOT_TALK_SUMMARY = 'Réponse artificielle';
    const BOT_TALK_FILE    = __DIR__.'/resources/phrases_zizibot.txt';

    /**
     * Add a freaky response in the bottom of the talk page.
     *
     * @return bool
     * @throws UsageException
     * @throws Exception
     */
    public function botTalk(): bool
    {
        // ugly dependency
        $wiki = ServiceFactory::wikiApi();
        $page = new WikiPageAction($wiki, 'Discussion utilisateur:'.getenv('BOT_NAME'));
        $last = $page->page->getRevisions()->getLatest();

        // No response if the last edition from bot or bot owner
        if (!$last->getUser() || in_array($last->getUser(), [getenv('BOT_NAME'), getenv('BOT_OWNER')])) {
            // compare with timestamp
            return false;
        }

        $addText = $this->generateTalkText($last->getUser());

        echo "Prepare to talk. Sleep 5 min...\n";
        echo sprintf("-> %s \n", $addText);
        sleep(300);

        $editInfo = new EditInfo(static::BOT_TALK_SUMMARY);
        $success = $page->addToBottomOfThePage($addText, $editInfo);

        return (bool)$success;
    }

    /**
     * @param string|null $toEditor
     * @param string|null $identation
     *
     * @return string
     * @throws Exception
     */
    private function generateTalkText(?string $toEditor = null, ?string $identation = ':')
    {
        $to = ($toEditor) ? sprintf('@%s : ', $toEditor) : ''; // {{notif}}
        $sentence = TextUtil::mb_ucfirst($this->getRandomSentence());
        if(!$sentence){
            throw new Exception('no sentence');
        }

        return sprintf('%s%s%s --~~~~', $identation, $to, $sentence);
    }

    private function getRandomSentence(): ?string
    {
        $sentences = file(self::BOT_TALK_FILE);
        if(!$sentences){
            return null;
        }
        return (string)trim($sentences[array_rand($sentences)]);
    }

    /**
     * Todo
     * https://www.mediawiki.org/wiki/API:Usercontribs
     */
    public function botContribs(): string
    {
        $url
            = 'https://fr.wikipedia.org/w/api.php?action=query&list=usercontribs&ucuser=ZiziBot&ucnamespace=0&uclimit=40&ucprop=title|timestamp|comment&format=json';

        return file_get_contents($url);
    }

}
