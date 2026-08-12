<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw;

/**
 * Result of RawExternLinkParser::parse() : a "[url Libellé]" (or bare "[url]") fragment
 * broken into its structural parts, before any {{lien web}}/{{article}}/{{lien brisé}}
 * decision is made. Pure data, no behaviour — see docs in RawExternLinkParser.
 */
final class RawExternLinkDTO
{
    /**
     * @param string[] $leadingTemplates template names found immediately before the
     *     bracket (e.g. ['en'], ['pdf']) — stripped out of $leadingText.
     * @param array<string, string> $hints template params predicted from $rest by the
     *     Hints/ extractor chain (e.g. ['site' => 'presence-pc.com']) — already removed
     *     from $rest when matched. See Hints/HintExtractorInterface.
     */
    public function __construct(
        public readonly string $raw,
        public readonly string $url,
        public readonly ?string $titre,
        public readonly string $leadingText,
        public readonly array $leadingTemplates,
        public readonly string $rest,
        public readonly bool $isBullet,
        public readonly ?string $refName = null,
        public readonly ?string $refGroup = null,
        public readonly array $hints = [],
    ) {
    }

    /**
     * True when nothing is left unaccounted for : $leadingText is empty and $rest is
     * empty or just trailing punctuation. This is the "safe to auto-transform" signal —
     * see docs/situation-projet-WP-liens-externes.md and the Lot 1 test backlog.
     */
    public function isFullyConsumed(): bool
    {
        return trim($this->leadingText) === ''
            && preg_match('#^[\s.]*$#u', $this->rest) === 1;
    }
}
