<?php
declare(strict_types=1);

namespace App\Infrastructure;

/**
 * Consume RabbitMQ queue and acknowledge each message.
 */
include __DIR__.'/../Application/myBootstrap.php';

$channel = ServiceFactory::queueChannel('test');

echo " [*] Waiting for messages. To exit press CTRL+C\n";

$callback = function ($msg) {
    echo ' [x] Received ', $msg->body, "\n";
    sleep(2);
//    sleep(substr_count($msg->body, '.'));
    echo " [x] Done\n";
    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
};

// qos : one message to a worker at a time (next one after acknowledge)
$channel->basic_qos(null, 1, null);

$channel->basic_consume('test', '', false, false, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
//$connection->close();
