<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use App\Application\PublisherAction;
use App\Domain\Models\Wiki\ArticleOrLienBriseInterface;
use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\WikiTemplateFactory;
use Exception;

/**
 * news URL to {{Article}}.
 * Class ArticleFromURL
 *
 * @package App\Application\Examples
 */
class ArticleFromURL
{
    private $publisherAction;
    /**
     * @var ArticleOrLienBriseInterface|null
     */
    private $article;
    /**
     * @var string
     */
    private $url;

    public function __construct(string $url)
    {
        $this->url = $url;
        $this->publisherAction = new PublisherAction($url);
        try {
            $this->article = $this->process();
        } catch (Exception $e) {
            dump($e);
            die;
        }
    }

    public function getResult(): ?ArticleOrLienBriseInterface
    {
        return $this->article;
    }

    /**
     * @throws Exception
     */
    private function process(): ?ArticleOrLienBriseInterface
    {
        //$text = file_get_contents(__DIR__.'/../resources/tmp_news_figaro.html');

        $mapper = $this->selectMapper($this->url);
        if (!$mapper) {
            return null;
        }
        sleep(10);
        $arrayLD = [];
        try {
            $text = $this->publisherAction->getHTMLSource();
            $arrayLD = $this->publisherAction->extractLdJson($text);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                dump('****** lien brisé !!!!');
                $lienBrise = WikiTemplateFactory::create('lien brisé');
                $lienBrise->hydrate(['url' => $this->url, 'titre' => 'Article de presse', 'brisé le' => date('d-m-Y')]);

                return $lienBrise;
            }
            echo "*** Erreur ".$e->getMessage()."\n";

            return null;
        }

        if (empty($arrayLD)) {
            echo "*** Pas de donnée Json-LD\n";

            return null;
        }

        // TODO : select the mapper
        try {
            $articleData = $mapper->process($arrayLD);
        } catch (\Throwable $e) {
            echo sprintf(
                "SKIP : %s %s:%s \n",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );

            return null;
        }


        if (!empty($articleData)) {
            $article = new ArticleTemplate();
            $article->hydrate($articleData);

            return $article;
        }

        return null;
    }

    /**
     * todo refac factory/builder
     *
     * @param $url
     *
     * @return MapperInterface|null
     */
    private function selectMapper($url): ?MapperInterface
    {
        if (preg_match('#^https?://(www\.)?lemonde\.fr/[^ ]+$#i', $url)) {
            return new LeMondeMapper();
        }
        if (preg_match('#^https?://(www\.)?lefigaro\.fr/[^ ]+$#i', $url)) {
            return new FigaroMapper();
        }
        if (preg_match('#^https?://(www\.)?liberation\.fr/[^ ]+$#i', $url)) {
            return new LiberationMapper();
        }


        return null;
    }
}
