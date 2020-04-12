<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\Memory;
use App\Application\WikiPageAction;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\ServiceFactory;
use Mediawiki\Api\MediawikiFactory;
use Normalizer;


include __DIR__.'/../myBootstrap.php';

$process = new Monitor();
$process->run();

/**
 * TODO refac
 * Class EditProcess
 *
 * @package App\Application\Examples
 */
class Monitor
{
    const SLEEP_TIME = 5;

    private $db;
    private $lastTitle = '';
    /**
     * @var MediawikiFactory
     */
    private $wiki;

    public function __construct()
    {
        $this->db = new DbAdapter();
        $this->wiki = ServiceFactory::wikiApi();
    }

    public function run()
    {
        while (true) {
            echo "\n-----MONITOR------------------------\n\n";
            //$memory->echoMemory(true);

            $this->pageProcess();
            sleep(self::SLEEP_TIME);
        }
    }

    private function pageProcess()
    {
        $data = $this->db->getMonitor();
        if (empty($data)) {
            echo "new data vide\n";
            exit();
        }

        $title = $data[0]['page'];
        if ($title === $this->lastTitle) {
            echo "end\n";
            exit;
        }
        echo "$title \n";

        $pageAction = new WikiPageAction($this->wiki, $title);
        $text = $pageAction->getText();
        if (!$text || empty($text)) {
            echo "Pas de wiki texte\n";
            $stat = 0;
            goto updateMonitor;
        }

        $stat = '0';
        $suffix = '';
        if (!in_array($pageAction->getLastEditor(), ['CodexBot', 'ZiziBot', getenv('BOT_NAME'), getenv('BOT_OWNER')])) {
            $stat = $this->checkAltered($data, $text);
            $suffix = '[found]';
        }

        echo $data[0]['edited']." : ".$stat." % ".$suffix."\n";

        updateMonitor:
        $this->db->updateMonitor(
            [
                'page' => $title ?? '',
                'verify' => date("Y-m-d H:i:s"),
                'altered' => (int)$stat,
            ]
        );
    }

    /**
     * TODO : if find raw -> reverted
     *
     * @param array  $data
     * @param string $text
     *
     * @return int
     */
    private function checkAltered(array $data, string $text): int
    {
        if (count($data) === 0) {
            return 99;
        }
        $found = 0;
        $count = 0;
        $text = mb_strtolower($text);
        foreach ($data as $dat) {
            if (1 === intval($dat['skip']) || empty($dat['edited'])) {
                continue;
            }
            $count++;
            if (empty($dat['opti'])) {
                echo 'opti vide';
                continue;
            }

            // compte pas différence Unicode entre BD et wiki
            $opti = Normalizer::normalize($dat['opti']); // hack
            // compte pas les changements de typo majuscule/minuscule
            $optiLower = mb_strtolower($opti);
            // compte pas la correction sur ouvrage avec commentaire HTML
            $optiComment = WikiTextUtil::removeHTMLcomments($opti);
            // compte pas la suppression de langue=fr : provisoire (fix on SQL)
            $optiLanfr = preg_replace('#\|[\n ]*langue=fr[\n ]*#', '', $opti);

            if (!empty($opti)
                && (mb_strpos($text, $opti) !== false
                    || mb_strpos(mb_strtolower($text), $optiLower) !== false
                    || mb_strpos($text, $optiComment) !== false
                    || mb_strpos($text, $optiLanfr) !== false)
            ) {
                echo '+';
                $found++;
            } else {
                echo '-';
            }
            // ici update DB

        }

        return (int)round(($count - $found) / count($data) * 100);
    }

}
