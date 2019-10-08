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
        if ($queue === 'test') {
            dump('queue=test', $message);
        }
        if ($queue === 'rabbit') {
            $this->amqpMsg($queue, $message);

            return;
        }
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
