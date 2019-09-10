<?php

namespace App\Domain\Models\Wiki;

/**
 * Class LienWebTemplate
 */
class LienWebTemplate extends AbstractWikiTemplate implements WikiTemplateInterface
{
    const MODEL_NAME = 'lien web';

    protected $parametersByOrder
        = [
            'langue',
            'auteur',
            'lien auteur',
            'auteur1',
            'prénom1',
            'nom1',
            'postnom1',
            'lien auteur1',
            'directeur1',
            'responsabilité1',
            'auteur2',
            'prénom2',
            'nom2',
            'postnom2',
            'lien auteur2',
            'directeur2',
            'responsabilité2',
            'et al.',
            'auteur institutionnel',
            'traducteur',
            'photographe',
            'champ libre',
            'titre',
            'sous-titre',
            'traduction titre',
            'description',
            'url',
            'format',
            'série',
            'site',
            'périodique',
            'lieu',
            'éditeur',
            'jour',
            'mois',
            'année',
            'date',
            'isbn',
            'issn',
            'e-issn',
            'oclc',
            'pmid',
            'doi',
            'jstor',
            'numdam',
            'bibcode',
            'math reviews',
            'zbl',
            'arxiv',
            'consulté le',
            'citation',
            'page',
            'id',
            'libellé',
            'brisé le',
            'archive-url',
            'archive-date',
            'dead-url',
        ];
    /**
     * Also define the required parameters
     *
     * @var array
     */
    protected $parameters
        = [
            'langue' => '',
            'titre' => '',
            'url' => '',
            'date' => '',
            'site' => '',
            'consulté le' => '',
        ];

    public function __construct()
    {
    }


    public function setTitre(string $titre)
    {
        $this->parameters['titre'] = $titre;
    }

}
