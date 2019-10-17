<?php

declare(strict_types=1);

namespace App\Application;

interface QueueInterface
{
    public function getNewRaw(): ?string;

    public function sendCompletedData(array $finalData): bool;
}
