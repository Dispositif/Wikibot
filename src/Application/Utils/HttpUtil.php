<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Utils;

use DomainException;
use Normalizer;
use Throwable;

class HttpUtil
{

    /**
     * Better than filter_var($url, FILTER_VALIDATE_URL) because it's not multibyte capable.
     * See for example .中国 domain name
     */
    public static function isHttpURL(string $url): bool
    {
        return (bool)preg_match('#^https?://[^ ]+$#i', $url);
    }

    /**
     * Normalize and converting to UTF-8 encoding
     */
    public static function normalizeHtml(string $html, ?string $url = ''): ?string
    {
        if (empty($html)) {
            return $html;
        }

        $html2 = Normalizer::normalize($html);

        if (is_string($html2) && !empty($html2)) {
            return $html2;
        }

        $charset = self::extractCharset($html) ?? 'WINDOWS-1252';
        if (empty($charset)) {
            throw new DomainException('normalized html error and no charset found : ' . $url);
        }
        try {
            // PHP Notice:  iconv(): Detected an illegal character in input string
            $html2 = @iconv($charset, 'UTF-8//TRANSLIT', $html);
            if (false === $html2) {
                throw new DomainException("error iconv : $charset to UTF-8 on " . $url);
            }
            $html2 = Normalizer::normalize($html2);
            if (!is_string($html2)) {
                throw new DomainException("error normalizer : $charset to UTF-8 on " . $url);
            }
        } catch (Throwable $e) {
            throw new DomainException("error converting : $charset to UTF-8 on " . $url, $e->getCode(), $e);
        }

        return $html2;
    }

    /**
     * Extract charset from HTML text
     */
    private static function extractCharset(string $html): ?string
    {
        if (preg_match(
            '#<meta(?!\s*(?:name|value)\s*=)(?:[^>]*?content\s*=[\s"\']*)?([^>]*?)[\s"\';]*charset\s*=[\s"\']*([^\s"\'/>]*)#',
            $html,
            $matches
        )
        ) {
            $charset = $matches[2] ?? $matches[1] ?? null;
        }
        if (empty($charset)) {

            $encoding = mb_detect_encoding($html, null, true);
            $charset = is_string($encoding) ? strtoupper($encoding) : null;
        }

        return $charset;
    }
}
