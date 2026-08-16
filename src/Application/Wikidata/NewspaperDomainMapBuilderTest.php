<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Wikidata;

use PHPUnit\Framework\TestCase;

class NewspaperDomainMapBuilderTest extends TestCase
{
    public function testKeepsDomainClaimedByOneNewspaper(): void
    {
        $map = (new NewspaperDomainMapBuilder())->build(
            [
                ['fr' => 'Le Monde', 'domain' => 'lemonde.fr', 'frwiki' => 'Le_Monde'],
                // same paper twice (several WD rows per item) : still unambiguous
                ['fr' => 'Le Monde', 'domain' => 'lemonde.fr', 'frwiki' => 'Le_Monde'],
            ]
        );

        self::assertSame(['lemonde.fr' => ['fr' => 'Le Monde', 'frwiki' => 'Le Monde']], $map);
    }

    public function testDecodesFrwikiTitle(): void
    {
        $map = (new NewspaperDomainMapBuilder())->build(
            [['fr' => 'Le Canard enchaîné', 'domain' => 'lecanardenchaine.fr', 'frwiki' => 'Le_Canard_encha%C3%AEn%C3%A9']]
        );

        self::assertSame('Le Canard enchaîné', $map['lecanardenchaine.fr']['frwiki']);
    }

    /**
     * The 2026-08-16 bug : 153 newspapers claimed 'bnf.fr' (P856 pointing at their Gallica
     * holdings), so every gallica.bnf.fr citation was labelled with the arbitrary winner.
     */
    public function testDropsDomainClaimedBySeveralNewspapers(): void
    {
        $builder = new NewspaperDomainMapBuilder();
        $map = $builder->build(
            [
                ['fr' => 'Pariser Zeitung', 'domain' => 'bnf.fr', 'frwiki' => 'Pariser_Zeitung'],
                ['fr' => 'Le Petit Parisien', 'domain' => 'bnf.fr', 'frwiki' => 'Le_Petit_Parisien'],
            ]
        );

        self::assertSame([], $map);
        self::assertArrayHasKey('bnf.fr', $builder->getDroppedDomains());
    }

    public function testDropsArchiveAggregatorEvenWithOnlyOneClaimant(): void
    {
        $builder = new NewspaperDomainMapBuilder();
        $map = $builder->build([['fr' => 'Ilkka', 'domain' => 'archive.org', 'frwiki' => 'Ilkka_(journal)']]);

        self::assertSame([], $map);
        self::assertSame(
            NewspaperDomainMapBuilder::DROP_REASON_AGGREGATOR,
            $builder->getDroppedDomains()['archive.org']
        );
    }

    public function testDropsGenericHostingPlatform(): void
    {
        $builder = new NewspaperDomainMapBuilder();
        $map = $builder->build(
            [['fr' => "Les Cahiers de l'Orient", 'domain' => 'wordpress.com', 'frwiki' => "Les_Cahiers_de_l'Orient"]]
        );

        self::assertSame([], $map);
        self::assertSame(
            NewspaperDomainMapBuilder::DROP_REASON_HOSTING,
            $builder->getDroppedDomains()['wordpress.com']
        );
    }

    public function testPrefersSiblingRowWithFrenchLabelOverBareWikidataId(): void
    {
        $map = (new NewspaperDomainMapBuilder())->build(
            [
                ['fr' => 'Q11148', 'domain' => 'example.com', 'frwiki' => 'Journal_exemple'],
                ['fr' => 'Journal exemple', 'domain' => 'example.com', 'frwiki' => 'Journal_exemple'],
            ]
        );

        self::assertSame('Journal exemple', $map['example.com']['fr']);
    }

    public function testDropsDomainWithoutAnyFrenchLabel(): void
    {
        $builder = new NewspaperDomainMapBuilder();
        $map = $builder->build([['fr' => 'Q11148', 'domain' => 'example.com', 'frwiki' => 'Journal_exemple']]);

        self::assertSame([], $map);
        self::assertSame(
            NewspaperDomainMapBuilder::DROP_REASON_NO_LABEL,
            $builder->getDroppedDomains()['example.com']
        );
    }
}
