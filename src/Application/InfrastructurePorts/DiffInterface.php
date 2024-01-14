<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2024 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\InfrastructurePorts;

interface DiffInterface
{
    public function __construct(string $diffStyle = 'Unified');

    public function getDiff(string $oldText, string $newText): string;
}
