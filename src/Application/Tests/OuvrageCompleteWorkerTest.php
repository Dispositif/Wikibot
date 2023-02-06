<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Application\Tests;

use App\Application\OuvrageCompleteWorker;
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
        $this->markTestSkipped('Skip : Bug on Phpunit before upgrade php 8');
    }

    public function testRun()
    {
        $DbAdapterMock = $this->createMock(DbAdapter::class);
        $DbAdapterMock->method('getNewRaw')->willReturn(
            ['page' => 'bla', 'raw' => '{{Ouvrage |auteur=Pierre André|titre=Bla|}}']
        );
        $DbAdapterMock->method('sendCompletedData')->willReturn(true);

        $complete = new OuvrageCompleteWorker($DbAdapterMock);

        $this::assertSame(
            true,
            $complete->run(1)
        );
    }

}
