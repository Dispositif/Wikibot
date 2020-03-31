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
use App\Domain\WikiTemplateFactory;
use Exception;
use Throwable;

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

    public function __construct(PublisherAction $publisher)
    {
        $this->publisherAction = $publisher;
        $this->url = $publisher->getUrl();
        try {
            $this->article = $this->process();
        } catch (Exception $e) {
            dump($e);
            die;
        }
    }

    /**
     * @throws Exception
     */
    private function process(): ?ArticleOrLienBriseInterface
    {
        $mapper = PublisherMapperFactory::fromURL($this->url);
        if (!$mapper) {
            return null;
        }
        sleep(10);
        $arrayLD = [];
        try {
            $html = $this->publisherAction->getHTMLSource();
            $htmlData = $this->publisherAction->extractWebData($html);
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), '404') !== false) {
                dump('****** lien brisé !!!!');
                $lienBrise = WikiTemplateFactory::create('lien brisé');
                $lienBrise->hydrate(['url' => $this->url, 'titre' => 'Article de presse', 'brisé le' => date('d-m-Y')]);

                return $lienBrise; // ok
            }
            echo "*** Erreur ".$e->getMessage()."\n";

            return null;
        }

        if (empty($htmlData)) {
            echo "*** Pas de data Json-LD ou meta\n";

            return null;
        }
        $htmlData['url'] = $this->url;

        try {
            $articleData = $mapper->process($htmlData);
        } catch (Throwable $e) {
            echo sprintf(
                "SKIP : %s %s:%s \n",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );

            return null;
        }

        if (!empty($articleData) && !empty($articleData['titre'])) {
            $article = WikiTemplateFactory::create('article');
            $article->hydrate($articleData);
            if(!$article->hasParamValue('lire en ligne')) {
                $article->setParam('lire en ligne', $this->url);
            }
            return $article; // ok
        }

        return null;
    }

    public function getResult(): ?ArticleOrLienBriseInterface
    {
        return $this->article;
    }

}
