<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

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
 *
 */
class Monitor
{
    public const SLEEP_TIME = 60;

    private $db;
    private $lastTitle = '';
    /**
     * @var MediawikiFactory
     */
    private $wiki;

    public function __construct()
    {
        $this->db = new DbAdapter();
        $this->wiki = ServiceFactory::getMediawikiFactory();
    }

    public function run(): void
    {
        $i = 0;
        while (true) {
            $i++;
            echo "\n-----MONITOR------------------------\n\n";
            //$memory->echoMemory(true);

            $this->pageProcess();
            sleep(self::SLEEP_TIME);

            if ($i > 1000) {
                echo "1000 monitoring => break";
                break;
            }
        }
    }

    private function pageProcess(): void
    {
        $data = $this->db->getMonitor();
        if (empty($data)) {
            echo "new data vide. Sleep 1h\n";
            sleep(60 * 60);

            return;
        }

        $title = $data[0]['page'];
        if ($title === $this->lastTitle) {
            echo "end. Sleep 1h\n";
            sleep(60 * 60);

            return;
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
        if ($data === []) {
            return 99;
        }
        $found = 0;
        $count = 0;
        $text = mb_strtolower($text);
        foreach ($data as $dat) {
            if (1 === (int) $dat['skip'] || empty($dat['edited'])) {
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
