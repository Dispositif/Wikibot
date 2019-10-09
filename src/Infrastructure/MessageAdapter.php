<?php
declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\MessageInterface;
use PhpAmqpLib\Message\AMQPMessage;

class MessageAdapter implements MessageInterface
{
    private $tempStorage = [];

    // inject AMQPAdapter, logAdapter, etc ?
    public function __construct()
    {
    }

    /**
     * A temporary exchange system.
     *
     * @param string $queue
     * @param        $message
     */
    public function send(string $queue, $message): void
    {
        // keep message in memory
        if ($queue === 'test') {
            dump('queue:test', $message);
        }

        // insert message in text file
        if ($queue === 'ISBN invalide') {
            $corpus = new CorpusAdapter();
            $corpus->addNewElementToCorpus('queue_ISBN invalide', $message);

            return;
        }

        // send message to AMQP server
        if ($queue === 'rabbit') {
            $this->amqpMsg($queue, $message);

            return;
        }

        //default
        $this->tempStorage[$queue][] = $message;
    }

    /**
     * AMQP server interface (with acknowledge and durable messages).
     *
     * @param string $queueName
     * @param        $data
     */
    private function amqpMsg(string $queueName, $data): void
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
