<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher\Traits;

use App\Domain\Enums\Language;
use DateTime;
use Exception;

/**
 * Typo cleaning, email delete, convert date to d-m-Y, etc.
 */
trait MapperConverterTrait
{
    protected function isAnArticle(?string $str): bool
    {
        return in_array($str, ['article', 'journalArticle']);
    }

    protected function convertDCpage(array $meta): ?string
    {
        if (isset($meta['citation_firstpage'])) {
            $page = $meta['citation_firstpage'];
            if (isset($meta['citation_lastpage'])) {
                $page .= '–' . $meta['citation_lastpage'];
            }

            return (string)$page;
        }

        return null;
    }

    /**
     * Note : à appliquer AVANT wikification (sinon bug sur | )
     *
     * @param string|null $str
     *
     * @return string|null
     */
    protected function clean(?string $str = null): ?string
    {
        if ($str === null) {
            return null;
        }
        $str = $this->stripEmailAdress($str);

        $str = str_replace(
            [
                '|',
                "\n",
                "\t",
                "\r",
                '&#x27;',
                '&#39;',
                '&#039;',
                '&apos;',
                "\n",
                "&#10;",
                "&eacute;",
                '©',
                '{{',
                '}}',
                '[[',
                ']]',
            ],
            [
                '/',
                ' ',
                ' ',
                '',
                "’",
                "'",
                "'",
                "'",
                '',
                ' ',
                "é",
                '',
                '',
                '',
                '',
                '',
            ],
            $str
        );

        $str = html_entity_decode($str);
        $str = strip_tags($str);

        return trim($str);
    }

    protected function stripEmailAdress(?string $str = null): ?string
    {
        if ($str === null) {
            return null;
        }

        return preg_replace('# ?[^ ]+@[^ ]+\.[A-Z]+#i', '', $str);
    }

    protected function convertOGtype2format(?string $ogType): ?string
    {
        if (empty($ogType)) {
            return null;
        }
        // og:type = default: website / video.movie / video.tv_show video.other / article, book, profile
        if (strpos($ogType, 'video') !== false) {
            return 'vidéo';
        }
        if (strpos($ogType, 'book') !== false) {
            return 'livre';
        }

        return null;
    }

    /**
     * https://developers.facebook.com/docs/internationalization#locales
     * @param string|null $lang
     *
     * @return string|null
     */
    protected function convertLangue(?string $lang = null): ?string
    {
        if (empty($lang)) {
            return null;
        }
        // en_GB
        if (preg_match('#^([a-z]{2})_[A-Z]{2}$#', $lang, $matches)) {
            return $matches[1];
        }

        return Language::all2wiki($lang);
    }

    protected function convertDate(?string $str): ?string
    {
        if (empty($str)) {
            return null;
        }
        $str = str_replace(' 00:00:00', '', $str);
        $str = str_replace('/', '-', $str);

        // "2012" - "1775-1783" (Gallica)
        if (preg_match('#^[12]\d{3}$#', $str) || preg_match('#^[12]\d{3}-[12]\d{3}$#', $str)) {
            return $str;
        }

        return $this->tryFormatDateOrComment($str);
    }

    protected function tryFormatDateOrComment(string $str): string
    {
        try {
            $date = new DateTime($str);
        } catch (Exception $e) {
            // 23/11/2015 00:00:00
            if (isset($this->log) && method_exists($this->log, 'notice')) {
                $this->log->notice('tryFormatDateOrComment failed with ' . $str);
            }

            return sprintf('<!-- %s -->', $str);
        }

        return $date->format('d-m-Y');
    }
}
