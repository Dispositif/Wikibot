<?php

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
        $this->page = $wiki->newPageGetter()->getFromTitle($title);
    }

    /**
     * @return string|null
     */
    public function getText(): ?string
    {
        return $this->page->getRevisions()->getLatest()->getContent()->getData();
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
     * Extract <ref> data from text.
     *
     * @param $text string
     *
     * @return array
     *
     * @throws Exception
     */
    public function extractRefFromText(string $text): ?array
    {
        $parser = new TagParser(); // todo ParserFactory
        $refs = $parser->importHtml($text)->getRefValues(); // []

        return (array) $refs;
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
