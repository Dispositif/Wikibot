<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

/**
 * Parameters names changed on hydration (alias)
 * Raw datas : Value are not normalized (see rather OuvrageClean class for optimized values)
 * Note : Avec serialization(), extraction de extrait=,commentaire= (obsolètes)
 * sur {{citationbloc}},{{commentaire biblio}}...
 * Class OuvrageTemplate.
 */
class OuvrageTemplate extends AbstractWikiTemplate
{
    const MODEL_NAME = 'Ouvrage'; // todo

    const REQUIRED_PARAMETERS
        = [
            //            'langue' => '', // inutile avec 'fr'
            //            'auteur1' => '', // duplicate with prénom1/nom1
            'titre' => '', // obligatoire
            'éditeur' => '',
            'année' => '', // géré dans serialize
            //            'pages totales' => '',
            //            'passage' => '', // pas pertinent sur biblio et liste oeuvres
            'isbn' => '',
        ];

    /**
     * update 18 sept 2019.
     */
    const PARAM_ALIAS
        = [
            'numéro dans la collection' => 'numéro dans collection',
            'lang' => 'langue',
            'lien langue' => 'langue',
            'prénom' => 'prénom1', // fréquent : ajouté sur params normaux
            'first1' => 'prénom1',
            'first2' => 'prénom2',
            'first3' => 'prénom3',
            'nom' => 'nom1', // fréquent : ajouté sur params normaux
            'last1' => 'nom1',
            'last2' => 'nom2',
            'last3' => 'nom3',
            'postnom' => 'postnom1',
            'lien auteur' => 'lien auteur1',
            'auteur' => 'auteur1', // fréquent : ajouté sur params normaux malgré alias
            'directeur' => 'directeur1',
            // non-documenté (Aristote)
            'author1' => 'auteur1',
            'author2' => 'auteur2',
            'author3' => 'auteur3',
            'author4' => 'auteur4',
            'et alii' => 'et al.',
            'trad' => 'traducteur',
            'traduction' => 'traducteur',
            'title' => 'titre',
            'titre vo' => 'titre original',
            'location' => 'lieu',
            'year' => 'année',
            'édition' => 'éditeur',
            'publisher' => 'éditeur',
            'numéro édition' => "numéro d'édition",
            'origyear' => 'année première édition',
            'publi' => 'réimpression',
            //            'pages' => 'pages totales', // doc - mis temporairement en paramètre normal
            //            'page' => 'passage', // Doc - mis temporairement en paramètre normal
            //        'format' => 'format livre', //  ou 'format électronique' (pdf)
            'ISBN' => 'isbn',
            'isbn1' => 'isbn',
            'ISBN1' => 'isbn',
            'ISBN2' => 'isbn2',
            'ISBN3' => 'isbn3',
            'issn1' => 'issn',
            'iSSN' => 'issn',
            'iSSN1' => 'issn',
            'ISSN2' => 'issn2',
            'ISSN3' => 'issn3',
            'E-ISSN' => 'e-issn',
            'ASIN' => 'asin',
            'résumé' => 'présentation en ligne',
            'url résumé' => 'présentation en ligne',
            'url' => 'lire en ligne',
            'url texte' => 'lire en ligne',
            'accessdate' => 'consulté le',
            'numéro' => 'numéro chapitre',
            'chapitre' => 'numéro chapitre',
            'chap' => 'titre chapitre',
            'chapter' => 'titre chapitre',
            'ref' => 'référence simplifiée',
            // TODO: desactive ?
            /*
             * Conversion enwiki {{cite book}} => {{ouvrage}} (2012)
             */
            'author1-link' => 'lien auteur1',
            'author2-link' => 'lien auteur2',
            'author3-link' => 'lien auteur3',
            'author4-link' => 'lien auteur4',
            'author5-link' => 'lien auteur5',
            'author6-link' => 'lien auteur6',
            'author7-link' => 'lien auteur7',
            'author8-link' => 'lien auteur8',
            'author9-link' => 'lien auteur9',
            'last' => 'nom1',
            'first' => 'prénom1',
            'last4' => 'nom4',
            'first4' => 'prénom4',
            'last5' => 'nom5',
            'first5' => 'prénom5',
            'last6' => 'nom6',
            'first6' => 'prénom6',
            'last7' => 'nom7',
            'first7' => 'prénom7',
            'last8' => 'nom8',
            'first8' => 'prénom8',
            'last9' => 'nom9',
            'first9' => 'prénom9',
            'author' => 'auteur',
            'authorlink' => 'lien auteur',
            'coauthors' => 'co-auteur',
            //            'editor' => '',
            //            'editor-link' => '',
            //            'others' => '',
            'trans_title' => 'titre traduit',
            //            'type' => '',
            'edition' => "numéro d'édition",
            //            'series' => '',
            'date' => 'date',
            'month' => 'mois',
            'language' => 'langue',
            'trans_chapter' => 'titre chapitre traduit',
            'quote' => 'extrait',
            /* END {{cite book}} to {{ouvrage}} convertion */
            'publication-place' => 'lieu',
            //  'editor-last', 'editor-first', 'editor2-last', etc
            'publication-date' => 'date',
            'author-link' => 'lien auteur1',
            'première édition' => 'année première édition',
            'citation' => 'extrait',
            'chapter-url' => 'lire en ligne',
            'p' => 'passage',
        ];

