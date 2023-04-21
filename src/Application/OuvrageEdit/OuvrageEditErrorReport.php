<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageEdit;

class OuvrageEditErrorReport
{
    /**
     * Liste les erreurs reportées.
     * 0 => "|editor=JT Staley, MP Bryant, N Pfennig, and JG Holt, eds. <!--PARAMETRE 'editor' N'EXISTE PAS -->"
     * 1 => "|editor=DR Boone and RW Castenholz, eds. <!--PARAMETRE 'editor' N'EXISTE PAS -->".
     *
     * @param string $text
     *
     * @return array|null
     */
    public function getReport(string $text): ?array
    {
        if (preg_match_all(
                '#\\* <span style="background:\#FCDFE8"><nowiki>([^\n]+)</nowiki></span>\n#',
                $text,
                $matches
            ) > 0
        ) {
            // FIXED zizibot : des '<nowiki>' supplémentaires ajoutés à quelques rapports
            return str_replace('<nowiki>', '', $matches[1]);
        }

        return null;
    }

    public function countErrorInText(array $errors, string $text): int
    {
        $found = 0;
        foreach ($errors as $error) {
            if (false !== mb_strpos($text, $error)) {
                ++$found;
            }
        }

        return $found;
    }

    /**
     * Delete the previous bot errorReporting message from text.
     *
     * @param string      $text
     * @param string|null $botName
     *
     * @return string
     */
    public function deleteAllReports(string $text, ?string $botName = 'CodexBot'): string
    {
        $pattern = sprintf(
            '#== Ouvrage avec erreur de paramètre ==(.*)Le robot \[\[Utilisateur:%s\|%s\]\] \(\[\[Discussion utilisateur:%s\|[^\]]+\]\]\) [0-9a-zéà: ]+ \([A-Z]+\)[\n]*#s',
            $botName,
            $botName,
            $botName
        );
        // option s : dot matches new lines
        $text = preg_replace(
            $pattern,
            '',
            $text
        );

        return $text;
    }
}
