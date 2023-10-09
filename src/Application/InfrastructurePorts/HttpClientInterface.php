<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\InfrastructurePorts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

interface HttpClientInterface
{
    public function get(string|UriInterface $uri, array $options = []): ResponseInterface;
    public function request($method, $uri, array $options = []): ResponseInterface;
}