    public $externalTemplates = [];

    protected $parametersByOrder
        = [
            'langue',
            'langue originale',
            'auteurs', // déconseillé => auteur1, auteur2...
            'co-auteur', // obsolète
            //            'auteur', // alias de auteur1 mais très fréquent (conservation style)
            'auteur1',
            'prénom1',
            //            'prénom', // alias mais fréquent
            'nom1',
            //            'nom', // alias mais fréquent
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
            'auteur3',
            'prénom3',
            'nom3',
            'postnom3',
            'lien auteur3',
            'directeur3',
            'responsabilité3',
            'auteur4',
            'prénom4',
            'nom4',
            'postnom4',
            'lien auteur4',
            'directeur4',
            'responsabilité4',
            'auteur5',
            'prénom5',
            'nom5',
            'postnom5',
            'lien auteur5',
            'directeur5',
            'responsabilité5',
            'auteur6',
            'prénom6',
            'nom6',
            'postnom6',
            'lien auteur6',
            'directeur6',
            'responsabilité6',
            'auteur7',
            'prénom7',
            'nom7',
            'postnom7',
            'lien auteur7',
            'directeur7',
            'responsabilité7',
            'auteur8',
            'prénom8',
            'nom8',
            'postnom8',
            'lien auteur8',
            'directeur8',
            'responsabilité8',
            'auteur9',
            'prénom9',
            'nom9',
            'postnom9',
            'lien auteur9',
            'directeur9',
            'responsabilité9',
            'auteur10',
            'prénom10',
            'nom10',
            'postnom10',
            'lien auteur10',
            'directeur10',
            'responsabilité10',
            'auteur11',
            'prénom11',
            'nom11',
            'postnom11',
            'lien auteur11',
            'directeur11',
            'responsabilité11',
            'auteur12',
            'prénom12',
            'nom12',
            'postnom12',
            'lien auteur12',
            'directeur12',
            'responsabilité12',
            'auteur13',
            'prénom13',
            'nom13',
            'postnom13',
            'lien auteur13',
            'directeur13',
            'responsabilité13',
            'auteur14',
            'prénom14',
            'nom14',
            'postnom14',
            'lien auteur14',
            'directeur14',
            'responsabilité14',
            'auteur15',
            'prénom15',
            'nom15',
            'postnom15',
            'lien auteur15',
            'directeur15',
            'responsabilité15',
            'auteur16',
            'prénom16',
            'nom16',
            'postnom16',
            'lien auteur16',
            'directeur16',
            'responsabilité16',
            'auteur17',
            'prénom17',
            'nom17',
            'postnom17',
            'lien auteur17',
            'directeur17',
            'responsabilité17',
            'auteur18',
            'prénom18',
            'nom18',
            'postnom18',
            'lien auteur18',
            'directeur18',
            'responsabilité18',
            'auteur19',
            'prénom19',
            'nom19',
            'postnom19',
            'lien auteur19',
            'directeur19',
            'responsabilité19',
            /**/
            'et al.',
            'auteur institutionnel',
            'traducteur',
            'traductrice',
            'préface',
            'postface',
            'illustrateur',
            'photographe',
            'editor', // provisoire
            'editor1', // provisoire
            'editor2', // provisoire
            'editor3', // provisoire
            'champ libre',
            'titre',
            'sous-titre',
            'lien titre',
            'titre original',
            'traduction titre',
            'volume',
            'tome',
            'titre volume',
            'titre tome', // alias 'titre volume' à utilisé avec 'tome'
            'éditeur',
            'lien éditeur', // obsolète
            'collection',
            'lieu',
            'nature ouvrage',
            'série',
            'numéro dans collection',
            'année',
            'mois',
            'jour',
            'date',
            "numéro d'édition",
            'année première édition',
            'réimpression',
            'pages totales',
            'page', // temporaire
            'pages', // temporaire
            'format livre',
            'format', // obsolete
            'passage',
            'isbn',
            'isbn10',
            'isbn2',
            'isbn3',
            'isbn erroné',
            'isbn invalide', // not official // pour bot
            'issn',
            'issn2',
            'issn3',
            'e-issn',
            'ismn',
            'ean',
            'asin',
            'oclc',
            'bnf',
            'lccn',
            'dnb',
            'doi',
            'pmid',
            'jstor',
            'bibcode',
            'math reviews',
            'zbl',
            'arxiv',
            'sudoc',
            'wikisource',
            'présentation en ligne',
            'lire en ligne',
            'écouter en ligne',
            'format électronique',
            'consulté le',
            'partie',
            'numéro chapitre',
            'titre chapitre',
            'id', // déconseillé
            'libellé',
            'référence',
            'référence simplifiée',
            'extrait', // obsolete? => {{citation bloc}}
            'commentaire', // obsolete? => {{commentaire biblio}}
            'plume', // obsolète ou pas? (déc. 2019)
        ];

