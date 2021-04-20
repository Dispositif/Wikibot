<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Publisher;


use Exception;

class OpenGraphMapper implements MapperInterface
{
    use ExternConverterTrait;

    /**
     * Tenir compte du <title>bla</title> pour générer un {lien web} ?
     */
    protected $htmlTitleAllowed = true;

    protected $titleFromHtmlState = false;

    /**
     * @param array|null $options
     */
    public function __construct(?array $options = [])
    {
        if (!empty($options['htmlTitleAllowed']) && is_bool($options['htmlTitleAllowed'])) {
            $this->htmlTitleAllowed = $options['htmlTitleAllowed'];
        }
    }

    /**
     * todo pretty ALLCAP UGLY TITLE
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
        // Mode "pas de titre html"
        if (!$this->htmlTitleAllowed && !empty($meta['html-title'])) {
            unset($meta['html-title']);
        }

        // si usage <title> HTML
        if ($this->htmlTitleAllowed
            && empty($meta['og:title'])
            && empty($meta['twitter:title'])
            && empty($meta['DC.title'])
            && !empty($meta['html-title'])
        ) {
            $this->titleFromHtmlState = true;
        }

        return [
            'DATA-TYPE' => 'Open Graph/Dublin Core',
            'DATA-ARTICLE' => $this->isAnArticle($meta['og:type'] ?? ''),
            'site' => $this->clean($meta['og:site_name'] ?? null),
            'titre' => $this->clean(
                $meta['og:title'] ?? $meta['twitter:title'] ?? $meta['DC.title'] ?? $meta['html-title'] ?? null
            ),
            'url' => $meta['og:url'] ?? $meta['URL'] ?? $meta['html-url'] ?? null,
            'langue' => $this->convertLangue(
                $meta['og:locale'] ?? $meta['DC.language'] ?? $meta['citation_language'] ?? $meta['lang'] ??
                $meta['language'] ??
                $meta['content-language'] ?? $meta['Content-Language'] ?? $meta['html-lang'] ?? null
            ),
            'consulté le' => date('d-m-Y'),
            'auteur' => $this->cleanAuthor(
                $meta['og:article:author'] ??
                $meta['article:author'] ?? $meta['citation_author'] ?? $meta['article:author_name'] ?? null
            ),
            'format' => $this->convertOGtype2format($meta['og:type'] ?? null),
            'date' => $this->convertDate(
                $meta['og:article:published_time'] ?? $meta['article:published_time'] ??
                $meta['DC.date'] ?? $meta['citation_date'] ?? $meta['citation_publication_date'] ?? null
            ),
            'accès url' => $this->convertURLaccess($meta),

            // DUBLIN CORE ONLY
            'périodique' => $this->clean($meta['DC.isPartOf'] ?? $meta['citation_journal_title'] ?? null),
            'et al.' => $this->authorsEtAl(
                $meta['citation_authors'] ?? $meta['DC.Contributor'] ?? null,
                true
            ),
            'auteur1' => $this->wikifyPressAgency(
                $this->cleanAuthor(
                    $this->authorsEtAl($meta['citation_authors'] ?? $meta['DC.Contributor'] ?? $meta['Author'] ?? null)
                )
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

            // WIKI
            //'note' => $this->addNote(),
        ];
    }
}
