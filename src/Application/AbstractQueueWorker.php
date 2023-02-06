<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\ServiceFactory;
use ErrorException;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Consume RabbitMQ queue and acknowledge each message.
 * Class AbstractWorker.
 */
abstract class AbstractQueueWorker
{
    public const QUEUE_NAME = 'AbstractQueueWorker';

    /**
     * Connect and obtain the messages from queue.
     *
     * @throws ErrorException
     */
    public function run()
    {
        $channel = ServiceFactory::queueChannel(static::QUEUE_NAME);

        echo " [*] Waiting for messages. To exit press CTRL+C\n";

        // qos : one message to a worker at a time (next one after acknowledge)
        $channel->basic_qos(null, 1, null);
        // callback to $this->msgProcess().
        // TODO check Rector refac
        $channel->basic_consume(static::QUEUE_NAME, '', false, false, false, false, function (\PhpAmqpLib\Message\AMQPMessage $msg) : void {
            $this->msgProcess($msg);
        });

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        //$connection->close();
    }

    /**
     * Process with one of the queue message.
     * Note: Callback needs a public function. See call_user_func().
     *
     * @param AMQPMessage $msg
     */
    abstract public function msgProcess(AMQPMessage $msg): void;

    /**
     * @param AMQPMessage $msg
     */
    protected function acknowledge(AMQPMessage $msg)
    {
        $msg->delivery_info['channel']
            ->basic_ack($msg->delivery_info['delivery_tag']);
    }
}
