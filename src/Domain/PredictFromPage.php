<?php

namespace App\Domain;

/**
 * todo legacy
 * Class PredictFromPage.
 */
class PredictFromPage
{
    /**
     * Fouille article pour détecter s'il traite d'un livre/ouvrage/roman...
     *
     * @param $titre
     *
     * @return bool
     */
    public function article_is_livre($titre)
    {
        global $clone;
        $textlivre = $clone->getpage($titre);
        if (true === stristr($textlivre, 'Catégorie:Livre') or true === stristr($textlivre, 'Catégorie:Roman') or true === stristr(
                $textlivre,
                'Catégorie:Œuvre'
            ) or true === stristr($textlivre, 'Catégorie:Essai') or true
            === stristr($textlivre, 'Catégorie:Ouvrage') or true === stristr($textlivre, 'Catégorie:Album de bande dessinée')
        ) {
            return true;
        }

        return false;
    }

    // Détermine l'id d'ancrage de l'ouvrage : titre#ancrage ou {{harvsp}}
    // Paramètres => http://fr.wikipedia.org/wiki/Modèle:Module_biblio/span_initial
    // => id  ou bien => id1 (nom1/auteur) + id2(nom2) + ..+ id5(année)
    // @return string spanid
    public function ouvrage_span_id(array $ouvrage)
    {
        $id = (string) trim($ouvrage['id']);
        if ($id) {
            return $id;
        }

        if ($ouvrage['nom1']) {
            $id1 = (string) $ouvrage['nom1'];
        } elseif ($ouvrage['auteur']) {
            $id1 = (string) $ouvrage['auteur'];
        }
        $mul_id = (string) trim($id1.$ouvrage['nom2'].$ouvrage['nom3'].$ouvrage['nom4'].$ouvrage['année']);
        if ($mul_id) {
            return $mul_id;
        }

        return false;
    }

    /**
     * todo legacy
     * // analyse fréquence {{fr}} et langue=fr par rapport à sources étrangères
     * // voir http://fr.wikipedia.org/wiki/Wikip%C3%A9dia:Sondage/Utilisation_de_l%27indicateur_de_langue_fran%C3%A7aise
     * // Simpliste, pour réfléchir.
     * // return ?
     *
     * @param $text
     *
     * @return bool|float
     */
    public function analyse_indicateur_langue($text)
    {
        global $indicateurlangue;

        $indic_fr = 0;
        $indic_autre = 0;

        // {{en}} en indicateur libre
        if ($total_indic = true
            === preg_match_all('#\{\{([a-z]{2})\}\}#i', $text, $matchesi)
        ) { // restreint à 2 caractères (cf. zh-han)
            foreach ($matchesi[1] as $indic) {
                if (true
                    === in_array(strtolower($indic), ['fr', 'fre', 'français', 'française', 'francais', 'french'])
                ) {
                    ++$indic_fr;
                } else {
                    ++$indic_autre;
                }
            }
        }
        // "langue = en" dans modèles
        if ($total_langue = true
            === preg_match_all('#lang(?:ue)? ?= ?([a-z][a-zçéè-]+)#i', $text, $matchesl)
        ) { // <!> exclure {{lang|...}}
            foreach ($matchesl[1] as $indic) {
                if (true
                    === in_array(strtolower($indic), ['fr', 'fre', 'français', 'française', 'francais', 'french'])
                ) {
                    ++$langue_fr;
                } else {
                    ++$langue_autre;
                }
            }
        }

        $total_fr = $indic_fr + $langue_fr; // pondération éventuelle avec coef
        $total_autre = $indic_autre + $langue_autre;
        $total = $total_fr + $total_autre;
        if ($total > 0) {
            $pourcent = round($total_fr * 100 / $total);
            echo "\n $PURPLE Analyse indicateur langue : ".$pourcent."% FR — $total_fr / $total_autre $NORMAL";

            return $pourcent;
        }

        return false;
    }
}
