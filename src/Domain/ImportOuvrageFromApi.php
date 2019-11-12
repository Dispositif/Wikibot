<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Publisher\BookApiInterface;
use App\Domain\Publisher\MapperInterface;
use Exception;
use Scriptotek\GoogleBooks\Volume;

class ImportOuvrageFromApi
{
    private $adapter;

    private $ouvrage;

    /**
     * OuvrageFromApi constructor.
     *
     * @param OuvrageTemplate  $ouvrage
     * @param BookApiInterface $adapter
     */
    public function __construct(OuvrageTemplate $ouvrage, BookApiInterface $adapter)
    {
        $this->adapter = $adapter;
        $this->ouvrage = clone $ouvrage;
    }

    /**
     * Inutile si pas de clonage $ouvrage dans construct().
     *
     * @return OuvrageTemplate|null
     */
    public function getOuvrage(): ?OuvrageTemplate
    {
        return $this->ouvrage;
    }

    /**
     * @param string $isbn
     *
     * @return OuvrageTemplate
     *
     * @throws Exception
     */
    public function hydrateFromIsbn(string $isbn): OuvrageTemplate
    {
        $volume = $this->getDataByIsbn($isbn);
        /**
         * @var Volume
         */
        $data = $this->mapping($volume);

        try {
            $this->ouvrage->hydrate($data);
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $this->ouvrage;
    }

    /**
     * @param $volume
     *
     * @return array
     */
    private function mapping($volume): array
    {
        $mapper = $this->adapter->getMapper();

        if (empty($volume)) {
            return [];
        }
        /**
         * @var $mapper MapperInterface
         */
        $data = $mapper->process($volume);

        return $data;
    }

    private function getDataByIsbn(string $isbn)
    {
        return $this->adapter->getDataByIsbn($isbn);
    }
}
