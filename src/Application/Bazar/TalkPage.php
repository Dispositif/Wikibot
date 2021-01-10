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
 * todo extract Factory?
 * Page discussion en objet, avec les messages parsés en objets TalkPage.
 * Distinction sections, auteurs, dates, etc.
 * Class TalkPage
 */
class TalkPage
{
    const REGEX_SECTION = '#(==+[^=]+==+\n)#';

    private $title;

    private $raw;
    /**
     * @var TalkSection[]
     */
    private $sections;

    public function __construct(string $title, string $raw)
    {
        $this->title = $title;
        $this->raw = $raw;
        $this->sections = $this->extractTalkSections();
    }

    /**
     * @return TalkSection[]|array
     * @throws Exception
     */
    private function extractTalkSections(): array
    {
        $sectionTitles = $this->extractSectionTitles($this->raw);

        if (empty($sectionTitles)) {
            return [new TalkSection('', $this->raw)];
        }

        $sContents = preg_split(self::REGEX_SECTION, $this->raw);
        if (!is_array($sContents)) {
            throw new Exception('section pattern preg_split result is not an array');
        }

        $sections = [];
        foreach ($sectionTitles as $k => $title) {
            $sections[] = new TalkSection($title, $sContents[$k]);
        }

        return $sections;
    }

    /**
     * @return array
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    private function extractSectionTitles(string $raw): array
    {
        if (preg_match_all(self::REGEX_SECTION, $this->raw, $matches, PREG_PATTERN_ORDER) === false) {
            return [];
        }

        return array_merge([''], $matches[1]);
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

}
