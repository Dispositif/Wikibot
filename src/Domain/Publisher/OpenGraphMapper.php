<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Publisher;


use App\Domain\Publisher\Traits\AuthorConverterTrait;
use App\Domain\Publisher\Traits\MapperConverterTrait;
use App\Domain\Publisher\Traits\OpenAccessTrait;
use Exception;

/**
 * Parsing/mapping Open Graph and Dublin Core meta data, and HTML meta tags
 * Currently only used by ExternMapper (mixed with JSON-LD mapping) for ExternRefWorker.
 */
class OpenGraphMapper implements MapperInterface
{
    use MapperConverterTrait, AuthorConverterTrait, OpenAccessTrait;

    /**
     * Allowing use of HTML <title> or <h1> to predict web page title ?
     */
    protected $htmlTitleAllowed = true;

    protected $titleFromHtmlState = false;

    public function __construct(?array $options = [])
    {
        if (!empty($options['htmlTitleAllowed']) && is_bool($options['htmlTitleAllowed'])) {
            $this->htmlTitleAllowed = $options['htmlTitleAllowed'];
        }
    }

    /**
     * Mapping from Open Graph and Dublin Core meta tags
     * https://ogp.me/
     * https://www.dublincore.org/schemas/
     * todo extract DC ?
     *
     * @param array $meta
     *
     * @return array
     * @throws Exception
     */
    public function process($meta): array
    {
        $seoSanitizer = new SeoSanitizer();

        return [
            'DATA-TYPE' => 'Open Graph/Dublin Core',
            'DATA-ARTICLE' => $this->isAnArticle($meta['og:type'] ?? ''),
            'site' => $this->clean($meta['og:site_name'] ?? null),
            'titre' => $seoSanitizer->cleanSEOTitle(
                $meta['prettyDomainName'],
                $this->predictBestTitle($meta)
            ),
            'url' => $meta['og:url'] ?? $meta['URL'] ?? $meta['html-url'] ?? null,
            'langue' => $this->convertLangue(
                $meta['og:locale'] ?? $meta['DC.language'] ?? $meta['citation_language'] ?? $meta['lang'] ??
                $meta['language'] ??
                $meta['content-language'] ?? $meta['Content-Language'] ?? $meta['html-lang'] ?? null
            ),
            'consulté le' => date('d-m-Y'),
            'auteur' => $this->cleanAuthor($this->clean(
                $meta['og:article:author'] ??
                $meta['article:author'] ?? $meta['citation_author'] ?? $meta['article:author_name'] ?? null
            )),
            'format' => $this->convertOGtype2format($meta['og:type'] ?? null),
            'date' => $this->convertDate(
                $meta['og:article:published_time'] ?? $meta['article:published_time'] ??
                $meta['DC.date'] ?? $meta['citation_date'] ?? $meta['citation_publication_date'] ?? null
            ),
            'accès url' => $this->convertURLaccess($meta),

            // DUBLIN CORE ONLY
            'périodique' => $this->clean($meta['DC.isPartOf'] ?? $meta['citation_journal_title'] ?? null),
            'et al.' => $this->isAuthorsEtAl($meta['citation_authors'] ?? $meta['DC.Contributor'] ?? null)
                ? 'oui'
                : null,
            'auteur1' => $this->wikifyPressAgency(
                $this->cleanAuthor($this->clean(
                    $this->shrinkMultiAuthors($meta['citation_authors'] ?? $meta['DC.Contributor'] ?? $meta['Author'] ?? null)
                ))
            ),
            'volume' => $meta['citation_volume'] ?? null,
            'numéro' => $meta['citation_issue'] ?? null,
            'page' => $this->convertDCpage($meta),
            'doi' => $meta['citation_doi'] ?? $meta['DOI'] ?? null,
            'éditeur' => $meta['DC.publisher'] ?? $meta['dc.publisher'] ?? null, // Persée dégeulasse todo?
            'pmid' => $meta['citation_pmid'] ?? null,
            'issn' => $meta["citation_issn"] ?? null,
            'isbn' => $meta["citation_isbn"] ?? null,
            // "prism.eIssn" => "2262-7197"
        ];
    }

    public function isTitleFromHtmlState(): bool
    {
        return $this->titleFromHtmlState;
    }

    /**
     * Todo extraire cette logique to MapperConverterTrait or ExternPageTitlePredictor ?
     */
    private function predictBestTitle(array $meta): ?string
    {
        // Mode "pas de titre html"
        if (!$this->htmlTitleAllowed) {
            return $this->getBestTitleFromMetadata($meta);
        }

        if (null === $this->getBestTitleFromMetadata($meta)
            && !empty($meta['html-title'])
        ) {
            $this->titleFromHtmlState = true;
        }

        // Responsibility ?!! sanitize title here conflicts with ExternMapper:postprocess()
        return $this->chooseBestTitle(
            $this->getBestTitleFromMetadata($meta),
            $meta['html-title'],
            $meta['html-h1']
        );
    }

    /**
     * Choose page's title from OpenGrap or Dublin core.
     */
    private function getBestTitleFromMetadata(array $meta): ?string
    {
        if (!empty($meta['og:title'])) {
            return $meta['og:title'];
        }
        if (!empty($meta['twitter:title'])) {
            return $meta['twitter:title'];
        }
        if (!empty($meta['DC.title'])) {
            return $meta['DC.title'];
        }

        return null;
    }

    /**
     * Choose best title from meta-title, html-title and html-h1.
     * Title is sanitized in ExternMapper::postprocess()
     */
    public function chooseBestTitle(?string $metaTitle, ?string $htmlTitle, ?string $htmlH1): ?string
    {
        // clean all titles
        $metaTitle = $this->clean($metaTitle);
        $htmlTitle = $this->clean($htmlTitle);
        $htmlH1 = $this->clean($htmlH1);

        // check if htmlh1 included in htmltitle, if yes use htmlh1
        if (!empty($metaTitle)) {
            return $metaTitle;
        }
        if (!empty($htmlH1) && !empty($htmlTitle) && str_contains($htmlTitle, $htmlH1)) {
            return $htmlH1;
        }

        return $htmlTitle ?? $htmlH1 ?? null;
    }
}
