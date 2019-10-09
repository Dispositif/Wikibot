<?php

declare(strict_types=1);

namespace App\Infrastructure;

class FileManager
{
    /**
     * Search string in a simple list CSV.
     *
     * @param string $filename
     * @param string $str
     *
     * @return bool
     */
    public function isStringInCSV(string $filename, string $str): bool
    {
        $f = fopen($filename, 'r');
        $result = false;
        while ($row = fgetcsv($f)) {
            if ($row[0] === $str) {
                return true;
            }
        }
        fclose($f);

        return false;
    }

    //    /**
    //     * function log TODO legacy
    //     *
    //     * @param string $file
    //     * @param null   $content
    //     */
    //    public function savelog($file = 'global_log', $content = null)
    //    {
    //        if ($content == false) {
    //            return;
    //        }
    //
    //        $file = './temp/'.$file.'.txt';
    //
    //        $filetime = @filemtime($file);
    //        if ($filetime < (time() - 3600 * 24 * 25) OR $filetime == false) {
    //            $fp = @fopen($file, 'w+');
    //        } // efface fichier
    //        else {
    //            $fp = @fopen($file, 'a+');
    //        } // fin de fichier
    //
    //        $content = "\r".date('d\-m\-Y H\:i\:s').' '.utf8_decode($content);
    //        @fputs($fp, $content);
    //        @fclose($fp);
    //    }
    //
    //    /**
    //     * todo legacy
    //     *  liste de données (plutôt que SQL) Forme # nom avec # nombre
    //     * // classement par ordre d'occurence
    //     * // avec $mode='alpha' classement alphabétique
    //     * // avec $mode='sub' pour soustraire 1
    //     *
    //     * @param      $nomliste
    //     * @param      $titre
    //     * @param null $mode
    //     */
    //    public function add_to_numliste($nomliste, $titre, $mode = null)
    //    {
    //        $filename = 'temp/LISTE_'.$nomliste.'.txt';
    //
    //        $res = '';
    //        $tableau = [];
    //        $matches = [];
    //        $lignes = explode("\n", file_get_contents($filename));
    //        foreach ($lignes AS $key => $ligne) {
    //            preg_match_all('#^([0-9]+) (.*)$#', $ligne, $matches, PREG_PATTERN_ORDER);
    //            $tableau[$matches[2][0]] = $matches[1][0];
    //        }
    //        if ($mode == 'sub') { // bug
    //            if ($tableau[$titre] > 0) {
    //                $tableau[$titre] = ($tableau[$titre] - 1);
    //            }else {
    //                $tableau[$titre] = '';
    //            }
    //        }else {
    //            if ($tableau[$titre]) {
    //                $tableau[$titre] = ($tableau[$titre] + 1);
    //            }else {
    //                $tableau[$titre] = 1;
    //            }
    //        }
    //        deleteEmptyArray5($tableau);
    //        arsort($tableau); // par num descendant
    //        if ($mode == 'alpha') {
    //            ksort($tableau); // par titre alphabétiq
    //        }
    //        foreach ($tableau AS $key => $value) {
    //            $res .= $value.' '.$key."\n";
    //        }
    //        //echo $res;
    //        file_put_contents($filename, $res);
    //    }
}
