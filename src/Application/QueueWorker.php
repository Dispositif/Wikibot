<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use PhpAmqpLib\Message\AMQPMessage;

include __DIR__.'/../Application/myBootstrap.php';

/**
 * @notused
 *
 * Consume RabbitMQ queue and acknowledge each message.
 */
class QueueWorker extends AbstractQueueWorker
{
    public const QUEUE_NAME = 'rabbit';

    /**
     * process with one of the queue message.
     * Note: Callback needs a public function / see call_user_func().
     *
     * @param AMQPMessage $msg
     */
    public function msgProcess(AMQPMessage $msg): void
    {
        echo ' [x] Received ', $msg->body, "\n";
        sleep(4);
        echo " [x] Done\n";

        $this->acknowledge($msg);
    }
}

echo "Go...\n";
(new QueueWorker())->run();
