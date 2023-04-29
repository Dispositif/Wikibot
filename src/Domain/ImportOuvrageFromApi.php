<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\InfrastructurePorts\BookApiInterface;
use App\Domain\Models\Wiki\OuvrageTemplate;
use Exception;
use LogicException;
use Scriptotek\GoogleBooks\Volume;
use SimpleXMLElement;
use Throwable;

/**
 * TODO HEXA : create interface for Scriptotek GB Volume !!
 */
class ImportOuvrageFromApi
{
    private readonly OuvrageTemplate $ouvrage;

    /**
     * OuvrageFromApi constructor.
     */
    public function __construct(OuvrageTemplate $ouvrage, private readonly BookApiInterface $adapter)
    {
        $this->ouvrage = clone $ouvrage;
    }

    /**
     * Inutile si pas de clonage $ouvrage dans construct().
     */
    public function getOuvrage(): ?OuvrageTemplate
    {
        return $this->ouvrage;
    }

    /**
     *
     *
     * @throws Exception
     */
    public function hydrateFromIsbn(string $isbn): OuvrageTemplate
    {
        $e = null;
        $volume = $this->getDataByIsbn($isbn);
        /**
         * @var Volume
         */
        $data = $this->mapping($volume);

        if(isset($data['infos'])) {
            $infos = $data['infos'];
            unset($data['infos']);
        }

        try {
            $this->ouvrage->hydrate($data);
            if(isset($infos)) {
                $this->ouvrage->setInfos($infos);
            }
        } catch (Throwable $e) {
            throw new LogicException('Hydratation error '.$e->getMessage(), $e->getCode(), $e);
        }

        return $this->ouvrage;
    }

    /**
     * @param $volume
     */
    private function mapping($volume): array
    {
        $mapper = $this->adapter->getMapper();

        // FIXED : empty(SimpleXmlElement) => false !!
        if (empty($volume) && !$volume instanceof SimpleXMLElement) {
            return [];
        }

        /*
         * @var $mapper MapperInterface
         */
        return $mapper->process($volume);
    }

    private function getDataByIsbn(string $isbn)
    {
        return $this->adapter->getDataByIsbn($isbn);
    }
}
