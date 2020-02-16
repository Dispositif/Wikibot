<?php

declare(strict_types=1);

namespace App\Application\Examples;

use App\Infrastructure\DbAdapter;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../myBootstrap.php';

/**
 * Class CompleteProcessTest
 *
 * @group application
 */
class CompleteProcessTest extends TestCase
{
    protected function setUp(): void
    {
        // todo check ENV
        //$this->markTestSkipped('all tests in this file are inactive for this server configuration!');
    }

    public function testRun()
    {
        $DbAdapterMock = $this->createMock(DbAdapter::class);
        $DbAdapterMock->method('getNewRaw')->willReturn(
            '{{Ouvrage |auteur=Pierre André|titre=Bla|}}'
        );
        $DbAdapterMock->method('sendCompletedData')->willReturn(true);

        $complete = new CompleteProcess($DbAdapterMock, false);

        $this::assertSame(
            true,
            $complete->run(1)
        );
    }

}
