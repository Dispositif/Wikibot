<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

/**
 * Class BnfMapper
 *
 * @package App\Domain\Publisher
 */
class BnfMapper extends AbstractBookMapper implements MapperInterface
{
    /**
     * @param $data array
     *
     * @return array
     */
    public function process($data): array
    {
        return [];
    }
}
