<?php

declare(strict_types=1);

namespace App\Domain\Publisher;

interface MapperInterface
{
    public function process($dataObject): array;
}
