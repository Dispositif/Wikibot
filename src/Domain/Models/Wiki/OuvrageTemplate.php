<?php


namespace App\Domain\Models\Wiki;


use App\Domain\TextUtil;

/**
 * TODO : Extraction de plume=,extrait=,commentaire= (obsolètes) sur {{plume}},{{citation bloc}},{{commentaire biblio}}
 * Class OuvrageTemplate
 */
class OuvrageTemplate extends AbstractWikiTemplate
{
    const MODEL_NAME          = 'ouvrage';

    const REQUIRED_PARAMETERS = [
//        'auteur1' => '',
        'titre' => '', // obligatoire
//        'éditeur' => '',
//        'année'=>'',
//        'pages totales' => '',
//        'isbn' => ''
    ];

    protected $parametersByOrder
        = [
            'langue',
            'langue originale',
            'auteurs', // déconseillé => auteur1, auteur2...
            'co-auteur', // obsolète
            'auteur', // alias de auteur1 mais très fréquent (conservation style)
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
            'id',
            'libellé',
            'référence',
            'référence simplifiée',
            'plume', // obsolete {{plume}}
            'extrait', // obsolete => {{citation bloc}}
            'commentaire', // obsolete => {{commentaire biblio}}
        ];

    /**
     * update 18 sept 2019
     */
    const PARAM_ALIAS = [
        'lang'=>'langue',
        'lien langue'=>'langue',
        'prénom'=>'prénom1',
        'first1' => 'prénom1',
        'first2' => 'prénom2',
        'first3' => 'prénom3',
        'nom' => 'nom1',
        'last1' => 'nom1',
        'last2' => 'nom2',
        'last3' => 'nom3',
        'postnom'=>'postnom1',
        'lien auteur'=> 'lien auteur1',
//        'auteur' => 'auteur1', // fréquent : ajouté sur params normaux malgré alias
        'directeur' => 'directeur1', // non-documenté (Aristote)
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
        'chapitre' => 'numéro chapitre', // non-documenté, [[Aristote]]
        'chap' => 'titre chapitre',
        'chapter' => 'titre chapitre',
        'page' => 'passage',
        'ref' => 'référence simplifiée' // TODO: desactive ?

    ];

    protected function setTitre(string $titre)
    {
        // Typo : majuscule
        $titre = TextUtil::mb_ucfirst($titre);

        // Typo : sous-titre précédé de " : "
        // provoque bug regex détection lien externe http://
//        $titre = preg_replace('#[ ]*\:[ ]*#', ' : ', $titre);
        // todo typo : déplacer sous-titre dans [sous-titre]

        $this->parametersValues['titre'] = $titre;
    }

//    private function setLangue() { }
//    private function setLangueoriginale() { }
//    private function setAuteur1() { }
//    private function setPrenom1() { }
//    private function setNom1() { }
//    private function setPostnom1() { }
//    private function setLienauteur1() { }
//    private function setDirecteur1() { }
//    private function setResponsabilite1() { }
//    private function setAuteur2() { }
//    private function setPrenom2() { }
//    private function setNom2() { }
//    private function setPostnom2() { }
//    private function setLienauteur2() { }
//    private function setDirecteur2() { }
//    private function setResponsabilite2() { }
//    private function setAuteur3() { }
//    private function setPrenom3() { }
//    private function setNom3() { }
//    private function setPostnom3() { }
//    private function setLienauteur3() { }
//    private function setDirecteur3() { }
//    private function setResponsabilite3() { }
//    private function setEtal() { }
//    private function setAuteurinstitutionnel() { }
//    private function setTraducteur() { }
//    private function setTraductrice() { }
//    private function setPreface() { }
//    private function setPostface() { }
//    private function setIllustrateur() { }
//    private function setPhotographe() { }
//    private function setChamplibre() { }
//    private function setTitre() { }
//    private function setSoustitre() { }
//    private function setTitreoriginal() { }
//    private function setTraductiontitre() { }
//    private function setVolume() { }
//    private function setTome() { }
//    private function setTitrevolume() { }
//    private function setTitretome() { }
//    private function setLieu() { }
//    private function setEditeur() { }
//    private function setNatureouvrage() { }
//    private function setCollection() { }
//    private function setSerie() { }
//    private function setNumerodanscollection() { }
//    private function setAnnee() { }
//    private function setMois() { }
//    private function setJour() { }
//    private function setDate() { }
//    private function setNumerodedition() { }
//    private function setAnneepremireedition() { }
//    private function setReimpression() { }
//    private function setPagestotales() { }
//    private function setFormatlivre() { }
//    private function setIsbn() { }
//    private function setIsbn2() { }
//    private function setIsbn3() { }
//    private function setIsbnerrone() { }
//    private function setIssn() { }
//    private function setIssn2() { }
//    private function setIssn3() { }
//    private function setEissn() { }
//    private function setIsmn() { }
//    private function setEan() { }
//    private function setAsin() { }
//    private function setOclc() { }
//    private function setBnf() { }
//    private function setLccn() { }
//    private function setDnb() { }
//    private function setDoi() { }
//    private function setPmid() { }
//    private function setJstor() { }
//    private function setBibcode() { }
//    private function setMathreviews() { }
//    private function setZbl() { }
//    private function setArxiv() { }
//    private function setSudoc() { }
//    private function setWikisource() { }
//    private function setPresentationenligne() { }
//    private function setLireenligne() { }
//    private function setEcouterenligne() { }
//    private function setFormatelectronique() { }
//    private function setConsultele() { }
//    private function setPartie() { }
//    private function setNumerochapitre() { }
//    private function setTitrechapitre() { }
//    private function setPassage() { }
//    private function setId() { }
//    private function setLibelle() { }
//    private function setReference() { }
//    private function setReferencesimplifiee() { }
//    private function setPlume() { }
//    private function setExtrait() { }
//    private function setCommentaire() { }

}
