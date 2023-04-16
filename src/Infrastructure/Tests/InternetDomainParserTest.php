<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Infrastructure\InternetDomainParser;
use PHPUnit\Framework\TestCase;

class InternetDomainParserTest extends TestCase
{
    /**
     * @var InternetDomainParser
     */
    private $domainParser;

    public function setUp(): void
    {
        $this->domainParser = new InternetDomainParser();
    }

    /**
     * @group skipci
     * @dataProvider provideUrls
     */
    public function testResolve($httpURL, $expected): void
    {
        $this::assertSame(
            $expected,
            $this->domainParser->getRegistrableDomainFromURL($httpURL)
        );
    }

    public function provideUrls(): array
    {
        return [
            ['https://www.google.fr', 'google.fr'],
            ['http://fu.bar.co.uk', 'bar.co.uk'],
            ['http://fu.free.fr', 'free.fr'],
            ['https://bla.blogspot.com', 'bla.blogspot.com'],
        ];
    }
}
