<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\InfrastructurePorts\MessageInterface;
use Exception;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * @unused
 * Class MessageAdapter
 *
 * @package App\Infrastructure
 */
class MessageAdapter implements MessageInterface
{
    private array $tempStorage = [];

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
