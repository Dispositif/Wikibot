<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

/**
 * Class PublisherMapperFactory
 *
 * @package App\Domain\Publisher
 */
class PublisherMapperFactory
{
    /**
     * @param $url
     *
     * @return MapperInterface|null
     */
    public static function fromURL(string $url): ?MapperInterface
    {
        if (preg_match('#^https?://(www\.)?lemonde\.fr/[^ ]+$#i', $url)) {
            return new LeMondeMapper();
        }
        if (preg_match('#^https?://(www\.)?lefigaro\.fr/[^ ]+$#i', $url)) {
            return new FigaroMapper();
        }
        if (preg_match('#^https?://(www\.)?liberation\.fr/[^ ]+$#i', $url)) {
            return new LiberationMapper();
        }
        if (preg_match('#^https?://(www\.)?la-croix\.com/[^ ]+$#i', $url)) {
            return new LaCroixMapper();
        }


        return null;
    }
}
