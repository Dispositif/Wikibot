<?php


namespace App\Infrastructure;

use App\Domain\CorpusInterface;

/**
 * Dirty todo refac with FileManager and league/flysystem
 * Class CorpusAdapter
 */
class CorpusAdapter extends FileManager implements CorpusInterface
{
    public function getFirstnameCorpus(): ?array
    {
        // todo refac
        $firstnameCorpus = include __DIR__.'/../Domain/ressources/corpus_firstname.php';
        return $firstnameCorpus;
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
        $filename = __DIR__.'/../Domain/ressources/'.$corpusName.'.txt';
        if(empty($element) ) {
            return false;
        }

        // check if the element already in the corpus
        if(file_exists($filename)) {
            $data = file_get_contents($filename);
            if( preg_match('/^'.preg_quote($element).'$/m', $data) > 0 ) {
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
