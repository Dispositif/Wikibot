<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use App\Domain\Exceptions\ConfigException;
use App\Infrastructure\ServiceFactory;
use Exception;
use GuzzleHttp\Client;
use Mediawiki\DataModel\EditInfo;

/**
 * See also https://github.com/enterprisey/AAdminScore/blob/master/js/aadminscore.js
 * https://fr.wikipedia.org/wiki/Cat%C3%A9gorie:%C3%89lection_administrateur_en_cours
 * Class VoteAdmin
 */
class VoteAdmin
{
    const SUMMARY               = '/* Approbation :*/ 🗳️ 🕊';
    const FILENAME_PHRASES_POUR = __DIR__.'/resources/phrases_voteAdmin_pour.txt';
    const FILENAME_BLACKLIST    = __DIR__.'/resources/blacklist.txt';
    const MIN_VALUE_POUR        = 0.65;
    const MIN_COUNT_POUR        = 7;
    const MIN_ADMIN_SCORE       = 500;
    const BOURRAGE_DETECT_REGEX = '#\[\[(?:User|Utilisateur|Utilisatrice):(Irønie|CodexBot|ZiziBot)#i';

    /**
     * @var string
     */
    private $voteText;
    /**
     * @var string
     */
    private $votePage;
    /**
     * @var WikiPageAction
     */
    private $pageAction;
    private $pageText;
    /**
     * @var string
     */
    private $comment = '';

    public function __construct(string $AdminVotePage)
    {
        $this->votePage = $AdminVotePage;
        $this->process();
    }

    private function process()
    {
        if (!$this->checkBlacklist()) {
            return false;
        }

        if (!$this->checkPourContre()) {
            echo "check pour/contre => false";

            return false;
        }

        $adminScore = $this->getAdminScore();
        if ($adminScore && $adminScore < self::MIN_ADMIN_SCORE) {
            echo "Admin score => false";

            return false;
        }

        $this->comment .= ' – adminScore: '.$adminScore;

        $this->voteText = sprintf("%s ~~~~\n", $this->selectVoteText());

        dump($this->comment);
        dump($this->voteText);
        sleep(5);

        $insertResult = $this->generateVoteInsertionText();
        if (empty($insertResult)) {
            echo "insertResult vide\n";

            return false;
        }

        dump($insertResult);

        sleep(20);

        return $this->editVote($insertResult);
    }

    private function selectVoteText(): string
    {
        $sentences = file(self::FILENAME_PHRASES_POUR, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$sentences) {
            throw new ConfigException('Pas de phrases disponibles');
        }

        return (string)trim($sentences[array_rand($sentences)]);
    }

    private function generateVoteInsertionText(): ?string
    {
        $wikiText = $this->getText();

        if (empty($wikiText)) {
            echo "Page vide\n";

            return null;
        }

        if (!$this->isAllowedToVote($wikiText)) {
            echo "Not allowed to vote\n";

            return null;
        }

        // insertion texte {{pour}}
        if (!preg_match('/(# \{\{Pour\}\}[^#\n]+\n)\n*==== Opposition ====/im', $wikiText, $matches)) {
            return null;
        }

        if (empty($matches[1])) {
            return null;
        }

        // note : \n déjà inclus
        return str_replace($matches[1], $matches[1].$this->voteText, $wikiText);
    }

    private function getText(): ?string
    {
        if ($this->pageText) {
            // cache
            return $this->pageText;
        }

        $this->pageAction = ServiceFactory::wikiPageAction($this->votePage);
        $this->pageText = $this->pageAction->getText();

        return $this->pageText;
    }

    private function editVote(string $insertVote): bool
    {
        if (!empty($insertVote)) {
            $summary = sprintf(
                '%s (%s)',
                self::SUMMARY,
                $this->comment
            );

            return $this->pageAction->editPage($insertVote, new EditInfo($summary, false, false), true);
        }
    }

    private function isAllowedToVote(string $wikitext): bool
    {
        // bourrage d'urne
        if (preg_match(self::BOURRAGE_DETECT_REGEX, $wikitext)) {
            echo "Bourrage d'urne ! ;) \n";

            return false;
        }
        if (!preg_match('#\{\{Élection administrateur en cours#i', $wikitext)) {
            return false;
        }

        return true;
    }

    /**
     * Return true if 15 {{pour}} and 60% {{pour}}
     *
     * @return bool
     */
    private function checkPourContre(): bool
    {
        $lowerText = strtolower($this->getText());
        $pour = substr_count($lowerText, '{{pour}}');
        $contre = substr_count($lowerText, '{{contre}}');
        $neutre = substr_count($lowerText, '{{neutre}}');
        $stat = $pour / ($pour + $contre + $neutre);

        echo "Stat {pour} : $pour \n";
        echo 'Stat pour/contre+neutre : '.(100 * $stat)." % \n";

        $this->comment .= $pour.';'.number_format($stat, 1).';false';

        if ($pour >= self::MIN_COUNT_POUR && $stat >= self::MIN_VALUE_POUR) {
            return true;
        }

        return false;
    }

    private function getBlacklist(): array
    {
        $list = file(self::FILENAME_BLACKLIST, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return $list ?? [];
    }

    /**
     * TODO move
     * TODO gérer espace "_"
     *
     * @return bool
     * @throws Exception
     */
    private function checkBlacklist(): bool
    {
        // Wikipédia:Administrateur/Ariel_Provost
        $blacklist = $this->getBlacklist();

        $user = $this->getUsername();

        if (in_array($user, $blacklist)) {
            echo "USER IS BLACKLISTED !! \n";

            return false;
        }

        return true;
    }

    private function getUsername()
    {
        if (!preg_match('#^Wikipédia:(?:Administrateur|Administratrice)/(.+)$#', $this->votePage, $matches)) {
            throw new Exception('username not found');
        }

        return str_replace('_', ' ', $matches[1]);
    }

    /**
     * Extract the Xtools "admin score" calculation.
     * See https://xtools.wmflabs.org/adminscore
     * also :
     * https://xtools.wmflabs.org/adminscore/fr.wikipedia.org/Ariel%20Provost
     * https://tools.wmflabs.org/supercount/index.php?user=Jennenke&project=fr.wikipedia.org
     * https://xtools.wmflabs.org/adminscore/fr.wikipedia.org/Jennenke
     * https://github.com/x-tools/xtools/blob/master/src/AppBundle/Controller/AdminScoreController.php
     * https://github.com/x-tools/xtools/blob/b39e4b114418784c6adce4a4e892b4711000a847/src/AppBundle/Model/AdminScore.php#L16
     * https://en.wikipedia.org/wiki/Wikipedia:WikiProject_Admin_Nominators/Nomination_checklist
     * Copy en JS : https://github.com/enterprisey/AAdminScore/blob/master/js/aadminscore.js
     * Class AdminScore
     */
    public function getAdminScore(): ?int
    {
        $html = $this->getAdminScoreHtml();

        if (!empty($html) && preg_match('#<th>Total</th>[^<]*<th>([0-9]+)</th>#', $html, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * @return string|null
     * @throws Exception
     */
    private function getAdminScoreHtml(): ?string
    {
        $client = new Client(
            [
                'timeout' => 300,
                'headers' => ['User-Agent' => getenv('USER_AGENT')],
                'verify' => false,
            ]
        );
        $url = 'https://xtools.wmflabs.org/adminscore/fr.wikipedia.org/'.str_replace(' ', '_', $this->getUsername());
        $resp = $client->get($url);
        if ($resp->getStatusCode() === 200) {
            return $resp->getBody()->getContents();
        }

        return null;
    }

}

