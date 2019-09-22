<?php


namespace App\Domain\Models\Wiki;

/**
 * https://fr.wikipedia.org/wiki/Mod%C3%A8le:Google_Livres
 * Le premier paramètre (ou id) est obligatoire. L
 * Le deuxième (ou titre) est requis si on ne veut pas fabriquer le lien brut (inclusion {{ouvrage}} 'Lire en ligne')
 * Class GoogleLivresTemplate
 */
class GoogleLivresTemplate extends AbstractWikiTemplate
{
    const MODEL_NAME          = 'Google Livres';
    const REQUIRED_PARAMETERS = ['id' => ''];
    const PARAM_ALIAS         = ['1' => 'id', '2' => 'titre'];
    protected $parametersByOrder
        = ['id', 'titre', 'couv', 'page', 'romain', 'page autre', 'surligne'];

}
