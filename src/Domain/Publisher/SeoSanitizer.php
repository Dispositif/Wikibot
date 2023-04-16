<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use App\Domain\Utils\TextUtil;

class SeoSanitizer
{
    private const MAX_LENGTH_FIRST_SEG_ALLOWING_SECOND_SEG = 30;
    private const REBUILD_SEPARATOR = ' - ';

    /**
     * Naive SEO sanitization of web page title.
     * pretty domain name as "google.com" or "google.co.uk"
     */
    public function cleanSEOTitle(string $prettyDomainName, ?string $title): ?string
    {
        if (empty($title) || empty(trim($title))) {
            return null;
        }
        $title = str_replace(['–', '—', '\\'], ['-', '-', '/'], $title); // replace em dash with hyphen

        $seoSegments = $this->extractSEOSegments($title);
        // No SEO segmentation found
        if (count($seoSegments) < 2) {
            return $title;
        }
        $seoSegmentsFiltered = $this->deleteSegmentsContainingSitename($prettyDomainName, $seoSegments);
        if ($seoSegmentsFiltered === []) {
            return trim($seoSegments[0]);
        }

        return $this->buildNewTitle($seoSegmentsFiltered);
    }

    /**
     * @param string[] $titleSegments
     */
    protected function buildNewTitle(array $titleSegments): string
    {
        // if only one segment or first segment is long enough, return it
        if (
            count($titleSegments) === 1
            || mb_strlen($titleSegments[0]) >= self::MAX_LENGTH_FIRST_SEG_ALLOWING_SECOND_SEG
        ) {
            return trim($titleSegments[0]);
        }

        // rebuild title but keep only the first 2 segments
        return trim($titleSegments[0]) . self::REBUILD_SEPARATOR . trim($titleSegments[1]);
    }

    /**
     * @return string[]
     */
    private function extractSEOSegments(string $title): array
    {
        $seoSeparator = $this->getSEOSeparator($title);

        return (null === $seoSeparator) ? [$title] : explode($seoSeparator, $title);
    }

    private function getSEOSeparator(string $title): ?string
    {
        // order is important. '-' before '/' ? see date, etc
        if (strpos($title, ' | ') !== false) {
            return ' | ';
        }
        if (strpos($title, ' / ') !== false) {
            return ' / ';
        }
        if (strpos($title, ' - ') !== false) {
            return ' - ';
        }
//        if (strpos($title, ' : ') !== false) {
//            return ' : ';
//        }

        return null;
    }

    /**
     * Remove SEO segments as containing same words as the website domain name.
     *
     * @param string[] $seoSegments
     *
     * @return string[]
     */
    private function deleteSegmentsContainingSitename(string $prettyDomainName, array $seoSegments): array
    {
        $siteName = TextUtil::stripPunctuation($this->extractSiteName($prettyDomainName));
        $siteName = str_replace(['.', '-', ' '], '', $siteName);
        $prettyDomainName = TextUtil::stripPunctuation($prettyDomainName);
        $prettyDomainName = str_replace(['.', '-', ' '], '', $prettyDomainName);

        return array_values(array_filter(
            $seoSegments,
            function ($segment) use ($prettyDomainName, $siteName) {
                $strippedSegment = mb_strtolower(TextUtil::stripPunctuation(TextUtil::stripAccents($segment)));
                $strippedSegment = str_replace(['.', '-', ' '], '', $strippedSegment);

                return !empty(trim($segment))
                    && false === strpos($strippedSegment, $prettyDomainName)
                    && false === strpos($strippedSegment, $siteName);
            }
        ));
    }

    /**
     * Get site name from pretty domain name.
     * Ex: "google.com" => "google"
     * Ex: "my-news.co.uk" => "my-news"
     */
    private function extractSiteName(string $prettyDomainName): string
    {
        // strip string after last dot
        $siteName = preg_replace('/\.[^.]*$/', '', $prettyDomainName);

        // strip string if only 2 chars after dot
        return preg_replace('/\.[^.]{2}$/', '', $siteName);
    }
}