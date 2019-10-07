<?php

namespace App\Infrastructure;

use PhpAmqpLib\Message\AMQPMessage;

include __DIR__.'/../Application/myBootstrap.php';

$channel = ServiceFactory::queueChannel('test');

if (empty($data)) {
    $data = "Hello World...";
}
$msg = new AMQPMessage(
    $data, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
);

$channel->basic_publish($msg, '', 'task_queue');

echo ' [x] Sent ', $data, "\n";

$channel->close();
//$connection->close();
