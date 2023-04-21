<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Publisher\Traits;

/**
 * Open Access of web documents. See https://en.wikipedia.org/wiki/Open_access
 * On frwiki mapping "accès url" : libre, inscription, limité, payant/abonnement.
 * https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:Acc%C3%A8s_url
 */
trait OpenAccessTrait
{
    /**
     * no consistency on the mysterious values of DC.rights.
     * @var string[]
     */
    protected $DCopenValues = ['free', 'public domain', 'public', 'domaine public', 'available'];

    protected function convertURLaccess($data): ?string
    {
        // https://developers.facebook.com/docs/instant-articles/subscriptions/content-tiering/?locale=fr_FR
        if (isset($data['og:article:content_tier'])) {
            switch (strtolower($data['og:article:content_tier'])) {
                case 'free':
                    return 'libre';
                case 'locked':
                    return 'payant';
                case 'metered':
                    return 'limité';
            }
        }

        // NYT, Figaro
        if (isset($data['isAccessibleForFree'])) {
            return ($this->sameAsTrue($data['isAccessibleForFree'])) ? 'libre' : 'payant';
        }

        if ($this->isOpenFromDCrights($data) || $this->isOpenFromDCaccessRights($data)) {
            return 'libre';
        }

        return null;
    }

    protected function sameAsTrue($str = null): bool
    {
        if ($str === null) {
            return false;
        }
        if (is_bool($str)) {
            return $str;
        }
        $str = strtolower($str);
        return in_array($str, ['true', '1', 'yes', 'oui', 'ok']);
    }

    /**
     * TODO : https://terms.tdwg.org/wiki/dcterms:accessRights
     * Information about who access the resource or an indication of its security status."
     * Values are a mystery...
     */
    protected function isOpenFromDCrights(array $data): bool
    {
        if (
            isset($data['DC.rights'])
            && in_array(strtolower($data['DC.rights']), $this->DCopenValues)
        ) {
            return true;
        }

        return false;
    }

    /**
     * TODO : https://terms.tdwg.org/wiki/dcterms:accessRights
     * Information about who access the resource or an indication of its security status."
     * Values are a mystery...
     */
    protected function isOpenFromDCaccessRights(array $data): bool
    {
        if (
            isset($data['DC.accessRights'])
            && in_array(strtolower($data['DC.accessRights']), $this->DCopenValues)
        ) {
            return true;
        }

        return false;
    }
}