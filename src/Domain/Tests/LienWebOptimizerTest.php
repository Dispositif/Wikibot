<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\WikiOptimizer\OptimizerFactory;
use App\Domain\WikiTemplateFactory;
use Exception;
use PHPUnit\Framework\TestCase;

class LienWebOptimizerTest extends TestCase
{
    /**
     * @dataProvider provideSomeParam
     *
     * @param $data
     * @param $expected
     *
     * @throws Exception
     */
    public function testSomeParam($data, $expected)
    {
        $lienWeb = WikiTemplateFactory::create('lien web');
        $lienWeb->hydrate($data);

        $optimized = (OptimizerFactory::fromTemplate($lienWeb))->doTasks();
        $optimizedTemplate = $optimized->getOptiTemplate();
        $this::assertSame(
            $expected,
            $optimizedTemplate->serialize(true)
        );
    }

    public static function provideSomeParam(): array
    {
        return [
            [
                ['langue' => 'fr', 'titre' => 'bla', 'url' => 'http://test.com'],
                "{{Lien web|langue=fr|titre=Bla|url=http://test.com|consulté le=}}",
            ],
            [
                // titre "Bla - PubMed
                ['titre' => 'Mali - Vidéo Dailymotion', 'url' => 'http://test.com', 'site' => 'Dailymotion'],
                "{{Lien web|titre=Mali|url=http://test.com|site=Dailymotion|consulté le=}}",
            ],
            [
                // doublon site / périodique
                [
                    'titre' => 'bla',
                    'url' => 'http://test.com',
                    'site' => "[[L'Équipe]]",
                    'périodique' => "[[L'Équipe]]",
                ],
                "{{Lien web|titre=Bla|url=http://test.com|site=[[L'Équipe]]|consulté le=}}",
            ],
            [
                // auteur1 = Rédaction
                ['titre' => 'bla', 'url' => 'http://test.com', 'auteur1' => 'Rédaction'],
                "{{Lien web|titre=Bla|url=http://test.com|consulté le=}}",
            ],
            [
                // doublon site / périodique
                ['titre' => 'bla', 'url' => 'http://test.com', 'auteur1' => 'Le Monde', 'site' => '[[Le Monde]]'],
                "{{Lien web|titre=Bla|url=http://test.com|site=[[Le Monde]]|consulté le=}}",
            ],
            [
                // quasi doublon site / périodique
                ['titre' => 'Bla', 'site' => 'France24.com', 'périodique' => 'France 24'],
                '{{Lien web|titre=Bla|url=|site=France24.com|consulté le=}}',
            ],
            [
                // doublon site / éditeur
                ['titre' => 'bla', 'url' => 'http://test.com', 'site' => 'Le Monde', 'éditeur' => 'Le Monde'],
                "{{Lien web|titre=Bla|url=http://test.com|site=Le Monde|consulté le=}}",
            ],
            [
                // quasi doublon site / éditeur
                ['titre' => 'Bla', 'site' => 'lemonde.fr', 'éditeur' => 'Le Monde'],
                '{{Lien web|titre=Bla|url=|site=lemonde.fr|consulté le=}}',
            ],
        ];
    }
}
