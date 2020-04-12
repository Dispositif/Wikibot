<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Domain;


use App\Application\RefWebTransformer;
use App\Application\TransformerInterface;
use App\Infrastructure\Logger;

class TransformerFactory
{
    public static function fromString(string $string): ?TransformerInterface
    {
        if (preg_match('#^https?://[^ ]+$#i', $string)) {
            return new RefWebTransformer(new Logger());
        }

        return null;
    }

}
