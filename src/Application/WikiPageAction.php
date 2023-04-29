<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Enums\Language;
use App\Infrastructure\Mediawiki\ExtendedMediawikiFactory;
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
    public const SKIP_LANG_INDICATOR = 'fr'; // skip {{fr}} before template

    /**
     * @var Page
     */
    public $page; // public for debug

    public $wiki;
    /**
     * Wiki namespace
     */
    private ?int $ns = null;
    private ?Revision $lastTextRevision = null;

    /**
     * @throws Exception
     */
    public function __construct(
        MediawikiFactory        $wiki, // api ?
        private readonly string $title
    )
    {
        $e = null;
        $this->wiki = $wiki;

        try {
            $this->page = $wiki->newPageGetter()->getFromTitle($title);
            $this->ns = $this->page->getPageIdentifier()->getTitle()->getNs();
        } catch (Throwable $e) {
            throw new Exception('Erreur construct WikiPageAction ' . $e->getMessage() . $e->getFile() . $e->getLine(), $e->getCode(), $e);
        }
    }

    public function getNs(): ?int
    {
        return $this->ns;
    }

    /**
     * todo Move to WikiTextUtil ?
     * Replace serialized template and manage {{en}} prefix.
     * Don't delete {{fr}} on frwiki.
     */
    public static function replaceTemplateInText(string $text, string $tplOrigin, string $tplReplace): ?string
    {
        // "{{en}} {{zh}} {{ouvrage...}}"
        // todo test U
        if (preg_match_all(
            '#(?<langTemp>{{[a-z][a-z]}} ?{{[a-z][a-z]}}) ?' . preg_quote($tplOrigin, '#') . '#i',
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
                "#(?<langTemp>{{(?<lang>[a-z][a-z])}} *\n?)?" . preg_quote($tplOrigin, '#') . '#i',
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
                    && !preg_match('#langue *= ?' . $lang . '#i', $tplReplace)
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
                    $tplReplace = str_replace($matchLangue[1], 'langue=' . $lang, $tplReplace);
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
                    $matches['langTemp'][$num] . $tplReplace,
                    $tplReplace,
                    $text
                ); // si 1er replace global sans
                // {{en}}
            }
        }

        return $text;
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
     */
    public function isPageHomonymie(): bool
    {
        return false !== stristr($this->getText() ?? '', '{{homonymie');
    }

    /**
     * Get wiki text from the page.
     */
    public function getText(): ?string
    {
        $latest = $this->getLastRevision();
        $this->lastTextRevision = $latest;

        if (empty($latest)) {
            return null;
        }

        return $latest->getContent()->getData();
    }

    public function getLastRevision(): ?Revision
    {
        // page doesn't exist
        if (empty($this->page->getRevisions()->getLatest())) {
            return null;
        }

        return $this->page->getRevisions()->getLatest();
    }

    /**
     * Is it page with a redirection link ?
     */
    public function isRedirect(): bool
    {
        return !empty($this->getRedirect());
    }

    /**
     * Get redirection page title or null.
     */
    public function getRedirect(): ?string
    {
        if ($this->getText() && preg_match('/^#REDIRECT(?:ION)? ?\[\[([^]]+)]]/i', $this->getText(), $matches)) {
            return (string)trim($matches[1]);
        }

        return null;
    }

    /**
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
     * Create a new page.
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
     * Add text to the bottom of the article.
     * @return bool success
     * @throws Exception
     */
    public function addToBottomOfThePage(string $addText, EditInfo $editInfo): bool
    {
        if (empty($this->page->getRevisions()->getLatest())) {
            throw new Exception('That page does not exist');
        }
        $oldText = $this->getText();
        $newText = $oldText . "\n" . $addText;

        return $this->editPage($newText, $editInfo);
    }

    /**
     * Edit the page with new text.
     * Opti : EditInfo optional param ?
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
        // captchaId=12345&captchaWord=MediaWikiIsCool
        $revisionSaver = ExtendedMediawikiFactory::newRevisionSaverExtended();
        $result = $revisionSaver->save($revision, $editInfo);
        if (false === $result) {
            echo "Error editPage\n";
            print_r($revisionSaver->getErrors());
        }

        return $result;
    }

    /**
     * Check if wiki has been edited by someone since bot's getText().
     */
    private function isPageEditedAfterGetText(): bool
    {
        $updatedPage = $this->wiki->newPageGetter()->getFromTitle($this->title);
        $updatedLastRevision = $updatedPage->getRevisions()->getLatest();
        // Non-strict object equality comparison
        /** @noinspection PhpNonStrictObjectEqualityInspection */
        return !($updatedLastRevision && $updatedLastRevision == $this->lastTextRevision);
    }

    /**
     * Add text to the top of the page.
     * @return bool success
     * @throws Exception
     */
    public function addToTopOfThePage(string $addText, EditInfo $editInfo): bool
    {
        if (empty($this->page->getRevisions()->getLatest())) {
            throw new Exception('That page does not exist');
        }
        $oldText = $this->getText();
        $newText = $addText . $oldText;

        return $this->editPage($newText, $editInfo);
    }

    /**
     * Extract <ref> data from text.
     * @throws Exception
     */
    public function extractRefFromText(string $text): ?array
    {
        $parser = new TagParser(); // todo ParserFactory
        $refs = $parser->importHtml($text)->getRefValues(); // []

        return $refs;
    }

    /**
     * TODO $url parameter
     * TODO? refactor with : parse_str() + parse_url($url, PHP_URL_QUERY)
     * check if any ref contains a targeted website/URL.
     */
    public function filterRefByURL(array $refs): array
    {
        $validRef = [];
        foreach ($refs as $ref) {
            if (preg_match(
                    '#(?<url>https?://(?:www\.)?lemonde\.fr/[^ \]]+)#i',
                    (string)$ref,
                    $matches
                ) > 0
            ) {
                $validRef[] = ['url' => $matches['url'], 'raw' => $ref];
            }
        }

        return $validRef;
    }
}
