<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

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