    private $source;

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param mixed $source
     */
    public function setSource($source): void
    {
        $this->source = $source;
    }

    /**
     * @param bool|null $cleanOrder
     *
     * @return string
     */
    public function serialize(?bool $cleanOrder = false): string
    {
        $serial = parent::serialize($cleanOrder);
        $serial = $this->anneeOrDateSerialize($serial);
        $serial = $this->stripIsbnBefore1970($serial);

        return $serial.$this->serializeExternalTemplates();
    }

    /**
     * todo move to abstract ? + refac
     * dirty.
     */
    public function serializeExternalTemplates(): string
    {
        $res = '';
        if (!empty($this->externalTemplates)) {
            foreach ($this->externalTemplates as $externalTemplate) {
                $res .= $externalTemplate->raw;
            }
        }

        return $res;
    }

    /**
     * Strip empty 'isbn' before 1970.
     *
     * @param string $serial
     *
     * @return string
     */
    private function stripIsbnBefore1970(string $serial): string
    {
        if (preg_match("#\|[\n ]*isbn=[\n ]*[|}]#", $serial) > 0
            && preg_match("#\|[\n ]*année=([0-9]+)[}| \n]#", $serial, $matches) > 0
        ) {
            $year = intval($matches[1]);
            if ($year > 0 && $year < 1970) {
                $serial = preg_replace("#\|[\n ]*isbn=[\n ]*#", '', $serial);
            }
        }

        return $serial;
    }

    /**
     * Pas de serialization année vide si date non vide.
     *
     * @param string $serial
     *
     * @return string
     */
    private function anneeOrDateSerialize(string $serial): string
    {
        if (preg_match("#\|[\n ]*année=[\n ]*\|#", $serial) > 0
            && preg_match("#\|[\n ]*date=#", $serial) > 0
        ) {
            $serial = preg_replace("#\|[\n ]*année=[\n ]*#", '', $serial);
        }

        return $serial;
    }

    /**
     * Détermine l'id d'ancrage <span> de l'ouvrage.
     * Utilisable par titre#ancrage ou {{harvsp}}.
     * Voir http://fr.wikipedia.org/wiki/Modèle:Module_biblio/span_initial.
     */
    public function getSpanInitial(): string
    {
        // Identifiant paramétré
        if ($this->getParam('id')) {
            return $this->getParam('id');
        }

        // Identifiant déduit : auteur1,2,3,4,année
        $id = '';
        for ($i = 1; $i < 4; ++$i) {
            $id .= ($this->getParam('nom'.$i)) ?? $this->getParam('auteur'.$i) ?? '';
        }
        $id .= $this->getParam('année') ?? '';

        return $id;
    }
}
