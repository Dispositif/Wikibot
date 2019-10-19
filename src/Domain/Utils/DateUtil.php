<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

class DateUtil
{
    public function cleanDate(string $date)
    {
        return $this->dateEnglish2french($date);
    }

    public function dateEnglish2french(string $date)
    {
        return str_replace(
            [
                'January',
                'February',
                'March',
                'April',
                'May',
                'June',
                'July',
                'August',
                'September',
                'October',
                'November',
                'December',
            ],
            [
                'janvier',
                'février',
                'mars',
                'avril',
                'mai',
                'juin',
                'juillet',
                'août',
                'septembre',
                'octobre',
                'novembre',
                'décembre',
            ],
            $date
        );
    }

//    /**
//     * todo: legacy.
//     *
//     * @param $date
//     */
//    public function legacyDate($date)
//    {
//        // === DATES ===
//        // date=année ?
//        if (is_numeric($date) and intval($date) < 2022 and intval($date) > 1500) {
//            //            $ouvrage['année']
//            //                = $date;
//            //            unset($date);
//            //            $suivi[]
//            //                = '+année';
//        }
//
//        if (!empty($date)) {
//            $date = $this->dateEnglish2french($date);
//        }
//        if (true === $date) {
//            if (true
//                === preg_match("#([0-9]{4})[ \-\/]([01][0-9])[ \-\/]([0123][0-9])#", $date, $matches)
//            ) { // 2011-04-15
//                //                $ouvrage['année'] = intval($matches[1]);
//                //                $ouvrage['mois'] = intval($matches[2]);
//                //                $ouvrage['jour'] = intval($matches[3]);
//                //                unset($date);
//                //                $suivi[] = '±date';
//            } elseif (true
//                === preg_match("#([0123][0-9])[ \-\/]([01][0-9])[ \-\/]([0-9]{4})#", $date, $matches)
//            ) { // 15-04-2011
//                //                $ouvrage['année'] = $matches[3];
//                //                $ouvrage['mois'] = intval($matches[2]);
//                //                $ouvrage['jour'] = intval($matches[1]);
//                //                unset($ouvrage['date']);
//                //                $suivi[] = '±date2';
//            }
//        }
//
//        if (true
//            === preg_match("#([0-9]{4})[ \-\/]([01][0-9])[ \-\/]([0123][0-9])#", $ouvrage['consulté le'], $matches)
//        ) { // 2011-04-15 => 15 avril 2011
//            $ouvrage['consulté le'] = intval($matches[3]).' '.$date_mois_francais[intval($matches[2])].' '.$matches[1];
//        // TODO: Bug : 3 = mars, 03 ≠ mars (corrigé cochon). Trouver la fonction php d'éval
//        } elseif (true
//            === preg_match("#([0123][0-9])[ \-\/]([01][0-9])[ \-\/]([0-9]{4})#", $ouvrage['consulté le'], $matches)
//        ) { // 2011-04-15 => 15 avril 2011
//            $ouvrage['consulté le'] = intval($matches[1]).' '.$date_mois_francais[intval($matches[2])].' '.$matches[3];
//        }
//        if ($ouvrage['consulté le'] !== $old_consultele) {
//            $suivi[] = '±consulté';
//        }
//    }

    // TYPO https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:AutoWikiBrowser/Typos#Dates

