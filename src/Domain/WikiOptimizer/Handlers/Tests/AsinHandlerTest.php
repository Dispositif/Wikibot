<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers\Tests;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OptiStatus;
use App\Domain\WikiOptimizer\Handlers\AsinHandler;
use PHPUnit\Framework\TestCase;

class AsinHandlerTest extends TestCase
{
    private function handle(string $wikitext): OuvrageTemplate
    {
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrateFromText($wikitext);
        (new AsinHandler($ouvrage, new OptiStatus()))->handle();

        return $ouvrage;
    }

    public function testEmptyAsinDoesNothing()
    {
        $ouvrage = $this->handle('{{Ouvrage |titre=bla}}');
        $this::assertNull($ouvrage->getParam('asin'));
        $this::assertSame('', $ouvrage->getParam('isbn')); // 'isbn' fait partie de MINIMUM_PARAMETERS
    }

    public function testValidIsbn10AsinMovedToEmptyIsbn()
    {
        // "0306406152" est un ISBN-10 valide (checksum correct)
        $ouvrage = $this->handle('{{Ouvrage |titre=bla |asin=0306406152}}');
        $this::assertNull($ouvrage->getParam('asin'));
        $this::assertSame('0306406152', $ouvrage->getParam('isbn'));
    }

    public function testValidIsbn10AsinMovedToIsbn2WhenIsbnOccupied()
    {
        $ouvrage = $this->handle('{{Ouvrage |titre=bla |isbn=2-7071-1234-5 |asin=0306406152}}');
        $this::assertNull($ouvrage->getParam('asin'));
        $this::assertSame('2-7071-1234-5', $ouvrage->getParam('isbn'));
        $this::assertSame('0306406152', $ouvrage->getParam('isbn2'));
    }

    public function testAsinDuplicateOfExistingIsbnIsJustRemoved()
    {
        $ouvrage = $this->handle('{{Ouvrage |titre=bla |isbn=0306406152 |asin=0306406152}}');
        $this::assertNull($ouvrage->getParam('asin'));
        $this::assertSame('0306406152', $ouvrage->getParam('isbn'));
        $this::assertNull($ouvrage->getParam('isbn2'));
    }

    public function testNonConvertibleAsinIsRemovedWithoutTrace()
    {
        // format ASIN produit Amazon typique, pas un ISBN
        $ouvrage = $this->handle('{{Ouvrage |titre=bla |asin=B0031YOOO2}}');
        $this::assertNull($ouvrage->getParam('asin'));
        $this::assertSame('', $ouvrage->getParam('isbn'));
    }

    public function testInvalidChecksumTenDigitAsinIsRemoved()
    {
        // 10 chiffres mais checksum ISBN-10 invalide
        $ouvrage = $this->handle('{{Ouvrage |titre=bla |asin=1234567890}}');
        $this::assertNull($ouvrage->getParam('asin'));
        $this::assertSame('', $ouvrage->getParam('isbn'));
    }

    public function testAllIsbnSlotsOccupiedFallsBackToRemoval()
    {
        $ouvrage = $this->handle(
            '{{Ouvrage |titre=bla |isbn=2-7071-1111-1 |isbn2=2-7071-2222-2 |isbn3=2-7071-3333-3 |asin=0306406152}}'
        );
        $this::assertNull($ouvrage->getParam('asin'));
        $this::assertSame('2-7071-1111-1', $ouvrage->getParam('isbn'));
        $this::assertSame('2-7071-2222-2', $ouvrage->getParam('isbn2'));
        $this::assertSame('2-7071-3333-3', $ouvrage->getParam('isbn3'));
    }
}
