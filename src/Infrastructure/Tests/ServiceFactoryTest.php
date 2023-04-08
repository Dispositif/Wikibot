<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use PHPUnit\Framework\TestCase;

class ServiceFactoryTest extends TestCase
{
    public function testCreateQueueChannel()
    {
        $this::markTestSkipped('integration webservice deactivated');
//        $channel = ServiceFactory::queueChannel('foo');
//        $this::assertInstanceOf(AMQPChannel::class, $channel);
    }

    public function testCloseAMQPconnection()
    {
        $this::markTestIncomplete();
    }

    public function testWikiApi()
    {
        $this::markTestSkipped('integration webservice deactivated');
//        $wiki = ServiceFactory::wikiApi();
//        $this::assertInstanceOf(MediawikiFactory::CLASS, $wiki);
    }
}
