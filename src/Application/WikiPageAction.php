<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Enums\Language;
use App\Infrastructure\TagParser;
use DomainException;
use Exception;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\DataModel\Content;
use Mediawiki\DataModel\EditInfo;
use Mediawiki\DataModel\Page;
use Mediawiki\DataModel\PageIdentifier;
use Mediawiki\DataModel\Revision;
use Mediawiki\DataModel\Title;
use Throwable;

class WikiPageAction
{
    const SKIP_LANG_INDICATOR = 'fr'; // skip {{fr}} before template

    /**
     * @var Page
     */
    public $page; // public for debug

    public $wiki; // api ?

    /**
     * @var string
     */
    private $title;
    /**
     * Wiki namespace
     *
     * @var int
     */
    private $ns;
    /**
     * @var Revision|null
     */
    private $lastTextRevision;

    /**
     * WikiPageAction constructor.
     *
     * @param MediawikiFactory $wiki
     * @param string           $title
     *
     * @throws Exception
     */
    public function __construct(MediawikiFactory $wiki, string $title)
    {
        $this->wiki = $wiki;
        $this->title = $title;

        try {
            $this->page = $wiki->newPageGetter()->getFromTitle($title);
            $this->ns = $this->page->getPageIdentifier()->getTitle()->getNs();
        } catch (Throwable $e) {
            throw new Exception('Erreur construct WikiPageAction '.$e->getMessage().$e->getFile().$e->getLine());
        }
    }

    /**
     * Get wiki text from the page.
     *
     * @return string|null
     */
    public function getText(): ?string
    {
        $latest = $this->getLastRevision();
        $this->lastTextRevision = $latest;

        if (empty($latest)) {
            return null;
        }

        return ($latest) ? $latest->getContent()->getData() : null;
    }

    public function getNs(): ?int
    {
        return $this->ns;
    }

    public function getLastRevision(): ?Revision
    {
        // page doesn't exist
        if (empty($this->page->getRevisions()->getLatest())) {
            return null;
        }

        return $this->page->getRevisions()->getLatest();
    }

    public function getLastEditor(): ?string
    {
        // page doesn't exist
        if (empty($this->page->getRevisions()->getLatest())) {
            return null;
        }

        $latest = $this->page->getRevisions()->getLatest();

        return ($latest) ? $latest->getUser() : null;
    }

    /**
     * Check if a frwiki disambiguation page.
     *
     * @return bool
     */
    public function isPageHomonymie(): bool
    {
        return false !== stristr($this->getText() ?? '', '{{homonymie');
    }

    /**
     * Is it page with a redirection link ?
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        return !empty($this->getRedirect());
    }

    /**
     * Get redirection page title or null.
     *
     * @return string|null
     */
    public function getRedirect(): ?string
    {
        if ($this->getText() && preg_match('/^#REDIRECT(?:ION)? ?\[\[([^]]+)]]/i', $this->getText(), $matches)) {
            return (string)trim($matches[1]);
        }

        return null;
    }

    /**
     * Edit the page with new text.
     * Opti : EditInfo optional param ?
     *
     * @param string    $newText
     * @param EditInfo  $editInfo
     * @param bool|null $checkConflict
     *
     * @return bool
     */
    public function editPage(string $newText, EditInfo $editInfo, ?bool $checkConflict = false): bool
    {
        if ($checkConflict && $this->isPageEditedAfterGetText()) {
            throw new DomainException('Wiki Conflict : Page has been edited after getText()');
            // return false ?
        }

        $revision = $this->page->getPageIdentifier();

        $content = new Content($newText);
        $revision = new Revision($content, $revision);

        // TODO try/catch UsageExceptions badtoken
        return $this->wiki->newRevisionSaver()->save($revision, $editInfo);
    }

    /**
     * Check if wiki has been edited by someone since bot's getText().
     *
     * @return bool
     */
    private function isPageEditedAfterGetText(): bool
    {
        $updatedPage = $this->wiki->newPageGetter()->getFromTitle($this->title);
        $updatedLastRevision = $updatedPage->getRevisions()->getLatest();

        // Non-strict object equality comparison
        /** @noinspection PhpNonStrictObjectEqualityInspection */
        if ($updatedLastRevision && $updatedLastRevision == $this->lastTextRevision) {
            return false;
        }

        return true;
    }

    /**
     * Create a new page.
     *
     * @param string        $text
     * @param EditInfo|null $editInfo
     *
     * @return bool
     * @throws Exception
     */
    public function createPage(string $text, ?EditInfo $editInfo = null): bool
    {
        if (!empty($this->page->getRevisions()->getLatest())) {
            throw new Exception('That page already exists');
        }

        $newContent = new Content($text);
        // $identifier = $this->page->getPageIdentifier()
        $title = new Title($this->title);
        $identifier = new PageIdentifier($title);
        $revision = new Revision($newContent, $identifier);

        return $this->wiki->newRevisionSaver()->save($revision, $editInfo);
    }

