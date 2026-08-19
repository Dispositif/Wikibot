<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\ExternLink;

use App\Application\AbstractBotTaskWorker;
use App\Application\InfrastructurePorts\PageListForAppInterface as PageListInterface;
use App\Application\WikiBotConfig;
use App\Domain\Exceptions\ConfigException;
use App\Domain\ExternLink\LienBriseArchiveFixer;
use Mediawiki\Api\MediawikiFactory;
use Throwable;

/**
 * Scans a page for existing {{Lien brisé}} and re-attempts a web archive on each one's
 * url= via LienBriseArchiveFixer — the mass-campaign counterpart of
 * fixDeadLinkNetworkFailureBug.php's one-off, titles-file-driven script. Whole-page text
 * in/out (unlike ExternRefWorker/ExistingRefWorker, which operate per-<ref>) : a
 * {{Lien brisé}} isn't necessarily wrapped in a <ref>, and there is nothing else on the
 * page for this worker to look at or transform.
 */
class LienBriseArchiveWorker extends AbstractBotTaskWorker
{
    public const TASK_BOT_FLAG = true;
    public const JOURNAL_TASK = 'lien-brise-archive';
    public const ARTICLE_ANALYZED_FILENAME = __DIR__ . '/../resources/article_lienBriseArchive_edited.txt';

    protected $modeAuto = true;

    protected LienBriseArchiveFixer $fixer;

    public function __construct(
        WikiBotConfig           $bot,
        MediawikiFactory        $wiki,
        ?PageListInterface      $pagesGen = null,
        ?LienBriseArchiveFixer  $fixer = null,
        bool                    $dryRun = false
    ) {
        if (!$fixer instanceof LienBriseArchiveFixer) {
            throw new ConfigException('LienBriseArchiveFixer not set');
        }
        $this->fixer = $fixer;

        parent::__construct($bot, $wiki, $pagesGen, $dryRun);
    }

    protected function processWithDomainWorker(string $title, string $text): ?string
    {
        try {
            return $this->fixer->fixText($text, $this->summary);
        } catch (Throwable $e) {
            $this->log->critical(
                'Error lienBriseArchive: ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine(),
                ['stats' => 'lienbrisearchive.exception']
            );

            return $text;
        }
    }

    /**
     * "🏛️ Correction {lien brisé} → InternetArchive" when every fix in this edit came
     * from Internet Archive (the expected/priority case — see DeadLinkTransformer's
     * archiver order) ; adapts to name Wikiwix too when that's what actually resolved,
     * rather than hardcode a source the edit didn't use.
     */
    protected function generateSummaryText(): string
    {
        $prefixSummary = $this->summary->isBotFlag() ? 'bot: ' : '';
        $wayback = (int) ($this->summary->memo['wayback'] ?? 0);
        $wikiwix = (int) ($this->summary->memo['wikiwix'] ?? 0);

        $emojis = [];
        $sources = [];
        if ($wayback > 0) {
            $emojis[] = '🏛️';
            $sources[] = 'InternetArchive' . ($wayback > 1 ? ' x' . $wayback : '');
        }
        if ($wikiwix > 0) {
            $emojis[] = '🥝';
            $sources[] = 'Wikiwix' . ($wikiwix > 1 ? ' x' . $wikiwix : '');
        }

        return trim($prefixSummary . implode('', $emojis) . ' Correction {lien brisé} → ' . implode(' + ', $sources));
    }
}
