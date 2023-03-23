<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\ExternPage;
use PHPUnit\Framework\TestCase;

class ExternPageTest extends TestCase
{
    /**
     * @group skipci
     * @dataProvider provideTestResult
     *
     * @param string|null $url
     * @param string|null $expected
     *
     * @throws \Exception
     */
    public function testGetPrettyDomainName(string $url, ?string $expected)
    {
        $page = new ExternPage($url, 'bla');
        $this::assertSame($expected, $page->getPrettyDomainName());
    }

    public function provideTestResult(): array
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
}
