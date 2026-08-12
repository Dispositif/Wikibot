<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * "[url Titre] Consulté le 06/07/2009.", "[url Titre] (consulté le 23 janvier 2009)." --
 * ~9.6% of the corpus, ~2.1% of it (like both examples above) with no comma before it,
 * which is why it needs its own extractor rather than piggybacking on the comma-based
 * ones : ordering after TrailingDateExtractor in the chain means it also picks up the
 * common "[url Titre], 30 juin 2011 (consulté le 6 avril 2020)." shape, where the
 * citation date is consumed first and this extractor only sees what's left,
 * "(consulté le 6 avril 2020).".
 *
 * The dominant real-world form in the "no comma" bucket is actually numeric
 * "JJ/MM/AAAA", not the textual "12 mars 2019" form -- both are handled ;
 * numericToFrenchText() reformats the numeric form into the same textual shape the rest
 * of this parser produces, after validating it's a real calendar date.
 */
final class ConsulteLeExtractor implements HintExtractorInterface
{
    private const PATTERN = '#^,?\s*\(?\s*[Cc]onsult\S*\s+(?:le|du|sur\s+\S+\s+le)\s+(?:(?<textday>\d{1,2})\s+(?<textmonth>' . FrenchDate::MONTHS_PATTERN . ')\s+(?<textyear>\d{4})|(?<numday>\d{1,2})[/.\-](?<nummonth>\d{1,2})[/.\-](?<numyear>\d{2,4}))\)?\.?\s*(?<remaining>.*)$#iu';

    public function extract(string $rest): ?HintMatch
    {
        if (trim($rest) === '' || !preg_match(self::PATTERN, $rest, $m)) {
            return null;
        }

        $value = $this->resolveValue($m);
        if ($value === null) {
            return null;
        }

        return new HintMatch('consulté le', $value, $m['remaining'] ?? '');
    }

    private function resolveValue(array $m): ?string
    {
        if (!empty($m['textday'])) {
            $day = (int) $m['textday'];
            $year = (int) $m['textyear'];

            return FrenchDate::isValidCalendarDate($day, $m['textmonth'], $year)
                ? trim($m['textday'] . ' ' . $m['textmonth'] . ' ' . $m['textyear'])
                : null;
        }

        if (!empty($m['numday'])) {
            return $this->numericToFrenchText((int) $m['numday'], (int) $m['nummonth'], (int) $m['numyear']);
        }

        return null;
    }

    /**
     * "06/07/2009" -> "6 juillet 2009" (French DD/MM/YYYY convention, never MM/DD).
     */
    private function numericToFrenchText(int $day, int $month, int $year): ?string
    {
        if ($year < 100) {
            $year += ($year < 70) ? 2000 : 1900;
        }
        $monthName = FrenchDate::monthName($month);
        if ($monthName === null || !checkdate($month, $day, $year)) {
            return null;
        }

        return $day . ' ' . $monthName . ' ' . $year;
    }
}
