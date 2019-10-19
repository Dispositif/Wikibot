<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TemplateParser;
use Exception;
use PHPUnit\Framework\TestCase;

class OuvrageOptimizeTest extends TestCase
{
    public function testGetOuvrage()
    {
        $raw
            = '{{Ouvrage|languX=français|prénom1=Ernest|nom1=Nègre|titre=Toponymie:France|tome=3|page=15-27|isbn=2600028846}}';

        $parse = TemplateParser::parseAllTemplateByName('ouvrage', $raw);
        $origin = $parse['ouvrage'][0]['model'];

        $optimized = (new OuvrageOptimize($origin))->doTasks()->getOuvrage();
        $this::assertSame(
            '{{Ouvrage|langue=fr|auteur1=Ernest Nègre|titre=Toponymie|sous-titre=France|tome=3|isbn=978-2-600-02884-4|isbn10=2600028846|passage=15-27}}',
            $optimized->serialize(true)
        );
    }

    /**
     * @dataProvider provideProcessTitle
     *
     * @param $data
     * @param $expected
     *
     * @throws Exception
     */
    public function testProcessTitle($data, $expected)
    {
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrate($data);

        $optimized = (new OuvrageOptimize($ouvrage))->doTasks()->getOuvrage();
        $this::assertSame(
            $expected,
            $optimized->serialize(true)
        );
    }

    public function provideProcessTitle()
    {
        return [
            [['title' => 'Toponymie'], '{{Ouvrage|titre=Toponymie}}'],
            [['title' => 'Toponymie. France'], '{{Ouvrage|titre=Toponymie|sous-titre=France}}'], // explode
            [['title' => 'Vive PHP 7.3 en short'], '{{Ouvrage|titre=Vive PHP 7.3 en short}}'], //inchangé (numbers)
            [['title' => 'Ils ont osé... Les maires de Saint-Camille'], '{{Ouvrage|titre=Ils ont osé... Les maires de Saint-Camille}}'],
            [['title' => 'Toponymie - France'], '{{Ouvrage|titre=Toponymie|sous-titre=France}}'], // explode (- spaced)
            [['title' => 'Toponymie / France'], '{{Ouvrage|titre=Toponymie|sous-titre=France}}'], // explode (/ spaced)
            [['title' => 'Toponymie Jean-Pierre France'], '{{Ouvrage|titre=Toponymie Jean-Pierre France}}'], // inchangé
            [['title' => 'Toponymie 1914-1918 super'], '{{Ouvrage|titre=Toponymie 1914-1918 super}}'], // inchangé
        ];
    }
}
