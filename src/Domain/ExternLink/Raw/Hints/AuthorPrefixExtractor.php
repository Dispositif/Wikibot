<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * "Louis Laroque, [url Titre]" -- an author name (or, indistinguishably at this parsing
 * stage, an institution : "Sénat Belge,", "Gouvernement du Canada," match the exact same
 * shape) right before the bracket. Applied to $leadingText, not $rest -- unlike the other
 * extractors here, which is why RawExternLinkParser runs it through a separate chain.
 *
 * Also matches a wikilinked author/institution, "[[Université Johns-Hopkins]],", and
 * keeps the wikilink markup VERBATIM as the hint value (not dereferenced to display text
 * like site/périodique hints are) -- 'auteur' is a param editors routinely write as a
 * direct wikilink, so "auteur=[[Université Johns-Hopkins]]" is itself the correct,
 * renderable {{lien web}} value, not an intermediate form to clean up further.
 *
 * Deliberately requires the WHOLE leadingText to be either that single wikilink, or
 * "Capitalized Word(s)," (optionally with lowercase connectors
 * "de"/"du"/"van"/"von"/"der"/"la"/"le").
 *
 * Known limitation, not attempted here : author vs. institution vs. site publisher are
 * genuinely ambiguous from syntax alone ("Sénat Belge," is an institution, not a person)
 * -- all are stored under the 'auteur' hint ; disambiguating is left to whatever
 * downstream merger has crawled data to cross-check against.
 */
final class AuthorPrefixExtractor implements HintExtractorInterface
{
    private const PATTERN = "#^(\[\[[^\]]+\]\]|" . FrenchName::NAME_PATTERN . "),\s*\$#u";

    public function extract(string $rest): ?HintMatch
    {
        $text = trim($rest);
        if ($text === '' || !preg_match(self::PATTERN, $text, $m)) {
            return null;
        }

        return new HintMatch('auteur', trim($m[1]), '');
    }
}