    /**
     * @param string   $addText
     * @param EditInfo $editInfo
     *
     * @return bool success
     * @throws Exception
     */
    public function addToBottomOrCreatePage(string $addText, EditInfo $editInfo): bool
    {
        if (empty($this->page->getRevisions()->getLatest())) {
            return $this->createPage($addText, $editInfo);
        }

        return $this->addToBottomOfThePage($addText, $editInfo);
    }

    /**
     * Add text to the bottom of the article.
     *
     * @param string   $addText
     * @param EditInfo $editInfo
     *
     * @return bool success
     * @throws Exception
     */
    public function addToBottomOfThePage(string $addText, EditInfo $editInfo): bool
    {
        if (empty($this->page->getRevisions()->getLatest())) {
            throw new Exception('That page does not exist');
        }
        $oldText = $this->getText();
        $newText = $oldText."\n".$addText;

        return $this->editPage($newText, $editInfo);
    }

    /**
     * Add text to the top of the page.
     *
     * @param string   $addText
     * @param EditInfo $editInfo
     *
     * @return bool success
     * @throws Exception
     */
    public function addToTopOfThePage(string $addText, EditInfo $editInfo): bool
    {
        if (empty($this->page->getRevisions()->getLatest())) {
            throw new Exception('That page does not exist');
        }
        $oldText = $this->getText();
        $newText = $addText.$oldText;

        return $this->editPage($newText, $editInfo);
    }

    /**
     * todo Move to WikiTextUtil ?
     * Replace serialized template and manage {{en}} prefix.
     * Don't delete {{fr}} on frwiki.
     *
     * @param string $text       wikitext of the page
     * @param string $tplOrigin  template text to replace
     * @param string $tplReplace new template text
     *
     * @return string|null
     */
    public static function replaceTemplateInText(string $text, string $tplOrigin, string $tplReplace): string
    {
        // "{{en}} {{zh}} {{ouvrage...}}"
        // todo test U
        if (preg_match_all(
            '#(?<langTemp>{{[a-z][a-z]}} ?{{[a-z][a-z]}}) ?'.preg_quote($tplOrigin, '#').'#i',
            $text,
            $matches
        )
        ) {
            // Skip double lang prefix (like in "{{fr}} {{en}} {template}")
            echo 'SKIP ! double lang prefix !';

            return $text;
        }

        // hack // todo: autres patterns {{en}} ?
        // OK : {{en}} \n {{ouvrage}}
        if (preg_match_all(
                "#(?<langTemp>{{(?<lang>[a-z][a-z])}} *\n?)?".preg_quote($tplOrigin, '#').'#i',
                $text,
                $matches
            ) > 0
        ) {
            foreach ($matches[0] as $num => $mention) {
                $lang = $matches['lang'][$num] ?? '';
                if (!empty($lang)) {
                    $lang = Language::all2wiki($lang);
                }

                // detect inconsistency between lang indicator and lang param
                // example : {{en}} {{template|lang=ru}}
                if (!empty($lang) && self::SKIP_LANG_INDICATOR !== $lang
                    && preg_match('#langue *=#', $tplReplace)
                    && !preg_match('#langue *= ?'.$lang.'#i', $tplReplace)
                    && !preg_match('#\| ?langue *= ?\n?\|#', $tplReplace)
                ) {
                    echo sprintf(
                        'prefix %s incompatible avec langue de %s',
                        $matches['langTemp'][$num],
                        $tplReplace
                    );

                    // skip all the replacements of that template
                    return $text; // return null ?
                }

                // FIX dirty juil 2020 : {{en}} mais aucun param/value sur new template
                if (!empty($lang) && $lang !== 'fr' && !preg_match('#\| ?langue *=#', $tplReplace) > 0) {
                    // skip all the replacements of that template

                    return $text;
                }

                // FIX dirty : {{en}} mais langue= avec value non définie sur new template...
                if (!empty($lang) && preg_match('#\| ?(langue *=) ?\n? ?\|#', $tplReplace, $matchLangue) > 0) {
                    $previousTpl = $tplReplace;
                    $tplReplace = str_replace($matchLangue[1], 'langue='.$lang, $tplReplace);
                    //dump('origin', $tplOrigin);
                    $text = str_replace($previousTpl, $tplReplace, $text);
                }

                // don't delete {{fr}} before {template} on frwiki
                if (self::SKIP_LANG_INDICATOR === $lang) {
                    $text = str_replace($tplOrigin, $tplReplace, $text);

                    continue;
                }

                // replace {template} and {{lang}} {template}
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
