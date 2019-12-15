<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

class ErrorReport
{
    const BOTNAME = 'ZiziBot';

    /**
     * Liste les erreurs reportées.
     * 0 => "|editor=JT Staley, MP Bryant, N Pfennig, and JG Holt, eds. <!--PARAMETRE 'editor' N'EXISTE PAS -->"
     * 1 => "|editor=DR Boone and RW Castenholz, eds. <!--PARAMETRE 'editor' N'EXISTE PAS -->"
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
            return $matches[1];
        }

        return null;
    }

    public function countErrorInText(array $errors, string $text): int
    {
        $found = 0;
        foreach ($errors as $error) {
            if (mb_strpos($text, $error) !== false) {
                $found++;
            }
        }

        return $found;
    }

    /**
     * Delete the previous bot errorReporting message from text.
     *
     * @param string $text
     *
     * @return string
     */
    public function deleteAllReports(string $text): string
    {
        $pattern = sprintf(
            '#== Ouvrage avec erreur de paramètre ==(.*)Le robot \[\[Utilisateur:%s\|%s\]\] \(\[\[Discussion utilisateur:%s\|discuter\]\]\) [0-9a-zéà: ]+ \(CET\)[\n]*#s',
            self::BOTNAME,
            self::BOTNAME,
            self::BOTNAME
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