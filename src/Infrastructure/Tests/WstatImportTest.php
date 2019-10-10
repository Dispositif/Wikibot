<?php

declare(strict_types=1);

namespace App\Infrastructure;


use PHPUnit\Framework\TestCase;

class WstatImportTest extends TestCase
{

    public function testGetUrl()
    {
        $wstat = new WstatImport(
            [
                'title' => 'Ouvrage',
                'query' => 'inclusions',
                'param' => 'isbn',
                'start' => 50000,
                'limit' => 500,
            ],
            10000
        );
        $this::assertEquals(
            'https://wstat.fr/template/index.php?title=Ouvrage&query=inclusions&param=isbn&start=50000&limit=500&format=json',
            $wstat->getUrl()
        );
    }

    // Intégration test. But data daily changes
    //    public function testImport()
    //    {
    //        $wstat = new WstatImport();
    //        $this::assertIsArray($wstat->getData());
    //    }

}
