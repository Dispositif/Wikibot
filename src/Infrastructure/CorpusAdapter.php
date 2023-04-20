<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\InfrastructurePorts\CorpusInterface;
use DomainException;
use Exception;

/**
 * @unused
 * Dirty todo refac with FileManager and league/flysystem
 * todo : deleteElementFromCorpus, setCorpusFromFilename ???
 * Class CorpusAdapter.
 */
class CorpusAdapter extends FileManager implements CorpusInterface
{
    private $storage = [];

    /**
     * ugly memory // todo refac Generator.
     */
    public function inCorpus(string $element, string $corpusName): bool
    {
        // corpus as array variable
        if (isset($this->storage[$corpusName])) {
            $corpData = $this->storage[$corpusName];
            return is_array($corpData) && in_array($element, $corpData);
        }

        // corpus as text
        $filename = $this->getCorpusFilename($corpusName);

        return $this->isStringInCSV($filename, $element);
    }

    private function getCorpusFilename(string $corpusName): string
    {
        // Corpus as text file
        if ('firstname' === $corpusName) {
            return __DIR__.'/../Domain/resources/corpus_firstname.txt';
        }
        if ('all-titles' === $corpusName) {
            return __DIR__.'/frwiki-latest-all-titles-in-ns0.txt';
        }

        throw new DomainException("corpus $corpusName not defined");
    }

    public function setCorpusInStorage(string $corpusName, array $arrayContent): void
    {
        $this->storage[$corpusName] = $arrayContent;
    }

    /**
     * dirty TODO.
     */
    public function addNewElementToCorpus(string $corpusName, string $element): bool
    {
        if (empty($element)) {
            return false;
        }

        //corpus as variable
        if (isset($this->storage[$corpusName])) {
            return $this->addNewElementToMemoryCorpus($corpusName, $element);
        }

        // else : corpus as file
        return $this->addNewElementToFileCorpus($corpusName, $element);
    }

    private function addNewElementToFileCorpus(string $corpusName, string $element): bool
    {
        if (empty($element)) {
            return false;
        }

        // strip "/"
        $sanitizeCorpusName = preg_replace('#[^0-9a-z_]#i', '', $corpusName);
        $filename = __DIR__.'/../Domain/resources/'.$sanitizeCorpusName.'.txt';

        // hack: create file or not ?
        if (!file_exists($filename)) {
            $newFile = @fopen($filename, 'w');
            if ($newFile !== false) {
                fclose($newFile);
            }
            if (!file_exists($filename)) {
                throw new Exception('corpus filename does not exist'.$filename);
            }
        }

        // check if the element already in the corpus
        if ($this->isStringInCSV($filename, $element)) {
            return false;
        }

        $write = file_put_contents(
            $filename,
            utf8_encode($element)."\n",
            FILE_APPEND | LOCK_EX
        );

        return (bool) $write;
    }

    private function addNewElementToMemoryCorpus(string $corpusName, string $element): bool
    {
        if (isset($this->storage[$corpusName]) && is_array($this->storage[$corpusName])) {
            if (!in_array($element, $this->storage[$corpusName])) {
                $this->storage[$corpusName][] = $element;
            }

            return true;
        }

        return false;
    }
}
