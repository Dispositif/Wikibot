<?php
declare(strict_types=1);

namespace App\Domain;

interface MessageInterface
{
    public function send(string $queue, $message):void;
}
