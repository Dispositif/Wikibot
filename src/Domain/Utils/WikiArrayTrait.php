<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */
declare(strict_types=1);

namespace App\Domain\Utils;

trait WikiArrayTrait
{
    /**
     * Legacy.
     * Generate the complete list of parameters from the fantasy order list
     * with additionnal parameters inserted in the place of the cleanOrder list.
     * Génère la liste complète de paramètres possibles d'après l'ordre
     * fantaisiste (humain) et l'ordre officiel (documentation).
     *
     *
     */
    public function completeFantasyOrder(array $fantasyOrder, array $cleanOrder): array
    {
        $lastFantasy = null;
        $firstParameters = [];
        $before = [];

        // relation between param name et its place in the fantasy order
        foreach ($cleanOrder as $param) {
            if (in_array($param, $fantasyOrder)) {
                $lastFantasy = $param;
                continue;
            }
            if (($lastFantasy === null) && !in_array($param, $fantasyOrder)) {
                $firstParameters[] = $param;
                continue;
            }
            $before[$param] = $lastFantasy;
        }

        $result = $firstParameters;
        foreach ($fantasyOrder as $param) {
            $result[] = $param;
            // additional parameters added in the end of the list
            if (in_array($param, $before)) {
                $result = array_merge($result, array_keys($before, $param));
            }
        }

        return $result;
    }
}