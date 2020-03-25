<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Models\Wiki\ArticleOrLienBriseInterface;
use App\Domain\Models\Wiki\LienBriseTemplate;
use App\Domain\Publisher\ArticleFromURL;
use App\Domain\Utils\WikiTextUtil;

/**
 * Class Ref2ArticleProcess
 *
 * @package App\Application
 */
class Ref2ArticleProcess
{
    /**
     * @var bool
     */
    private $warning = false;

    /**
     * Change tous les <ref>http://libe|lemonde|figaro</ref> en {article}.
     *
     * @param $text
     *
     * @return string|string[]
     * @throws \Exception
     */
    public function processText($text)
    {
        $refs = WikiTextUtil::extractAllRefs($text);
        if (empty($refs)) {
            return $text;
        }

        foreach ($refs as $ref) {
            $url = WikiTextUtil::stripFinalPoint(trim($ref[1]));
            $converter = new ArticleFromURL(new PublisherAction($url));
            $articleOrLienBrise = $converter->getResult();

            if (!$articleOrLienBrise instanceof ArticleOrLienBriseInterface) {
                continue;
            }
            if($articleOrLienBrise instanceof LienBriseTemplate){
                $this->warning = true;
            }

            $serial = $articleOrLienBrise->serialize(true);
            $text = $this->replaceRefInText($ref, $serial, $text);
        }

        return $text;
    }

    public function hasWarning():bool
    {
        return (bool)$this->warning;
    }

    private function replaceRefInText(array $ref, string $replace, string $text)
    {
        $replace .= '.'; // ending point
        $result = str_replace($ref[1], $replace, $ref[0]);
        echo "$result \n";

        return str_replace($ref[0], $result, $text);
    }

}
