<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\ExternLink\ExternPage;
use App\Infrastructure\InternetDomainParser;
use App\Infrastructure\TagParser;
use PHPUnit\Framework\TestCase;

class ExternPageTest extends TestCase
{
    /**
     * Skip CI because of registar domain dataset needed (or http request?).
     * @group skipci
     * @dataProvider provideTestResult
     */
    public function testGetPrettyDomainNameWithDomainParser(string $url, ?string $expected): void
    {
        $page = new ExternPage($url, 'bla', new TagParser(), new InternetDomainParser());
        $this::assertSame($expected, $page->getPrettyDomainName());
    }

    public static function provideTestResult(): array
    {
        return [
            ['http://test.com', 'test.com'],
            ['http://bla.test.com', 'test.com'],
            ['https://www.google.fr/bla', 'google.fr'],
            ['http://test.co.uk', 'test.co.uk'], // (national commercial subdomain)
            ['https://www6.nhk.or.jp/anime/topics/detail.html?i=10005', 'nhk.or.jp'],
            ['http://site.google.com', 'site.google.com'], // /* custom parsing */
            ['http://bla.free.fr', 'bla.free.fr'], /* custom parsing */
            ['http://bla-bla.gouv.fr', 'bla-bla.gouv.fr'], /* custom parsing */
            ['http://bla-bla.gouv', 'bla-bla.gouv'], /* custom parsing */
        ];
    }

    /**
     * @group skipci
     * @dataProvider provideTestResult2
     */
    public function testGetPrettyDomainNameNaiveAlgo(string $url, ?string $expected): void
    {
        $page = new ExternPage($url, 'bla', new TagParser(), null);
        $this::assertSame($expected, $page->getPrettyDomainName());
    }

    public static function provideTestResult2(): array
    {
        return [
            ['http://test.com', 'test.com'],
            ['https://www.google.fr/bla', 'google.fr'],
            ['http://test.co.uk', 'test.co.uk'], // (national commercial subdomain)
            ['http://site.google.com', 'site.google.com'], // /* custom parsing */
            ['http://bla.free.fr', 'bla.free.fr'], /* custom parsing */
            ['http://bla-bla.gouv.fr', 'bla-bla.gouv.fr'], /* custom parsing */
            ['http://bla-bla.gouv', 'bla-bla.gouv'], /* custom parsing */
        ];
    }

    public function testExceptionNotAnURL(): void
    {
        $this->expectExceptionMessage('string is not an URL blanoturl');
        $page = new ExternPage('blanoturl', 'bla');
        $page->getData();
    }

    public function testgetDataMetatags(): void
    {
        $page = new ExternPage(
            'http://www.bla-bla.free.fr',
            '<html lang="en-us">bla
            <meta name="description" content="bla bla bla">
            <meta name="robots" content="noindex,noarchive">
            <title>my title</title>
            <h1>my title2</h1>  '
        );
        $meta = $page->getData()['meta'];
        $this::assertSame('bla bla bla', $meta['description']);
        $this::assertSame('en-us', $meta['html-lang']);
        $this::assertSame('my title', $meta['html-title']);
        $this::assertSame('my title2', $meta['html-h1']);
        $this::assertSame('noindex,noarchive', $meta['robots']);
        $this::assertSame('bla-bla.free.fr', $meta['prettyDomainName']); // free.fr = custom rule
    }
}
