<?php


namespace App\Domain\Models\Wiki;


use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;

class OuvrageClean extends OuvrageTemplate
{

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

    protected function setPagestotales($pages)
    {
        // confusion 'passage' = "134-145"
        if (preg_match('/[0-9]+\-[0-9]+$/', $pages) > 0 && !isset($this->parametersValues['passage'])) {
            $this->parametersValues['passage'] = $pages;

            return;
        }
        $this->parametersValues['pages totales'] = $pages;
    }

    /**
     * Consensus Ouvrage (2012) sur suppression [[2010 en littérature|2010]]
     *
     * @param $str
     */
    protected function setAnnee($str)
    {
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
