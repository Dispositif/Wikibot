<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exceptions\ConfigException;
use App\Domain\Utils\TextUtil;
use App\Infrastructure\ServiceFactory;
use Exception;
use Mediawiki\Api\UsageException;
use Mediawiki\DataModel\EditInfo;

/**
 * Freaky customization of WikiBotConfig class
 * Class TalkBotConfig.
 */
class TalkBotConfig extends WikiBotConfig
{
    const BOT_TALK_SUMMARY = 'Réponse artificielle';

    const BOT_TALK_FILE       = __DIR__.'/resources/phrases_zizibot.txt';
    const TALKCONFIG_FILENAME = __DIR__.'/resources/botTalk_config.json';

    /**
     * Add a freaky response in the bottom of the talk page.
     *
     * @param string|null $pageTitle
     *
     * @return bool
     * @throws UsageException
     */
    public function botTalk(?string $pageTitle = null): bool
    {
        $talkConfig = $this->getTalkConfig();

        // ugly dependency
        $wiki = ServiceFactory::wikiApi();
        if (!$pageTitle) {
            $pageTitle = 'Discussion utilisateur:'.getenv('BOT_NAME');
        }
        $page = new WikiPageAction($wiki, $pageTitle);
        $last = $page->page->getRevisions()->getLatest();

        // No response if the last edition from bot or bot owner
        if (!$last->getUser() || 'Flow talk page manager' === $last->getUser()
            || in_array($last->getUser(), [getenv('BOT_NAME')])
        ) {
            return false;
        }
        // No response if time < 24h since last bot owner response
        if (in_array($last->getUser(), [getenv('BOT_OWNER')])) {
            $talkConfig['owner_last_time'] = intval(strtotime($last->getTimestamp()));
            file_put_contents(self::TALKCONFIG_FILENAME, json_encode($talkConfig));

            return false;
        }
        // No response if time < 24h since last owner response
        if (isset($talkConfig['owner_last_time']) && intval($talkConfig['owner_last_time']) > (time() - 60 * 60 * 24)) {
            echo "No response if time < 24h after last owner response\n";
            return false;
        }

        $indentation = $this->predictTalkIndentation($page->getText() ?? '', $last->getUser()); // ':::'
        $addText = $this->generateTalkText($last->getUser(), $indentation);

        echo "Prepare to talk on $pageTitle / Sleep 3 min...\n";
        echo sprintf("-> %s \n", $addText);
        sleep(180);

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
        if ($toEditor === 'Flow talk page manager') {
            $toEditor = null;
        }
        $to = ($toEditor) ? sprintf('@[[User:%s|%s]] : ', $toEditor, $toEditor) : ''; // {{notif}}
        $sentence = TextUtil::mb_ucfirst($this->getRandomSentence());
        if (!$sentence) {
            throw new Exception('no sentence');
        }

        return sprintf('%s%s%s --~~~~', $identation, $to, $sentence);
    }

    /**
     * Stupid ":::" talk page indentation prediction.
     *
     * @param string $text
     * @param string $author
     *
     * @return string ":::"
     */
    private function predictTalkIndentation(string $text, ?string $author = null): string
    {
        // extract last line
        $lines = explode("\n", trim($text));
        $lastLine = $lines[count($lines) - 1];
        if (preg_match('#^(:*).+#', $lastLine, $matches)) {
            if (!empty($matches[1])) {
                $nextIdent = $matches[1].':';
                if (empty($author)) {
                    return $nextIdent;
                }
                // search author signature link to check that he wrote on the page bottom
                if (preg_match(
                    '#\[\[(?:User|Utilisateur|Utilisatrice):'.preg_quote($author).'[|\]]#i',
                    $matches[0]
                )
                ) {
                    return $nextIdent;
                }
            }
        }

        return ':';
    }

    /**
     * @return string|null
     * @throws ConfigException
     */
    private function getRandomSentence(): string
    {
        $sentences = file(self::BOT_TALK_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$sentences) {
            throw new ConfigException('Pas de phrases disponibles pour TalkBot');
        }

        return (string)trim($sentences[array_rand($sentences)]);
    }

    /**
     * Todo
     * https://www.mediawiki.org/wiki/API:Usercontribs.
     */
    public function botContribs(): string
    {
        $url
            = 'https://fr.wikipedia.org/w/api.php?action=query&list=usercontribs&ucuser='.getenv('BOT_NAME')
            .'&ucnamespace=0&uclimit=40&ucprop=title|timestamp|comment&format=json';

        return file_get_contents($url);
    }

    private function getTalkConfig(): ?array
    {
        try {
            $text = file_get_contents(self::TALKCONFIG_FILENAME);

            return json_decode($text, true);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
