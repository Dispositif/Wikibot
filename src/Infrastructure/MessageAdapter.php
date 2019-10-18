<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 (c) Philippe M. <dispositif@gmail.com>
 * For the full copyright and license information, please view the LICENSE file
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\MessageInterface;
use Exception;
use PhpAmqpLib\Message\AMQPMessage;

class MessageAdapter implements MessageInterface
{
    private $tempStorage = [];

    // todo: dependency inject AMQPAdapter, logAdapter, etc ?
    public function __construct()
    {
    }

    /**
     * A temporary exchange system.
     *
     * @param string $queue
     * @param        $message
     *
     * @throws Exception
     */
    public function send(string $queue, $message): void
    {
        // keep message in memory
        if ('test' === $queue) {
            dump('queue:test', $message);
        }

        // insert message in text file
        if ('ISBN invalide' === $queue) {
            $corpus = new CorpusAdapter();
            $corpus->addNewElementToCorpus('queue_ISBN invalide', $message);

            return;
        }

        // send message to AMQP server
        if ('rabbit' === $queue) {
            $this->amqpMsg($queue, $message);

            return;
        }

        //default
        $this->tempStorage[$queue][] = $message;
    }

    /**
     * todo : DI
     * AMQP server interface (with acknowledge and durable messages).
     *
     * @param string $queueName
     * @param        $data
     */
    public function amqpMsg(string $queueName, string $data): void
    {
        $channel = ServiceFactory::queueChannel($queueName);
        $msg = new AMQPMessage(
            $data, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );
        // set routing_key to queueName value when using the default '' exchange
        $channel->basic_publish($msg, '', $queueName);

        $channel->close();
    }
}
