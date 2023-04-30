<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Tests;

use App\Application\InfrastructurePorts\MemoryInterface;
use App\Application\OuvrageComplete\OuvrageCompleteWorker;
use App\Domain\InfrastructurePorts\WikidataAdapterInterface;
use App\Domain\Models\PageOuvrageDTO;
use App\Infrastructure\DbAdapter;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../myBootstrap.php';

/**
 * Class CompleteProcessTest
 *
 * @group application
 */
class OuvrageCompleteWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        // todo check ENV
        //$this->markTestSkipped('Skip : Bug on Phpunit before upgrade php 8');
    }

    public function testRun()
    {
        $DbAdapterMock = $this->createMock(DbAdapter::class);
        $pageOuvrageDto = new PageOuvrageDTO([
            'page' => 'bla',
            'raw' => '{{Ouvrage |auteur=Pierre André|titre=Bla|}}'
        ]);
        $DbAdapterMock->method('getNewRaw')->willReturn($pageOuvrageDto);
        $DbAdapterMock->method('sendCompletedData')->willReturn(true);
        $wikidataAdapterMock = $this->createMock(WikidataAdapterInterface::class);

        $memoryMock = $this->createMock(MemoryInterface::class);
        $complete = new OuvrageCompleteWorker($DbAdapterMock, $wikidataAdapterMock, $memoryMock);

        $this::assertSame(
            true,
            $complete->run(1)
        );
    }
}
