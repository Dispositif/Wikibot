<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\Bazar;

use Exception;

/**
 * Section d'un page discussion.
 * Class TalkSection
 */
class TalkSection
{
    /**
     * option : x (regex multilignes, espaces trimés)
     * u : unicode
     */
    const REGEX_SIGNATURE = '/\[\[(?:utilisateur|utilisatrice|user|discussion\sutilisateur|discussion\sutilisatrice|user\stalk)\:
                        ([^\]]+)\|[^\]]+\]\]                      # capture username
                        \s?[^\n\[\]\{\}]*                           # extra (parentheses and space)
                        \s(\d{1,2}\s[a-zéàû]+\s20\d\d\sà\s\d{1,2}\:\d\d\s\([A-Z]{3,4}\))  # capture date 12 février 2000 à 09:28 (XXX)
                        /ixu';

    /**
     * @var string
     */
    protected $title = '';
    /**
     * @var array [TalkMessage]
     */
    protected $messages;

    protected $raw;

    public function __construct(string $title, string $raw)
    {
        $this->title = $title;
        $this->raw = $raw;
        $this->messages = self::extractMessagesFromRaw($raw);
    }

    /**
     * todo : DI => move TalkFactory ou static TalkMessage
     * todo : supprimer les "messages" sans user ?
     * Split a section text into collection of TalkMessage.
     * todo : format date variable selon préférences ??
     *
     * @param string $content
     *
     * @return TalkMessage[]
     * @throws Exception
     */
    static private function extractMessagesFromRaw(string $content): array
    {
        $signatures = self::extractSignatures($content);

        if (empty($signatures)) {
            return [new TalkMessage(0, $content)];
        }

        $splits = preg_split(self::REGEX_SIGNATURE, $content, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($splits)) {
            throw new Exception('signature pattern preg_split result is not an array');
        }

        $messages = [];
        foreach ($splits as $k => $text) {
            // hack: sign undefined for bottom text (after the last signature)
            // todo : supprimer carrément ? (pas un message).
            if (!isset($signatures[$k])) {
                $signatures[$k] = ['', '', ''];
            }

            $mess = new TalkMessage($k, $text.$signatures[$k][0]);
            $mess->setIndentation(self::indentLevelFromText($text));
            $mess->setRawDate(trim($signatures[$k][2]));
            $mess->setUser(trim($signatures[$k][1]));

            $messages[] = $mess;
        }

        return $messages;
    }

    /**
     * @param string $content
     *
     * @return array [ 0=> [ '[[discussion utilisateur:bob|bob]]) 1 janvier 2000', 'bob', '1 janvier 2000'] ]
     */
    static public function extractSignatures(string $content): array
    {
        // — Thibaut (discuter) 12 février 2015 à 09:28 (CET)
        // enwiki : --Irønie (talk) 15:18, 2 November 2009 (UTC)
        // PCRE options : global (_all) (not stop at first match, so catch both user+user_talk links
        // option x : strip spaces for multiline regex and comment #
        if (preg_match_all(self::REGEX_SIGNATURE, $content, $signatures, PREG_SET_ORDER)) {
            return $signatures;
        }

        return [];
    }

    /**
     * todo move TalkMessage ?
     * Count numbers of ":" (indentation) starting the signature line.
     *
     * @param string $text
     *
     * @return int
     */
    static public function indentLevelFromText(string $text): int
    {
        $lines = explode(PHP_EOL, trim($text));
        $last = (string)array_pop($lines);
        // note \: is not a redondant escape char ! (PhpStorm bug)
        if (preg_match('#^([\:]*).+#', $last, $matches)) {
            return strlen($matches[1]);
        }

        return 0;
    }

    /**
     * @param array $messages
     */
    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

}
