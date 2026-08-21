<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\Utils;

use App\Domain\Utils\SimHash;
use PHPUnit\Framework\TestCase;

class SimHashTest extends TestCase
{
    public function testIdenticalTextsHaveZeroDistance(): void
    {
        $text = 'Le vif du sujet est traité dans ce long paragraphe consacré à la question.';
        $a = SimHash::fingerprint($text);
        $b = SimHash::fingerprint($text);

        $this->assertSame(0, SimHash::hammingDistance($a, $b));
    }

    public function testNearDuplicateTextsHaveSmallDistance(): void
    {
        $live = 'Le vif du sujet est traité dans ce long paragraphe consacré à la question. '
            . 'Un deuxième paragraphe développe encore le propos avec davantage de détails.';
        // same content, cookie banner + ad injected, as an archive snapshot typically would
        $archived = 'Accepter les cookies | ' . $live . ' | Publicité : achetez ce produit maintenant';

        $distance = SimHash::hammingDistance(SimHash::fingerprint($live), SimHash::fingerprint($archived));

        $this->assertLessThan(10, $distance, 'near-duplicate texts should stay close in Hamming distance');
    }

    public function testUnrelatedTextsHaveLargeDistance(): void
    {
        $a = SimHash::fingerprint('Le vif du sujet est traité dans ce long paragraphe consacré à la question.');
        $b = SimHash::fingerprint('Recette de cuisine : faire bouillir des pâtes puis ajouter une sauce tomate maison.');

        $distance = SimHash::hammingDistance($a, $b);

        $this->assertGreaterThan(20, $distance, 'unrelated texts should be far apart in Hamming distance');
    }

    public function testEmptyTextDoesNotThrow(): void
    {
        $this->assertSame(0, SimHash::fingerprint(''));
    }
}
