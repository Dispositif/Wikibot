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

    /**
     * Strip punctuation
     * UTF-8 compatible ??
     * See http://fr.wikipedia.org/wiki/Ponctuation
     *
     * @return string
     */
    static public function strip_punctuation($string)
    {
        return str_replace(
            [
                '!', '"', '«', '»', '#', '$', '%', '&', "'", '’', '´', '`', '^', '…', '‽', '(', ')', '*', '⁂', '+', ',', '-', '–', '—', '.', '/', ':', ';', '?', '@', '[', '\\', ']', '_', '`', '{', '|', '¦', '}', '~', '<', '>', '№', '©', '®', '°', '†', '§', '∴', '∵', '¶', '•', '+', '-'
            ],
            '',
            $string
        );
    }

}
