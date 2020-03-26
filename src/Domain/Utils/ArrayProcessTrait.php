<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

trait ArrayProcessTrait
{
    /**
     * Set all array keys to lower case.
     *
     * @param array $array
     *
     * @return array
     */
    private static function arrayKeysToLower(array $array): array
    {
        $res = [];
        foreach ($array as $key => $val) {
            $res[mb_strtolower($key)] = $val;
        }

        return $res;
    }

    /**
     * Delete keys with empty string value "".
     *
     * @param array $myArray
     *
     * @return array
     */
    public function deleteEmptyValueArray(array $myArray): array
    {
        return array_filter(
            $myArray,
            function ($value) {
                return !is_null($value) && '' !== $value;
            }
        );
    }

    /**
     * Generate the complete list of parameters from the fantasy order list
     * with additionnal parameters inserted in the place of the cleanOrder list.
     * Génère la liste complète de paramètres possibles d'après l'ordre
     * fantaisiste (humain) et l'ordre officiel (documentation).
     *
     * @param array $fantasyOrder
     * @param array $cleanOrder
     *
     * @return array
     */
    public function completeFantasyOrder(array $fantasyOrder, array $cleanOrder): array
    {
        $lastFantasy = null;
        $firstParameters = [];
        $before = [];

        // Fait lien entre param et sa place par rapport à l'ordre fantaisiste
        foreach ($cleanOrder as $param) {
            if (in_array($param, $fantasyOrder)) {
                $lastFantasy = $param;
                continue;
            }
            if (is_null($lastFantasy) && !in_array($param, $fantasyOrder)) {
                $firstParameters[] = $param;
                continue;
            }
            $before[$param] = $lastFantasy;
        }

        $result = $firstParameters;
        foreach ($fantasyOrder as $param) {
            $result[] = $param;
            // Des paramètres additionnels sont placés après ?
            if (in_array($param, $before)) {
                $result = array_merge($result, array_keys($before, $param));
            }
        }

        return $result;
    }
}
