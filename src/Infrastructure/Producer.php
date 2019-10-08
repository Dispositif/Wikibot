<?php
declare(strict_types=1);

namespace App\Infrastructure;

use PhpAmqpLib\Message\AMQPMessage;

include __DIR__.'/../Application/myBootstrap.php';

$channel = ServiceFactory::queueChannel('test');

$data = implode(' ', array_slice($argv, 1));
if (empty($data)) {
    $data = "Hello World 2...";
}
$msg = new AMQPMessage(
    $data, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
);

// set routing_key to queueName value when using the default '' exchanger
$channel->basic_publish($msg, '', 'test');

echo ' [x] Sent ', $data, "\n";

$channel->close();
//$connection->close();
