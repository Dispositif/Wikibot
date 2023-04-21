<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\InfrastructurePorts;

interface MemoryInterface
{
    public function getMemory(?bool $real = null): string;

    public function memoryUsage(?bool $real = null): string;

    public function memoryPeak(?bool $real = null): string;
}