<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Application\OuvrageComplete\Handlers;


use App\Domain\InfrastructurePorts\WikidataAdapterInterface;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Publisher\Wikidata2Ouvrage;

class WikidataSearchHandler implements CompleteHandlerInterface
{

    /**
     * @var OuvrageTemplate
     */
    protected $bnfOuvrage;
    /**
     * @var WikidataAdapterInterface
     */
    protected $wikidataAdapter;
    /**
     * @var string article title
     */
    protected $page;

    public function __construct(OuvrageTemplate $bnfOuvrage, WikidataAdapterInterface $wikidataAdapter, string $page)
    {
        $this->bnfOuvrage = $bnfOuvrage;
        $this->wikidataAdapter = $wikidataAdapter;
        $this->page = $page;
    }

    public function handle(): ?OuvrageTemplate
    {
        // Wikidata requests from $infos (ISBN/ISNI)
        if (!empty($this->bnfOuvrage->getInfos())) {
            // TODO try/catch
            $wdComplete = new Wikidata2Ouvrage($this->wikidataAdapter, clone $this->bnfOuvrage, $this->page);
            return $wdComplete->getOuvrage();
        }

        return null;
    }
}