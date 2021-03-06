<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Infrastructure;

use Mediawiki\Api\UsageException;

/**
 * List of wiki-pages titles.
 * Class PageList
 *
 * @package App\Infrastructure
 */
class PageList implements PageListInterface
{
    protected $titles;

    /**
     * PageList constructor.
     *
     * @param $titles
     */
    public function __construct(array $titles) { $this->titles = $titles; }

    public function getPageTitles(): array
    {
        return $this->titles;
    }

    public function count():int
    {
        return count($this->titles);
    }

    /**
     * @param $filename
     *
     * @return PageList
     */
    public static function FromFile(string $filename): PageList
    {
        $names = @file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $titles = [];
        if (!empty($names)) {
            foreach ($names as $name) {
                $title = trim($name);
                if (!empty($title)) {
                    $titles[] = $title;
                }
            }
        }

        return new PageList((array)$titles);
    }

    /**
     * @param string $categoryName
     *
     * @return PageList
     * @throws UsageException
     */
    public static function FromWikiCategory(string $categoryName): PageList
    {
        $wiki = ServiceFactory::wikiApi();
        $wikiPages = $wiki->newPageListGetter()->getPageListFromCategoryName('Catégorie:'.$categoryName);
        $wikiPages = $wikiPages->toArray();
        $titles = [];
        foreach ($wikiPages as $wikiPage) {
            $title = $wikiPage->getPageIdentifier()->getTitle()->getText();
            $title = str_replace('Talk:', 'Discussion:', $title); // todo refac
            $titles[] = $title;
        }
        // arsort($titles);

        return new PageList($titles);
    }
}
