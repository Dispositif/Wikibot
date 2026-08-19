<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Domain\ExternLink\WikiwixUrl;
use PHPUnit\Framework\TestCase;

class WikiwixUrlTest extends TestCase
{
    /**
     * @dataProvider provideWikiwixUrls
     */
    public function testWikiwixUrlIsRecognized(string $url): void
    {
        self::assertTrue(WikiwixUrl::isWikiwixUrl($url));
    }

    public static function provideWikiwixUrls(): array
    {
        return [
            ['http://wikiwix.com/cache/?url=http://www.ign.fr/x'],
            ['http://www.wikiwix.com/cache/?url=http://www.ign.fr/x'],
            ['https://archive.wikiwix.com/cache/index2.php?url=http://www.ign.fr/x'],
            ['https://archive.wikiwix.com/cache/19981130000000/http://casamaures.org/x'],
            ['https://archive.wikiwix.com/'],
        ];
    }

    /**
     * @dataProvider provideNonWikiwixUrls
     */
    public function testOtherUrlIsNotRecognized(string $url): void
    {
        self::assertFalse(WikiwixUrl::isWikiwixUrl($url));
    }

    public static function provideNonWikiwixUrls(): array
    {
        return [
            ['http://www.lemonde.fr/article'],
            ['https://web.archive.org/web/20120101/http://wikiwix.com/'],
            // a domain merely ending like it
            ['https://notwikiwix.com/cache/?url=http://x.fr/'],
        ];
    }

    /**
     * @dataProvider provideOriginalHosts
     */
    public function testExtractOriginalHost(string $url, ?string $expected): void
    {
        self::assertSame($expected, WikiwixUrl::extractOriginalHost($url));
    }

    public static function provideOriginalHosts(): array
    {
        return [
            'raw original URL, %26-escaped query' => [
                'http://wikiwix.com/cache/?url=http://www.ign.fr/affiche_rubrique.asp?rbr_id=1087%26CommuneId=11364',
                'www.ign.fr',
            ],
            'percent-encoded original URL' => [
                'http://archive.wikiwix.com/cache/?url=http%3A%2F%2Fwww.sptimes.ru%2Fstory%2F25093',
                'www.sptimes.ru',
            ],
            'wikiwix &title= param after the original URL' => [
                'http://archive.wikiwix.com/cache/?url=http://www.cbr.com/news.asp?guid=9D3A&title=European%20parliament',
                'www.cbr.com',
            ],
            'index2.php flavour' => [
                'https://archive.wikiwix.com/cache/index2.php?url=http://x.fr/a',
                'x.fr',
            ],
            'api flavour, url= not the first param' => [
                'https://archive.wikiwix.com/cache/index2.php?apiresponse=1&url=http://y.fr/b',
                'y.fr',
            ],
            'longform (what WikiwixAdapter returns)' => [
                'https://archive.wikiwix.com/cache/19981130000000/http://casamaures.org/keskispas.php?lng=fr',
                'casamaures.org',
            ],
            'no original URL to extract' => ['https://archive.wikiwix.com/', null],
        ];
    }

    /**
     * @dataProvider provideSecureUrls
     */
    public function testToSecureUrl(string $url, string $expected): void
    {
        self::assertSame($expected, WikiwixUrl::toSecureUrl($url));
    }

    public static function provideSecureUrls(): array
    {
        return [
            'HTTP query form => HTTPS canonical form, original URL untouched' => [
                'http://wikiwix.com/cache/?url=http://www.ign.fr/affiche_rubrique.asp?rbr_id=1087%26CommuneId=11364',
                'https://archive.wikiwix.com/cache/index2.php?url=http://www.ign.fr/affiche_rubrique.asp?rbr_id=1087%26CommuneId=11364',
            ],
            'percent-encoded original URL kept as-is' => [
                'http://archive.wikiwix.com/cache/?url=http%3A%2F%2Fwww.sptimes.ru%2Fstory%2F25093',
                'https://archive.wikiwix.com/cache/index2.php?url=http%3A%2F%2Fwww.sptimes.ru%2Fstory%2F25093',
            ],
            'longform : scheme upgraded, shape kept (index2.php would lose the snapshot date)' => [
                'http://archive.wikiwix.com/cache/19981130000000/http://casamaures.org/x',
                'https://archive.wikiwix.com/cache/19981130000000/http://casamaures.org/x',
            ],
            'already canonical' => [
                'https://archive.wikiwix.com/cache/index2.php?url=http://x.fr/a',
                'https://archive.wikiwix.com/cache/index2.php?url=http://x.fr/a',
            ],
            'unparsable wikiwix URL left untouched' => [
                'http://archive.wikiwix.com/service/annotationCollector/updated.log',
                'http://archive.wikiwix.com/service/annotationCollector/updated.log',
            ],
        ];
    }
}
