<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
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
    final public const BOT_TALK_SUMMARY = 'Réponse artificielle[[User:Irønie|.]]';
    final public const BOT_TALK_FILE = __DIR__ . '/resources/phrases_zizibot.txt';
    final public const TALKCONFIG_FILENAME = __DIR__ . '/resources/botTalk_config.json';

    /**
     * Add a freaky response in the bottom of the talk page.
     * @throws UsageException
     */
    public function botTalk(?string $pageTitle = null): bool
    {
        $talkConfig = $this->getTalkConfig();

        // ugly dependency
        $wiki = ServiceFactory::getMediawikiFactory();
        if (!$pageTitle) {
            $pageTitle = 'Discussion utilisateur:' . $this::getBotName();
        }
        $page = new WikiPageAction($wiki, $pageTitle);
        $last = $page->page->getRevisions()->getLatest();

        // No response if the last edition from bot or bot owner
        if (!$last->getUser() || 'Flow talk page manager' === $last->getUser()
            || $last->getUser() === $this::getBotName()
            || $last->getUser() == $this::getBotOwner()
        ) {
            return false;
        }

        // blacklist users
        if (in_array($last->getUser(), self::BLACKLIST_EDITOR)) {
            return false;
        }

        // No response if time < 24h since last bot owner response
        if ($last->getUser() == self::getBotOwner()) {
            $talkConfig['owner_last_time'] = (int)strtotime($last->getTimestamp());
            file_put_contents(self::TALKCONFIG_FILENAME, json_encode($talkConfig, JSON_THROW_ON_ERROR));

            return false;
        }
        // No response if time < 24h since last owner response
        if (isset($talkConfig['owner_last_time']) && (int)$talkConfig['owner_last_time'] > (time() - 60 * 60 * 48)) {
            echo "No response if time < 24h after last owner response\n";

            return false;
        }

        $indentation = $this->predictTalkIndentation($page->getText() ?? '', $last->getUser()); // ':::'
        $addText = $this->generateTalkText($last->getUser(), $indentation);

        echo "Prepare to talk on $pageTitle / Sleep 2 min...\n";
        echo sprintf("-> %s \n", $addText);
        sleep(120);

        $editInfo = new EditInfo(static::BOT_TALK_SUMMARY);
        $success = $page->addToBottomOfThePage($addText, $editInfo);

        return (bool)$success;
    }

    /**
     * @throws Exception
     */
    private function generateTalkText(?string $toEditor = null, ?string $identation = ':'): string
    {
        if ($toEditor === 'Flow talk page manager') {
            $toEditor = null;
        }
        $to = ($toEditor) ? sprintf('@[[User:%s|%s]] : ', $toEditor, $toEditor) : ''; // {{notif}}
        $sentence = TextUtil::mb_ucfirst($this->getRandomSentence());
        if ($sentence === '') {
            throw new Exception('no sentence');
        }

        return sprintf('%s%s%s --~~~~', $identation, $to, $sentence);
    }

    /**
     * Stupid ":::" talk page indentation prediction.
     *
     * @return string ":::"
     */
    private function predictTalkIndentation(string $text, ?string $author = null): string
    {
        // extract last line
        $lines = explode("\n", trim($text));
        $lastLine = $lines[count($lines) - 1];
        if (preg_match('#^(:*).+#', $lastLine, $matches) && !empty($matches[1])) {
            $nextIdent = $matches[1] . ':';
            if (empty($author)) {
                return $nextIdent;
            }
            // search author signature link to check that he wrote on the page bottom
            if (preg_match(
                '#\[\[(?:User|Utilisateur|Utilisatrice):' . preg_quote($author, '#') . '[|\]]#i',
                $matches[0]
            )
            ) {
                return $nextIdent;
            }
        }

        return ':';
    }

    /**
     * @throws ConfigException
     */
    private function getRandomSentence(): string
    {
        $sentences = file(self::BOT_TALK_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($sentences)) {
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
        // TODO client
        $url
            = 'https://fr.wikipedia.org/w/api.php?action=query&list=usercontribs&ucuser=' . $this::getBotName()
            . '&ucnamespace=0&uclimit=40&ucprop=title|timestamp|comment&format=json';

        return file_get_contents($url);
    }

    private function getTalkConfig(): ?array
    {
        try {
            $text = file_get_contents(self::TALKCONFIG_FILENAME);

            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }
    }
}
