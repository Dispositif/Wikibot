<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Domain\ExternLink\PublisherLogicTrait;
use App\Domain\Models\Wiki\LienWebTemplate;
use PHPUnit\Framework\TestCase;

/**
 * config_presse.yaml lookup : registrable domain key + the subdomain keys that used to be
 * dead config ("gallica.bnf.fr" and friends).
 */
class PublisherLogicTraitTest extends TestCase
{
    public function testRegistrableDomainKeyStillApplies(): void
    {
        $subject = $this->subject(['lemonde.fr' => ['site' => '[[Le Monde]]']], 'lemonde.fr', 'www.lemonde.fr');

        self::assertSame(['site' => '[[Le Monde]]'], $subject->domainConfig());
    }

    public function testSubdomainKeyApplies(): void
    {
        $subject = $this->subject(['gallica.bnf.fr' => ['site' => '[[Gallica]]']], 'bnf.fr', 'gallica.bnf.fr');

        self::assertSame(['site' => '[[Gallica]]'], $subject->domainConfig());
    }

    public function testSubdomainKeyDoesNotLeakToSiblingSubdomain(): void
    {
        $subject = $this->subject(['gallica.bnf.fr' => ['site' => '[[Gallica]]']], 'bnf.fr', 'data.bnf.fr');

        self::assertSame([], $subject->domainConfig());
    }

    public function testSubdomainKeyOverridesRegistrableDomainKeyParamByParam(): void
    {
        $subject = $this->subject(
            [
                'bnf.fr' => ['site' => '[[Bibliothèque nationale de France|BnF]]', 'template' => 'lien web'],
                'gallica.bnf.fr' => ['site' => '[[Gallica]]'],
            ],
            'bnf.fr',
            'gallica.bnf.fr'
        );

        self::assertSame(
            ['site' => '[[Gallica]]', 'template' => 'lien web'],
            $subject->domainConfig()
        );
    }

    public function testDeactivatedShorthandString(): void
    {
        $subject = $this->subject(['example.com' => 'deactivated'], 'example.com', 'www.example.com');

        self::assertSame(['deactivated' => true], $subject->domainConfig());
    }

    public function testSiteReplacedOnLienWebFromSubdomainKey(): void
    {
        $subject = $this->subject(['gallica.bnf.fr' => ['site' => '[[Gallica]]']], 'bnf.fr', 'gallica.bnf.fr');

        $mapData = $subject->siteForLienWeb(['site' => '[[Pariser Zeitung]]']);

        self::assertSame('[[Gallica]]', $mapData['site']);
    }

    /**
     * Key order also drives the config_skip_domain.txt match (ExternRefTransformer::isSiteBlackListed),
     * where entries like "pop.culture.gouv.fr" used to never fire.
     */
    public function testDomainKeysGoFromLeastToMostSpecific(): void
    {
        $subject = $this->subject([], 'culture.gouv.fr', 'pop.culture.gouv.fr');

        self::assertSame(['culture.gouv.fr', 'pop.culture.gouv.fr'], $subject->domainKeys());
    }

    public function testDomainKeysWithoutSubdomain(): void
    {
        $subject = $this->subject([], 'lemonde.fr', 'lemonde.fr');

        self::assertSame(['lemonde.fr'], $subject->domainKeys());
    }

    private function subject(array $config, string $registrableDomain, ?string $hostname): object
    {
        return new class($config, $registrableDomain, $hostname) {
            use PublisherLogicTrait;

            protected $config;
            protected ?string $registrableDomain;
            protected ?string $hostname;
            protected array $publisherData = [];

            public function __construct(array $config, string $registrableDomain, ?string $hostname)
            {
                $this->config = $config;
                $this->registrableDomain = $registrableDomain;
                $this->hostname = $hostname;
            }

            public function domainConfig(): array
            {
                return $this->getDomainConfig();
            }

            public function domainKeys(): array
            {
                return $this->getConfigDomainKeys();
            }

            public function siteForLienWeb(array $mapData): array
            {
                return $this->replaceSiteForLienWeb(new LienWebTemplate(), $mapData);
            }
        };
    }
}
