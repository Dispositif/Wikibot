<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\ExternLink\ExternRefTransformer;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
use App\Domain\Publisher\ExternMapper;
use App\Infrastructure\Monitor\NullLogger;
use App\Infrastructure\NullExternLinkCheckRepository;
use PHPUnit\Framework\TestCase;

/**
 * Wikiwix cache URL found as-is in an article (raw-extern-ref/extern-ref) : HTTPS
 * canonicalization + |site= naming the archived site, not only the archiver.
 */
class ExternRefTransformerWikiwixTest extends TestCase
{
    private const IGN_WIKIWIX_URL = 'http://wikiwix.com/cache/?url=http://www.ign.fr/affiche_rubrique.asp?rbr_id=1087%26CommuneId=11364';
    private const IGN_SECURE_URL = 'https://archive.wikiwix.com/cache/index2.php?url=http://www.ign.fr/affiche_rubrique.asp?rbr_id=1087%26CommuneId=11364';

    public function testHttpWikiwixUrlIsUpgradedToHttpsCanonicalForm(): void
    {
        $subject = $this->subject();

        self::assertSame(self::IGN_SECURE_URL, $subject->prepareUrl(self::IGN_WIKIWIX_URL));
    }

    public function testNonWikiwixUrlUntouched(): void
    {
        $subject = $this->subject();

        self::assertSame('http://www.lemonde.fr/x', $subject->prepareUrl('http://www.lemonde.fr/x'));
    }

    public function testSiteNamesTheArchivedDomainViaTheArchiver(): void
    {
        $subject = $this->subject();
        $subject->prepareUrl(self::IGN_WIKIWIX_URL);

        // '[[Wikiwix]]' is what config_presse.yaml maps the wikiwix.com domain to
        self::assertSame(['site' => 'ign.fr via [[Wikiwix]]'], $subject->correctSite(['site' => '[[Wikiwix]]']));
    }

    public function testArchivedSiteIsWikifiedFromConfigPresse(): void
    {
        $subject = $this->subject(['ign.fr' => ['site' => '[[Institut national de l\'information géographique et forestière|IGN]]']]);
        $subject->prepareUrl(self::IGN_WIKIWIX_URL);

        self::assertSame(
            ['site' => '[[Institut national de l\'information géographique et forestière|IGN]] via [[Wikiwix]]'],
            $subject->correctSite(['site' => '[[Wikiwix]]'])
        );
    }

    public function testArchivedSiteIsWikifiedFromWikidataNewspapers(): void
    {
        $subject = $this->subject([], ['ign.fr' => ['fr' => 'IGN', 'frwiki' => 'Institut géographique national']]);
        $subject->prepareUrl(self::IGN_WIKIWIX_URL);

        self::assertSame(
            ['site' => '[[Institut géographique national|IGN]] via [[Wikiwix]]'],
            $subject->correctSite(['site' => '[[Wikiwix]]'])
        );
    }

    /**
     * DeadLinkTransformer passes the archived domain itself when it recursively crawls an
     * archive URL it just found — that stays authoritative.
     */
    public function testOptionFromDeadLinkTransformerWins(): void
    {
        $subject = $this->subject();
        $subject->prepareUrl(self::IGN_WIKIWIX_URL, ['originalRegistrableDomain' => 'insee.fr']);

        self::assertSame(['site' => 'insee.fr via [[Wikiwix]]'], $subject->correctSite(['site' => '[[Wikiwix]]']));
    }

    public function testAlreadyViaArchiverSiteNotPrefixedTwice(): void
    {
        $subject = $this->subject();
        $subject->prepareUrl(self::IGN_WIKIWIX_URL);

        self::assertSame(['site' => 'ign.fr via [[Wikiwix]]'], $subject->correctSite(['site' => 'ign.fr via [[Wikiwix]]']));
    }

    public function testEmptySiteLeftAlone(): void
    {
        $subject = $this->subject();
        $subject->prepareUrl(self::IGN_WIKIWIX_URL);

        self::assertSame([], $subject->correctSite([]));
    }

    private function subject(array $configPresse = [], array $newspapers = []): ExternRefTransformer
    {
        $domainParser = $this->createMock(InternetDomainParserInterface::class);
        $domainParser->method('getRegistrableDomainFromURL')->willReturnCallback(
            // enough for the hosts used here : "www.ign.fr" => "ign.fr"
            static fn(string $url): string => implode('.', array_slice(explode('.', (string)parse_url($url, PHP_URL_HOST)), -2))
        );

        return new class(
            $this->createMock(ExternMapper::class),
            $this->createMock(HttpClientInterface::class),
            $domainParser,
            new NullLogger(),
            [],
            new NullExternLinkCheckRepository(),
            null,
            false,
            $configPresse,
            $newspapers
        ) extends ExternRefTransformer {
            public function __construct(
                ExternMapper $mapper,
                HttpClientInterface $httpClient,
                InternetDomainParserInterface $domainParser,
                NullLogger $log,
                array $deadlinkArchivers,
                NullExternLinkCheckRepository $linkCheckRepository,
                ?HttpClientInterface $directRetryClient,
                bool $respectRobotsTxt,
                private readonly array $configPresse,
                private readonly array $newspapers,
            ) {
                parent::__construct(
                    $mapper,
                    $httpClient,
                    $domainParser,
                    $log,
                    $deadlinkArchivers,
                    $linkCheckRepository,
                    $directRetryClient,
                    $respectRobotsTxt
                );
            }

            /** the real one reads gitignored config/data files */
            protected function importConfigAndData(): void
            {
                $this->config = $this->configPresse;
                $this->skip_domain = [];
                $this->publisherData = ['newspaper' => $this->newspapers, 'scientific domain' => [], 'scientific wiki' => []];
            }

            public function prepareUrl(string $url, array $options = []): string
            {
                $this->options = $options;

                return $this->prepareWikiwixUrl($url);
            }

            public function correctSite(array $mapData): array
            {
                return $this->correctSiteViaWebarchiver($mapData);
            }
        };
    }
}
