<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use App\Domain\Utils\ArrayProcessTrait;
use App\Domain\Utils\TextUtil;
use Psr\Log\LoggerInterface;

/**
 * Generic mapper for press/revue article on web.
 * Using JSON-LD and meta tags to obtain {article} data.
 * Generic mapper for web pages URL to wiki-template references.
 * Converting to {article}, {lien web} or {lien brisé}
 * Using JSON-LD, Open Graph and Dublin Core meta extracted from HTML.
 */
class ExternMapper implements MapperInterface
{
    use ArrayProcessTrait, ExternConverterTrait;

    public const TITLE_TO_VERIFY_COMMENT = '<!-- Vérifiez ce titre -->';

    /** @var LoggerInterface */
    private $log;
    /** @var array */
    private $options = [];
    /** @var bool */
    private $titleFromHtmlState = false;

    public function __construct(LoggerInterface $log, ?array $options = [])
    {
        $this->log = $log;
        $this->options = $options;
    }

    public function process($data): array
    {
        $parsedData = $this->processMapping($data);

        return ($parsedData === []) ? [] : $this->postProcess($parsedData);
    }

    protected function processMapping($data): array
    {
        $mapJson = [];
        $mapMeta = [];
        $this->titleFromHtmlState = false;

        if (!empty($data['JSON-LD'])) {
            $mapJson = $this->processJsonLDMapping($data['JSON-LD']);
        }
        if (!empty($data['meta'])) {
            $openGraphMapper = new OpenGraphMapper($this->options); // todo inject/extract to reduce instanciationS ?
            $mapMeta = $openGraphMapper->process($data['meta']);
            $this->titleFromHtmlState = $openGraphMapper->isTitleFromHtmlState();
        }

        // langue absente JSON-LD mais array_merge risqué (doublon)
        if ($mapJson !== []) {
            if (!isset($mapJson['langue']) && isset($mapMeta['langue'])) {
                $mapJson['langue'] = $mapMeta['langue'];
                $mapJson['DATA-TYPE'] = 'JSON-LD+META';
            }
            // récupère "accès url" de OpenGraph (prévaut sur JSON:'isAccessibleForFree'
            if (isset($mapMeta['accès url'])) {
                $mapJson['accès url'] = $mapMeta['accès url'];
                $mapJson['DATA-TYPE'] = 'JSON-LD+META';
            }

            return $mapJson;
        }

        return $mapMeta;
    }

    /**
     * todo move to mapper ?
     */
    private function processJsonLDMapping(array $LDdata): array
    {
        if ($this->checkJSONLD($LDdata)) {
            return (new JsonLDMapper())->process($LDdata);
        }
        // gestion des multiples objets comme Figaro
        foreach ($LDdata as $dat) {
            if (is_array($dat) && $this->checkJSONLD($dat)) {
                return (new JsonLDMapper())->process($dat);
            }
        }

        return [];
    }

    protected function checkJSONLD(array $jsonLD): bool
    {
        return isset($jsonLD['headline']) && isset($jsonLD['@type']);
    }

    /**
     * Data sanitization.
     * todo Config parameter for post-process
     */
    protected function postProcess(array $data): array
    {
        $this->log->debug('call ExternMapper::postProcess()');
        $data = $this->deleteEmptyValueArray($data);
        if (isset($data['langue']) && 'fr' === $data['langue']) {
            unset($data['langue']);
        }

        // Ça m'énerve ! Gallica met "vidéo" pour livre numérisé
        if (isset($data['site']) && $data['site'] === 'Gallica') {
            unset($data['format']);
        }
        if (isset($data['site']) && TextUtil::countAllCapsWords($data['site']) >= 3) {
            $this->log->debug('lowercase site name');
            $data['site'] = TextUtil::mb_ucfirst(mb_strtolower($data['site']));
        }

        // lowercase title if too many ALLCAPS words
        if (isset($data['titre']) && TextUtil::countAllCapsWords($data['titre']) >= 4) {
            $this->log->debug('lowercase title');
            $data['titre'] = TextUtil::mb_ucfirst(mb_strtolower($data['titre']));
        }

        // SEO : cut site name if too long if no domain.name and no wiki link
        if (isset($data['site']) && false === mb_strpos($data['site'], '.') && false === mb_strpos($data['site'], '[[')) {
            $data['site'] = TextUtil::cutTextOnSpace($data['site'], 40);
        }

        // title has 150 chars max, or is cut with "…" at the end
        if (isset($data['titre'])) {
            $data['titre'] = TextUtil::cutTextOnSpace($data['titre'], 150);
            $data['titre'] = $this->addVerifyCommentIfNecessary($data['titre']);
        }

        return $data;
    }

    /**
     * todo Créer un modèle {titre à vérifier} ?
     */
    private function addVerifyCommentIfNecessary(?string $title): ?string
    {
        if (
            !empty($title)
            && strlen($title) >= 30
            && true === $this->titleFromHtmlState
        ) {
            /** @noinspection PhpRedundantOptionalArgumentInspection */
            $title = TextUtil::cutTextOnSpace($title, 70);
            $title .= self::TITLE_TO_VERIFY_COMMENT;
        }

        return $title;
    }
}
