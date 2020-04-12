<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Infrastructure;

use App\Domain\Exceptions\ConfigException;

/**
 * Dirty.
 * Class CirrusSearch
 *
 * @package App\Infrastructure
 */
class CirrusSearch implements PageListInterface
{
    /**
     * @var string
     */
    private $url;

    /**
     * CirrusSearch constructor.
     *
     * @param string|null $url
     */
    public function __construct(?string $url = null)
    {
        $this->url = $url;
    }

    public function setUrl(string $url)
    {
        $this->url = $url;
    }

    /**
     * TODO: use Wiki API library or Guzzle
     *
     * @return array
     * @throws ConfigException
     */
    public function getPageTitles(): array
    {
        if (!$this->url) {
            throw new ConfigException('CirrusSearch null URL');
        }

        $json = file_get_contents($this->url);
        if (false === $json) {
            return [];
        }

        $myArray = json_decode($json, true);
        $result = $myArray['query']['search'];
        if (empty($result)) {
            return [];
        }

        $titles = [];
        foreach ($result as $res) {
            $titles[] = trim($res['title']);
        }

        return $titles;
    }
}
