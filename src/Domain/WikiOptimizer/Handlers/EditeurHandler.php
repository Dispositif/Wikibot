<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OptiStatus;
use App\Domain\Utils\WikiTextUtil;
use App\Domain\WikiOptimizer\OuvrageOptimize;
use Exception;
use Psr\Log\LoggerInterface;
use Throwable;

class EditeurHandler implements OptimizeHandlerInterface
{
    /**
     * @var OuvrageTemplate
     */
    protected $ouvrage;
    /**
     * @var \App\Domain\OptiStatus
     */
    protected $optiStatus;
    /**
     * @var LoggerInterface
     */
    protected $log;
    /**
     * @var string|null
     */
    protected $articleTitle;

    public function __construct(OuvrageTemplate $ouvrage, OptiStatus $summary, ?string $articleTitle, LoggerInterface $log)
    {
        $this->ouvrage = $ouvrage;
        $this->optiStatus = $summary;
        $this->log = $log;
        $this->articleTitle = $articleTitle;
    }

    /**
     * todo : vérif lien rouge
     * todo 'lien éditeur' affiché 1x par page
     * opti : Suppression lien éditeur si c'est l'article de l'éditeur.
     * @throws Exception
     */
    public function handle()
    {
        $editeur = $this->ouvrage->getParam('éditeur');
        if (empty($editeur)) {
            return;
        }

        // FIX bug "GEO Art ([[Prisma Media]]) ; [[Le Monde]]"
        if (preg_match('#\[.*\[.*\[#', $editeur) > 0) {
            return;
        }
        // FIX bug "[[Fu|Bar]] bla" => [[Fu|Bar bla]]
        if (preg_match('#(.+\[\[|\]\].+)#', $editeur) > 0) {
            return;
        }

        // [[éditeur]]
        if (preg_match('#\[\[([^|]+)]]#', $editeur, $matches) > 0) {
            $editeurUrl = $matches[1];
        }
        // [[bla|éditeur]]
        if (preg_match('#\[\[([^]|]+)\|.+]]#', $editeur, $matches) > 0) {
            $editeurUrl = $matches[1];
        }

        // Todo : traitement/suppression des abréviations communes :
        // ['éd. de ', 'éd. du ', 'éd.', 'ed.', 'Éd. de ', 'Éd.', 'édit.', 'Édit.', '(éd.)', '(ed.)', 'Ltd.']

        $editeurStr = WikiTextUtil::unWikify($editeur);
        // On garde minuscule sur éditeur, pour nuance Éditeur/éditeur permettant de supprimer "éditeur"
        // ex: "éditions Milan" => "Milan"

        // Déconseillé : 'lien éditeur' (obsolete 2019)
        if ($this->ouvrage->hasParamValue('lien éditeur')) {
            if (empty($editeurUrl)) {
                $editeurUrl = $this->ouvrage->getParam('lien éditeur');
            }
            $this->ouvrage->unsetParam('lien éditeur');
        }

        // TODO check history
        if (empty($editeurUrl)) {
            $editeurUrl = $this->predictPublisherWikiTitle($editeurStr);
            if (!empty($editeurUrl) && $this->articleTitle !== $editeurUrl) {
                $this->optiStatus->addSummaryLog('+lien éditeur');
                $this->optiStatus->setNotCosmetic(true);
                $this->optiStatus->setMajor(true);
            }
        }

        $newEditeur = $editeurStr;
        if (!empty($editeurUrl)) {
            $newEditeur = WikiTextUtil::wikilink($editeurStr, $editeurUrl);
        }

        if ($newEditeur !== $editeur) {
            $this->ouvrage->setParam('éditeur', $newEditeur);
            $this->optiStatus->addSummaryLog('±éditeur');
            $this->optiStatus->setNotCosmetic(true);
        }
    }

    /**
     * todo move (cf. Article/Lien web optimizing)
     *
     * @param string $publisherName
     *
     * @return string|null
     */
    public function predictPublisherWikiTitle(string $publisherName): ?string
    {
        $data = [];
        try {
            $data = json_decode(
                file_get_contents(OuvrageOptimize::PUBLISHER_FRWIKI_FILENAME),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable $e) {
            $this->log->error('Catch EDITOR_TITLES_FILENAME import ' . $e->getMessage());
        }
        if (isset($data[$publisherName])) {
            return (string)urldecode($data[$publisherName]);
        }

        return null;
    }
}