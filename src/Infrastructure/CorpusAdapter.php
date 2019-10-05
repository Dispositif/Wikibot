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
        $corpData = $this->getCorpusContent($corpusName);

        // corpus as text
        if ( !is_null($corpData) && is_string($corpData)
            && preg_match('/^'.preg_quote($element).'$/m', $corpData) > 0) {
            return true;
        }
        // corpus as array variable
        if( is_array($corpData) && in_array($element, $corpData)) {
            return true;
        }

        return false;
    }

    private function getCorpusContent(string $name)
    {
        // Corpus as array variable
        if( isset($this->storage[$name])) {
            return (array) $this->storage[$name];
        }
        // Corpus as text file
        if ($name === 'firstname') {
            return (string) file_get_contents(__DIR__.'/../Domain/resources/corpus_firstname.txt');
        }
        throw new \DomainException("corpus $name not defined");
    }

    public function setCorpusInStorage(string $corpusName, array $arrayContent):void
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
