<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

use PHPUnit\Framework\TestCase;

class ArrayProcessTraitTest extends TestCase
{
    use ArrayProcessTrait;

    public function testCompleteFantasyOrder()
    {
        $fantasyOrder = ['fantaisie', 'auteur', 'titre', 'année'];
        $cleanOrder = ['bla', 'first', 'auteur', 'titre', 'sous-titre', 'année', 'fantaisie', 'fantaisie2'];

        $this::assertSame(
            ['bla', 'first', 'fantaisie', 'fantaisie2', 'auteur', 'titre', 'sous-titre', 'année'],
            $this->completeFantasyOrder($fantasyOrder, $cleanOrder)
        );
    }

}
