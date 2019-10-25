<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\TagParser;
use Exception;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\DataModel\Content;
use Mediawiki\DataModel\EditInfo;
use Mediawiki\DataModel\Page;
use Mediawiki\DataModel\Revision;

class WikiPageAction
{
    /**
     * @var Page
     */
    public $page; // public for debug

    public $wiki; // api ?

    /**
     * WikiPageAction constructor.
     *
     * @param MediawikiFactory $wiki
     * @param string           $title
     */
    public function __construct(MediawikiFactory $wiki, string $title)
    {
        $this->wiki = $wiki;
        try{
            $this->page = $wiki->newPageGetter()->getFromTitle($title);
        }catch (\Throwable $e){
            dump($e);
        }

    }

    /**
     * Get wiki text from the page
     *
     * @return string|null
     */
    public function getText(): ?string
    {
        $latest = $this->page->getRevisions()->getLatest();

        return ($latest) ? $latest->getContent()->getData() : null;
    }

    /**
     * Check if a frwiki disambiguation page.
     *
     * @return bool
     */
    public function isPageHomonymie(): bool
    {
        return stristr($this->getText(), '{{homonymie');
    }

    /**
     * Get redirection page title or null.
     *
     * @return string|null
     */
    public function getRedirect(): ?string
    {
        if (preg_match('/^#REDIRECT(?:ION)? ?\[\[([^]]+)]]/i', $this->getText(), $matches)) {
            return (string)trim($matches[1]);
        }

        return null;
    }

    /**
     * Edit the page with new text.
     * Opti : EditInfo optional param ?
     *
     * @param string   $newText
     * @param EditInfo $editInfo
     *
     * @return bool
     */
    public function editPage(string $newText, EditInfo $editInfo): bool
    {
        $revision = $this->page->getPageIdentifier();

        $content = new Content($newText);
        $revision = new Revision($content, $revision);
        $success = $this->wiki->newRevisionSaver()->save($revision, $editInfo);

        return $success;
    }

    /**
     * todo Move to WikiTextUtil ?
     * Hack to replace template serialized and manage {{en}}.
     *
     * @param string $text
     * @param string $tplOrigin
     * @param string $tplReplace
     *
     * @return string|null
     */
    public static function replaceTemplateInText(string $text, string $tplOrigin, string $tplReplace): ?string
    {
        // hack // todo: autres patterns {{en}} ?
        if (preg_match_all('#(?<langTemp>{{(?<lang>[a-z][a-z])}} *)?'.preg_quote($tplOrigin, '#').'#i', $text, $matches)
            > 0
        ) {
            foreach ($matches[0] as $num => $mention) {
                $lang = $matches['lang'][$num] ?? '';
                if (!empty($lang) && !preg_match('#lang(ue)?='.$lang.'#i', $tplReplace)) {
                    echo sprintf(
                        'prefix %s incompatible avec langue de %s',
                        $matches['langTemp'][$num],
                        $tplReplace
                    );

                    return null;
                }
                $text = str_replace($mention, $tplReplace, $text);
                $text = str_replace(
                    $matches['langTemp'][$num].$tplReplace,
                    $tplReplace,
                    $text
                ); // si 1er replace global sans
                // {{en}}
            }
        }

        return $text;
    }

    /**
     * Extract <ref> data from text.
     *
     * @param $text string
     *
     * @return array
     * @throws Exception
     */
    public function extractRefFromText(string $text): ?array
    {
        $parser = new TagParser(); // todo ParserFactory
        $refs = $parser->importHtml($text)->getRefValues(); // []

        return (array)$refs;
    }

    /**
     * TODO $url parameter
     * TODO? refactor with : parse_str() + parse_url($url, PHP_URL_QUERY)
     * check if any ref contains a targeted website/URL.
     *
     * @param array $refs
     *
     * @return array
     */
    public function filterRefByURL(array $refs): array
    {
        $validRef = [];
        foreach ($refs as $ref) {
            if (preg_match(
                    '#(?<url>https?://(?:www\.)?lemonde\.fr/[^ \]]+)#i',
                    $ref,
                    $matches
                ) > 0
            ) {
                $validRef[] = ['url' => $matches['url'], 'raw' => $ref];
            }
        }

        return $validRef;
    }
}
