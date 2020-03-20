<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Infrastructure;

/**
 * Dirty.
 * Class CirrusSearch
 *
 * @package App\Infrastructure
 */
class CirrusSearch
{
    /**
     * todo REFAC : move to API library
     *
     * @param string $url
     *
     * @return array
     */
    public function search(string $url): array
    {
        $json = file_get_contents($url);

        $myArray = json_decode($json, true);
        $result = $myArray['query']['search'];
        if (empty($result)) {
            return [];
        }

        foreach ($result as $res) {
            $titles[] = trim($res['title']);
        }

        return $titles;
    }
}
