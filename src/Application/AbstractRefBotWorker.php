<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application;


use App\Domain\Utils\WikiTextUtil;
use Codedungeon\PHPCliColors\Color;
use Exception;

abstract class AbstractRefBotWorker extends AbstractBotTaskWorker
{
    public const TASK_BOT_FLAG = false;
    public const MAX_REFS_PROCESSED_IN_ARTICLE = 30;

    protected $warning = false;

    public function hasWarning(): bool
    {
        return (bool)$this->warning;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function processWithDomainWorker(string $title, string $text): ?string
    {
        return $this->processText($text);
    }

    /**
     * @param $text
     *
     * @return string|string[]
     * @throws Exception
     */
    public function processText($text)
    {
        $refs = WikiTextUtil::extractRefsAndListOfLinks($text);
        if ($refs === []) {
            $this->log->debug('empty extractRefsAndListOfLinks');

            return $text;
        }

        // Avoid memory leak problem : bot limited to N refs in an article
        $refs = array_slice($refs, 0, self::MAX_REFS_PROCESSED_IN_ARTICLE, true);

        foreach ($refs as $ref) {
            $refContent = WikiTextUtil::stripFinalPoint(trim($ref[1]));

            $newRefContent = $this->processRefContent($refContent);

            $text = $this->replaceRefInText($ref, $newRefContent, $text);
        }

        return $text;
    }

    public abstract function processRefContent($refContent): string;

    protected function replaceRefInText(array $ref, string $replace, string $text)
    {
        // Pas de changement
        if (WikiTextUtil::stripFinalPoint(trim($replace)) === WikiTextUtil::stripFinalPoint(trim($ref[1]))) {
            return $text;
        }
        $replace = $this->addFinalPeriod($ref[0], $replace);
        $result = str_replace($ref[1], $replace, $ref[0]);
        $this->printDiff($ref[0], $result);

        return str_replace($ref[0], $result, $text);
    }

    /**
     * Add a final period '.' before an eventual '</ref>'
     */
    protected function addFinalPeriod($ref, string $replace): string
    {
        if (preg_match('#</ref>#', $ref)) {
            $replace .= '.';
        }
        return $replace;
    }

    protected function printDiff(string $before, string $after, string $level = 'debug'): void
    {
        $this->log->log($level, sprintf("%s--%s %s\n", Color::BG_LIGHT_RED, Color::NORMAL, $before));
        $this->log->log($level, sprintf("%s--%s %s\n", Color::BG_LIGHT_GREEN, Color::NORMAL, $after));
    }
}
