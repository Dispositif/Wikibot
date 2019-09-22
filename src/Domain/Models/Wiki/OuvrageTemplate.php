<?php


namespace App\Domain\Models\Wiki;


use App\Domain\TextUtil;
use App\Domain\WikiTextUtil;

/**
 * TODO : Extraction de plume=,extrait=,commentaire= (obsolètes) sur {{plume}},{{citation bloc}},{{commentaire biblio}}
 * Class OuvrageTemplate
 */
class OuvrageTemplate extends AbstractWikiTemplate
{
    public $externalTemplates = []; // todo

    const MODEL_NAME = 'ouvrage';

    const REQUIRED_PARAMETERS
        = [
            //        'auteur1' => '',
            'titre' => '', // obligatoire
            //        'éditeur' => '',
            //        'année'=>'',
            //        'pages totales' => '',
            //        'isbn' => ''
        ];
    /**
     * update 18 sept 2019
     */
    const PARAM_ALIAS
        = [
            'lang' => 'langue',
            'lien langue' => 'langue',
            //            'prénom' => 'prénom1', // fréquent : ajouté sur params normaux
            'first1' => 'prénom1',
            'first2' => 'prénom2',
            'first3' => 'prénom3',
            //            'nom' => 'nom1', // fréquent : ajouté sur params normaux
            'last1' => 'nom1',
            'last2' => 'nom2',
            'last3' => 'nom3',
            'postnom' => 'postnom1',
            'lien auteur' => 'lien auteur1',
            //        'auteur' => 'auteur1', // fréquent : ajouté sur params normaux malgré alias
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
            'pages' => 'pages totales',
            'page' => 'pages totales',
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
            'page' => 'passage',
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
            'editor' => '',
            'editor-link' => '',
            'others' => '',
            'trans_title' => 'titre traduit',
            'type' => '',
            'edition' => "numéro d'édition",
            'series' => '',
            'volume' => 'volume',
            'date' => 'date',
            'month' => 'mois',
            'language' => 'langue',
            'id' => 'identifiant',
            'trans_chapter' => 'titre chapitre traduit',
            'quote' => 'extrait',
            /* END {{cite book}} to {{ouvrage}} convertion */
            'publication-place' => 'lieu',
            //  'editor-last', 'editor-first', 'editor2-last', etc
            'publication-date' => 'date',
            'author-link' => 'lien auteur1',
        ];
    protected $parametersByOrder
        = [
            'id', // déconseillé. En tête pour visibilité, car utilisé comme ancre
            'langue',
            'langue originale',
            'auteurs', // déconseillé => auteur1, auteur2...
            'co-auteur', // obsolète
            'auteur', // alias de auteur1 mais très fréquent (conservation style)
            'auteur1',
            'prénom1',
            'prénom', // alias mais fréquent
            'nom1',
            'nom', // alias mais fréquent
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
            'champ libre',
            'titre',
            'sous-titre',
            'titre original',
            'traduction titre',
            'volume',
            'tome',
            'titre volume',
            'titre tome', // alias 'titre volume' à utilisé avec 'tome'
            'lieu',
            'éditeur',
            'lien éditeur', // obsolète
            'nature ouvrage',
            'collection',
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
            'format livre',
            'format', // obsolete
            'isbn',
            'isbn2',
            'isbn3',
            'isbn erroné',
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
            'passage',
            'libellé',
            'référence',
            'référence simplifiée',
            'plume', // obsolete {{plume}}
            'extrait', // obsolete => {{citation bloc}}
            'commentaire', // obsolete => {{commentaire biblio}}
        ];

    protected function setTitre(string $titre)
    {
        // Typo : majuscule
        $titre = TextUtil::mb_ucfirst($titre);
        $this->parametersValues['titre'] = $titre;
    }

    protected function setLieu($lieu)
    {
        // pas de wikilien sur lieu (stats/pertinence)
        $lieu = WikiTextUtil::deWikify($lieu);
        $this->parametersValues['lieu'] = $lieu;
    }
    protected function setPagestotales($pages) {
        // confusion 'passage' = "134-145"
        if(preg_match('/[0-9]+\-[0-9]+$/',$pages) > 0){
            $this->parametersValues['passage'] = $pages;
            return;
        }
        $this->parametersValues['pages totales'] = $pages;
    }

