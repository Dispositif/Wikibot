<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Domain\Publisher\GallicaUtil;
use PHPUnit\Framework\TestCase;

class GallicaUtilTest extends TestCase
{
    public function testIsGallicaUrl()
    {
        $url = 'https://gallica.bnf.fr/ark:/12148/bpt6k5698362j/f440.item.zoom';
        $this::assertSame(true, GallicaUtil::isGallicaURL($url));
        $this::assertSame(false, GallicaUtil::isGallicaURL('http://google.fr'));
    }

}
