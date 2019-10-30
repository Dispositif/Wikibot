<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Domain;

/**
 * todo Legacy to refac
 * Class PredictNameFirstName
 */
class PredictNameFirstName
{
    /**
     * @var CorpusInterface|null
     */
    private $corpusAdapter;

    private $unknownCorpusName = 'corpus_unknow_firstname'; // temp refac

    private $firstnameCorpusName = 'firstname'; // temp refac

    public function __construct(?CorpusInterface $corpus = null)
    {
        $this->corpusAdapter = $corpus;
    }

    /**
     * Check if the name is already inside the corpus of firstnames.
     *
     * @param string $firstname
     * @param bool   $logInCorpus
     *
     * @return bool
     */
    public function checkFirstname(string $firstname, bool $logInCorpus = false): bool
    {
        if (!$this->corpusAdapter) {
            return false;
        }

        $sanitizedName = mb_strtolower($firstname);
        if (strlen(trim($firstname)) >= 2
            && $this->corpusAdapter->inCorpus($sanitizedName, $this->firstnameCorpusName)
        ) {
            return true;
        }

        // add the name to a corpus
        if ($this->corpusAdapter && $logInCorpus) {
            $this->corpusAdapter->addNewElementToCorpus($this->unknownCorpusName, $sanitizedName);
        }

        return false;
    }

    /**
     * todo Refactor with new typoPatternFromAuthor() :)
     * todo Legacy.
     * Determine name and firstname from a string where both are mixed or abbreviated
     * Prediction from typo pattern, statistical analysis and list of famous firstnames.
     *
     * @param string $author
     *
     * @return array
     */
    public function predictNameFirstName(string $author): array
    {
        // multiple authors // todo? explode authors
        if (PredictAuthors::hasManyAuthors($author)) {
            return ['fail' => '2+ authors in string'];
        }

        // ALLUPPER, FIRSTUPPER, ALLLOWER, MIXED, INITIAL, ALLNUMBER, WITHNUMBER, DASHNUMBER, URL, ITALIC, BIBABREV,
        // AND, COMMA, PUNCTUATION
        $tokenizer = new TypoTokenizer();
        $typoPattern = $tokenizer->typoPatternFromAuthor($author)['pattern'];
        $tokenAuthor = preg_split('#[ ]+#', $author);

        if ('FIRSTUPPER FIRSTUPPER' === $typoPattern && !empty($tokenAuthor[1])) {
            // Paul Durand
            if ($this->checkFirstname($tokenAuthor[0], true) && !$this->checkFirstname($tokenAuthor[1])) {
                return ['firstname' => $tokenAuthor[0], 'name' => $tokenAuthor[1]];
            }
            // Durand Paul
            if ($this->checkFirstname($tokenAuthor[1]) && !$this->checkFirstname($tokenAuthor[0])) {
                return ['firstname' => $tokenAuthor[1], 'name' => $tokenAuthor[0]];
            }

            // Pierre Paul
            if ($this->checkFirstname($tokenAuthor[1]) && $this->checkFirstname($tokenAuthor[0])) {
                return ['fail' => 'both names in the firstnames corpus'];
            }

            return ['fail' => 'firstname not in corpus'];
        }

        // Jean-Pierre Durand
        if ('MIXED FIRSTUPPER' === $typoPattern && !empty($tokenAuthor[0]) && !empty($tokenAuthor[1])) {
            // Jean-Pierre Durand
            if ($this->checkFirstname($tokenAuthor[0], true) && !$this->checkFirstname($tokenAuthor[1])) {
                return ['firstname' => $tokenAuthor[0], 'name' => $tokenAuthor[1]];
            }
            // Ducroz-Lacroix Pierre
            if ($this->checkFirstname($tokenAuthor[1]) && !$this->checkFirstname($tokenAuthor[0])) {
                return ['firstname' => $tokenAuthor[1], 'name' => $tokenAuthor[0]];
            }
            // Luc-Zorglub Durand
            $pos = mb_strpos($tokenAuthor[0], '-');
            $firstnamePart = mb_substr($tokenAuthor[0], 0, $pos);
            if ($pos > 0 && $this->checkFirstname($firstnamePart)) {
                return ['firstname' => $tokenAuthor[0], 'name' => $tokenAuthor[1]];
            }

            return ['fail' => 'firstname MIXED not in corpus'];
        }

        // A. Durand
        if ('INITIAL FIRSTUPPER' === $typoPattern && !empty($tokenAuthor[0]) && !empty($tokenAuthor[1])) {
            // get last "." position (compatible with "A. B. Durand")
            $pos = mb_strrpos($author, '.');

            return [
                'firstname' => substr($author, 0, $pos + 1),
                'name' => trim(substr($author, $pos + 1)),
            ];
        }

        // Durand, P.
        if ('FIRSTUPPER COMMA INITIAL' === $typoPattern && !empty($tokenAuthor[0]) && !empty($tokenAuthor[1])) {
            $name = trim(str_replace(',', '', $tokenAuthor[0]));

            return ['firstname' => $tokenAuthor[1], 'name' => $name];
        }

        return [
            'fail' => 'unknown typo pattern',
            'pattern' => $typoPattern,
        ];
    }
}
