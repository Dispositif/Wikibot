<?php


namespace App\Domain;

/**
 * Class TextUtil
 */
abstract class TextUtil
{
    /**
     * UTF8 first letter in upper case
     *
     * @param string      $str
     * @param string|null $e
     *
     * @return string
     */
    static public function mb_ucfirst(string $str, ?string $e = 'UTF-8'): string
    {
        $first = mb_strtoupper(mb_substr($str, 0, 1, $e), $e);
        $rest = mb_substr($str, 1, mb_strlen($str, $e), $e);

        return $first.$rest;
    }

    /**
     * Strip punctuation
     * UTF-8 compatible ??
     * See http://fr.wikipedia.org/wiki/Ponctuation
     *
     * @return string
     */
    static public function strip_punctuation(string $str)
    {
        return str_replace(
            [
                '!',
                '"',
                '«',
                '»',
                '#',
                '$',
                '%',
                '&',
                "'",
                '’',
                '´',
                '`',
                '^',
                '…',
                '‽',
                '(',
                ')',
                '*',
                '⁂',
                '+',
                ',',
                '-',
                '–',
                '—',
                '.',
                '/',
                ':',
                ';',
                '?',
                '@',
                '[',
                '\\',
                ']',
                '_',
                '`',
                '{',
                '|',
                '¦',
                '}',
                '~',
                '<',
                '>',
                '№',
                '©',
                '®',
                '°',
                '†',
                '§',
                '∴',
                '∵',
                '¶',
                '•',
                '+',
                '-',
            ],
            '',
            $str
        );
    }

    static public function containsNonLatinCharacters(string $str): bool
    {
        return preg_match('/[^\\p{Common}\\p{Latin}]/u', $str);
    }

    /**
     * Simplest levenshtein distance prediction of the correct param name.
     * Weird results with ASCII extended chars :
     * levenshtein('notre','nôtre') => 2
     * TODO move
     *
     * @param string $str
     * @param array  $names
     * @param int    $max Maximum number of permutation/add/subtraction)
     *
     * @return string|null
     */
    static public function predictCorrectParam(
        string $str,
        array $names,
        int $max = 2
    ): ?string {
        $shortest = -1;
        $closest = '';
        $str2 = mb_strtolower(self::stripAccents($str));
        foreach ($names as $name) {
            if ($str === $name) {
                return $name; // exact match
            }
            $lev = levenshtein($str, $name);
            $lev2 = levenshtein(
                $str2,
                mb_strtolower(self::stripAccents($name))
            );
            if ($lev < $shortest || $lev2 < $shortest || $shortest === -1) {
                $closest = $name;
                $shortest = $lev;
            }
        }
        if ($shortest <= $max) {
            return $closest;
        }

        return null;
    }

    /**
     * Strip accents
     * OK : grec, cyrillique, macron, hatchek, brève, rond en chef, tilde
     * UTF-8 compatible
     *
     * @return string
     */
    static public function stripAccents(string $string): string
    {
        return strtr(
            utf8_decode($string),
            utf8_decode(
                'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝøāǟǡēḕḗḡḹīōȫȭȱṑṓǭṝūǖṻȳǣӣᾱῑῡčšžйўŭăӗğÅåůẘẙ'
            ),
            utf8_decode(
                'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUYoaaaeeeglioooooooruuuyæиαιυcszиyuaegAauwy'
            )
        );
    }

}
