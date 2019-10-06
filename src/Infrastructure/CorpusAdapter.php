<?php


namespace App\Infrastructure;

use App\Domain\CorpusInterface;

/**
 * Dirty todo refac with FileManager and league/flysystem
 * todo : deleteElementFromCorpus, setCorpusFromFilename ???
 * Class CorpusAdapter
 */
class CorpusAdapter extends FileManager implements CorpusInterface
{
    private $storage = [];

    /**
     * ugly memory // todo refac Generator
     *
     * @param string $element
     * @param string $corpusName
     *
     * @return bool
     */
    public function inCorpus(string $element, string $corpusName): bool
    {
        // corpus as array variable
        if (isset($this->storage[$corpusName])) {
            $corpData = $this->storage[$corpusName];
            if (is_array($corpData) && in_array($element, $corpData)) {
                return true;
            }

            return false;
        }

        // corpus as text
        $filename = $this->getCorpusFilename($corpusName);

        return $this->isStringInCSV($filename, $element);
    }

    private function getCorpusFilename(string $corpusName)
    {
        // Corpus as text file
        if ($corpusName === 'firstname') {
            return __DIR__.'/../Domain/resources/corpus_firstname.txt';
        }
        if ($corpusName === 'all-titles') {
            return __DIR__.'/frwiki-latest-all-titles-in-ns0.txt';
        }
        throw new \DomainException("corpus $corpusName not defined");
    }

    public function setCorpusInStorage(string $corpusName, array $arrayContent): void
    {
        $this->storage[$corpusName] = $arrayContent;
    }

    /**
     * dirty TODO
     *
     * @param string $corpusName
     * @param string $element
     *
     * @return bool
     */
    public function addNewElementToCorpus(string $corpusName, string $element): bool
    {
        //corpus as variable
        if (isset($this->storage[$corpusName]) && is_array($this->storage[$corpusName])) {
            if (!in_array($element, $this->storage[$corpusName])) {
                $this->storage[$corpusName][] = $element;
            }

            return true;
        }

        // corpus as file
        $filename = __DIR__.'/../Domain/resources/'.$corpusName.'.txt';
        if (empty($element)) {
            return false;
        }

        // check if the element already in the corpus
        if (file_exists($filename)) {
            $data = file_get_contents($filename);
            if (preg_match('/^'.preg_quote($element).'$/m', $data) > 0) {
                return false;
            }
        }

        file_put_contents(
            $filename,
            utf8_encode($element)."\n",
            FILE_APPEND | LOCK_EX
        );

        return true;
    }

}
