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

    /**
     * Current page title, so processRefContent() implementations (ExternRefWorker) can
     * pass it down to ExternRefTransformer — needed to record which page cites a URL
     * that failed transiently (see ExternLinkCheckRepositoryInterface). Without it, a
     * page visited once is permanently excluded from future discovery by
     * WorkerAnalyzedTitlesTrait, so a failure recorded without its page is unactionable.
     */
    protected ?string $currentTitle = null;

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
        $this->currentTitle = $title;

        return $this->processText($text);
    }

    /**
     * @throws Exception
     */
    public function processText(string $text): string
    {
        $refs = WikiTextUtil::extractRefsAndListOfLinks(WikiTextUtil::filterSensitiveCommentsInText($text));
        if ($refs === []) {
            $this->log->debug('empty extractRefsAndListOfLinks');

            return $text;
        }

        // Avoid memory leak problem : bot limited to N refs in an article
        $refs = array_slice($refs, 0, self::MAX_REFS_PROCESSED_IN_ARTICLE, true);

        foreach ($refs as $ref) {
            $refContent = WikiTextUtil::stripFinalPoint(trim((string)$ref[1]));

            $newRefContent = $this->processRefContent($refContent);

            $text = $this->replaceRefInText($ref, $newRefContent, $text);
        }

        return $text;
    }

    public abstract function processRefContent(string $refContent): string;

    protected function replaceRefInText(array $ref, string $replace, string $text): string
    {
        // Pas de changement
        if (WikiTextUtil::stripFinalPoint(trim($replace)) === WikiTextUtil::stripFinalPoint(trim((string)$ref[1]))) {
            return $text;
        }
        $replace = $this->addFinalPeriod($ref[0], $replace);
        $result = str_replace($ref[1], $replace, (string)$ref[0]);

        // Skip if only trim() difference (no cosmetic edit with only trailing space or \n inside <ref>)
        if ($replace === trim($ref[1])) {
            $this->log->debug('Skip cosmetic trim ref difference', ['stats' => 'refbotworker.skip.trimdifference']);
            return $text;
        }

        $this->printDiff($ref[0], $result);

        return str_replace($ref[0], $result, $text);
    }

    /**
     * Add a final period '.' before an eventual '</ref>'
     */
    protected function addFinalPeriod($ref, string $replace): string
    {
        if (preg_match('#</ref>#', (string)$ref)) {
            $replace .= '.';
        }
        return $replace;
    }

    protected function printDiff(string $before, string $after, string $level = 'info'): void
    {
        $this->log->log($level, sprintf("%s--%s %s\n", Color::BG_LIGHT_RED, Color::NORMAL, $before));
        $this->log->log($level, sprintf("%s++%s %s\n", Color::BG_LIGHT_GREEN, Color::NORMAL, $after));
    }
}
