<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\TemplateParser;
use PHPUnit\Framework\TestCase;

class OuvrageOptimizeTest extends TestCase
{

    public function testGetOuvrage()
    {
        $raw = '{{Ouvrage|languX=français|prénom1=Ernest|nom1=Nègre|titre=Toponymie:France|tome=3|page=15-27|isbn=2600028846}}';

        $parse = TemplateParser::parseAllTemplateByName('ouvrage', $raw);
        $origin = $parse['ouvrage'][0]['model'];

        $optimized = (new OuvrageOptimize($origin))->doTasks()->getOuvrage();
        $this::assertSame(
            '{{Ouvrage|langue=fr|auteur1=Ernest Nègre|titre=Toponymie|sous-titre=France|tome=3|isbn=978-2-600-02884-4|isbn10=2600028846|passage=15-27}}',
            $optimized->serialize(true)
        );
    }
}