    /**
     * todo move + refac
     * dirty
     */
    public function serializeExternalTemplates(): string
    {
        $res = '';
        if(!empty($this->externalTemplates)){
            foreach ($this->externalTemplates as $externalTemplate) {
                $res .= $externalTemplate->raw;
            }
        }
        return $res;
    }

    /**
     * Consensus Ouvrage (2012) sur suppression [[2010 en littérature|2010]]
     * @param $str
     */
    protected function setAnnee($str) {
        $str = WikiTextUtil::deWikify($str);
        $this->parametersValues['année'] = $str;
        // major
    }

    //    protected function setLangue() { }
    //    protected function setLangueoriginale() { }
    //    protected function setAuteur1() { }
    //    protected function setPrenom1() { }
    //    protected function setNom1() { }
    //    protected function setPostnom1() { }
    //    protected function setLienauteur1() { }
    //    protected function setDirecteur1() { }
    //    protected function setResponsabilite1() { }
    //    protected function setAuteur2() { }
    //    protected function setPrenom2() { }
    //    protected function setNom2() { }
    //    protected function setPostnom2() { }
    //    protected function setLienauteur2() { }
    //    protected function setDirecteur2() { }
    //    protected function setResponsabilite2() { }
    //    protected function setAuteur3() { }
    //    protected function setPrenom3() { }
    //    protected function setNom3() { }
    //    protected function setPostnom3() { }
    //    protected function setLienauteur3() { }
    //    protected function setDirecteur3() { }
    //    protected function setResponsabilite3() { }
    //    protected function setEtal() { }
    //    protected function setAuteurinstitutionnel() { }
    //    protected function setTraducteur() { }
    //    protected function setTraductrice() { }
    //    protected function setPreface() { }
    //    protected function setPostface() { }
    //    protected function setIllustrateur() { }
    //    protected function setPhotographe() { }
    //    protected function setChamplibre() { }
    //    protected function setTitre() { }
    //    protected function setSoustitre() { }
    //    protected function setTitreoriginal() { }
    //    protected function setTraductiontitre() { }
    //    protected function setVolume() { }
    //    protected function setTome() { }
    //    protected function setTitrevolume() { }
    //    protected function setTitretome() { }

    //    protected function setEditeur() { }
    //    protected function setNatureouvrage() { }
    //    protected function setCollection() { }
    //    protected function setSerie() { }
    //    protected function setNumerodanscollection() { }
    //    protected function setAnnee() { }
    //    protected function setMois() { }
    //    protected function setJour() { }
    //    protected function setDate() { }
    //    protected function setNumerodedition() { }
    //    protected function setAnneepremireedition() { }
    //    protected function setReimpression() { }
    //    protected function setPagestotales() { }
    //    protected function setFormatlivre() { }
    //    protected function setIsbn() { }
    //    protected function setIsbn2() { }
    //    protected function setIsbn3() { }
    //    protected function setIsbnerrone() { }
    //    protected function setIssn() { }
    //    protected function setIssn2() { }
    //    protected function setIssn3() { }
    //    protected function setEissn() { }
    //    protected function setIsmn() { }
    //    protected function setEan() { }
    //    protected function setAsin() { }
    //    protected function setOclc() { }
    //    protected function setBnf() { }
    //    protected function setLccn() { }
    //    protected function setDnb() { }
    //    protected function setDoi() { }
    //    protected function setPmid() { }
    //    protected function setJstor() { }
    //    protected function setBibcode() { }
    //    protected function setMathreviews() { }
    //    protected function setZbl() { }
    //    protected function setArxiv() { }
    //    protected function setSudoc() { }
    //    protected function setWikisource() { }
    //    protected function setPresentationenligne() { }
    //    protected function setLireenligne() { }
    //    protected function setEcouterenligne() { }
    //    protected function setFormatelectronique() { }
    //    protected function setConsultele() { }
    //    protected function setPartie() { }
    //    protected function setNumerochapitre() { }
    //    protected function setTitrechapitre() { }
    //    protected function setPassage() { }
    //    protected function setId() { }
    //    protected function setLibelle() { }
    //    protected function setReference() { }
    //    protected function setReferencesimplifiee() { }
    //    protected function setPlume() { }
    //    protected function setExtrait() { }
    //    protected function setCommentaire() { }

}