    // Month
    //<Typo word="janvier" find="\b(\d{1,2}) +Janvier\b" replace="$1 janvier" />
    //<Typo word="janvier" find="([a-z,;:] ) ?(\[*)Janvier\b(?<!(?:Auguste) ?Janvier)" replace="$1$2janvier" />
    //<Typo word="janvier" find="\b[Jj]anv?\.? +([0-9]{4}|\[\[[0-9]{4}\]\])\b" replace="janvier $1" />
    //<Typo word="février" find="\b(\d{1,2}) +[fF][eé]vrier\b" replace="$1 février" />
    //<Typo word="février" find="([a-z,;:] ) ?(\[*)[fF][eé]vrier\b" replace="$1$2février" />
    //<Typo word="février" find="\b[Ff][eé][vb]\.? +([0-9]{4}|\[\[[0-9]{4}\]\])\b" replace="février $1" />
    //<Typo word="mars" find="\b(\d{1,2}) +Mars\b" replace="$1 mars" />
    //<Typo word="mars" find="\b[Mm]ar\.? +([0-9]{4}|\[\[[0-9]{4}\]\])\b" replace="mars $1" />
    //<Typo word="avril" find="\b(\d{1,2}) +Avril\b" replace="$1 avril" />
    //<Typo word="avril" find="([a-z,;:] |['’]) ?(\[*)Avril\b" replace="$1$2avril" />
    //<Typo word="avril" find="\b[Aa]vr\.? +([0-9]{4}|\[\[[0-9]{4}\]\])\b" replace="avril $1" />
    //<Typo word="mai" find="\b(\d{1,2}) +Mai\b" replace="$1 mai" />
    //<Typo word="mai" find="([a-z,;:] ) ?(\[*)Mai\b" replace="$1$2mai" />
    //<Typo word="juin" find="\b(\d{1,2}) +Juin\b" replace="$1 juin" />
    //<Typo word="juin" find="([a-z,;:] ) ?(\[*)Juin\b" replace="$1$2juin" />
    //<Typo word="juillet" find="\b(\d{1,2}) +Juillet\b" replace="$1 juillet" />
    //<Typo word="juillet" find="([a-z,;:] ) ?(\[*)Juillet\b(?<!(?:[Mm]onarchie de|[Rr]évolution de) ?Juillet)" replace="$1$2juillet" />
    //<Typo word="juillet" find="\b[Jj]uil?\.? +([0-9]{4}|\[\[[0-9]{4}\]\])\b" replace="juillet $1" />
    //<Typo word="août" find="\b(\d{1,2}) +Août\b" replace="$1 août" />
    //<Typo word="août" find="([a-z,;:] |['’]) ?(\[*)Août\b" replace="$1$2août" />
    //<Typo word="août" find="\b[Aa]oû.? +([0-9]{4}|\[\[[0-9]{4}\]\])\b" replace="août $1" />
    //<Typo word="aout" find="\b(\d{1,2}) +Aout\b" replace="$1 aout" />
    //<Typo word="aout" find="([a-z,;:] |['’]) ?(\[*)Aout\b" replace="$1$2aout" />
    //<Typo word="septembre" find="\b(\d{1,2}) +Septembre\b" replace="$1 septembre" />
    //<Typo word="septembre" find="([a-z,;:] ) ?(\[*)Septembre\b" replace="$1$2septembre" />
    //<Typo word="septembre" find="\b[Ss]ept?\.? +([0-9]{4}|\[\[[0-9]{4}\]\])\b" replace="septembre $1" />
    //<Typo word="octobre" find="\b(\d{1,2}) +Octobre\b" replace="$1 octobre" />
    //<Typo word="octobre" find="([a-z,;:] |['’]) ?(\[*)Octobre\b" replace="$1$2octobre" />
    //<Typo word="octobre" find="\[Oo]ct\.? +([0-9]{4}|\[\[[0-9]{4}\]\])\b" replace="octobre $1" />
    //<Typo word="novembre" find="\b(\d{1,2}) +Novembre\b" replace="$1 novembre" />
    //<Typo word="novembre" find="([a-z,;:] ) ?(\[*)Novembre\b" replace="$1$2novembre" />
    //<Typo word="novembre" find="\b[Nn]ov\.? +([0-9]{4}|\[\[[0-9]{4}\]\])\b" replace="novembre $1" />
    //<Typo word="décembre" find="\b(\d{1,2}) +[dD][ée]cembre\b" replace="$1 décembre" />
    //<Typo word="décembre" find="([a-z,;:] ) ?(\[*)[dD][ée]cembre\b" replace="$1$2décembre" />
    //<Typo word="décembre" find="\b[Dd][eé]c\.? +([0-9]{4}|\[\[[0-9]{4}\]\])\b" replace="décembre $1" />
    //<Typo word="1er du mois" find="\b1 +(janvier|février|mars|avril|mai|juin|juillet|ao[uû]t|septembre|octobre|novembre|décembre)\b" replace="{{1er}} $1" />
}
