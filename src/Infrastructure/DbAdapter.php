<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\QueueInterface;
use Exception;
use Simplon\Mysql\Mysql;
use Simplon\Mysql\MysqlException;
use Simplon\Mysql\PDOConnector;
use Throwable;

/**
 * Temporary SQL play. https://github.com/fightbulc/simplon_mysql .
 * Class DbAdapter.
 */
class DbAdapter implements QueueInterface
{
    private $db;

    private $newRawValidDate = '2019-10-25 16:16:18'; // valid domain code date

    public function __construct()
    {
        $pdo = new PDOConnector(
            getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE')
        );
        $pdoConn = $pdo->connect('utf8', ['port' => getenv('MYSQL_PORT')]);
        $this->db = new Mysql($pdoConn);
    }

    /**
     * @param $datas
     *
     * @return int|null
     * @throws Exception
     */
    public function insertTempRawOpti($datas)
    {
        $id = $this->db->insertMany('TempRawOpti', $datas);

        return $id;
    }

    /**
     * Get new raw text (template) to process
     *
     * @return string|null
     */
    public function getNewRaw(): ?string
    {
        $raw = null;

        try {
            $raw = $this->db->fetchColumn(
                'SELECT raw FROM TempRawOpti WHERE (optidate IS NULL OR optidate < :validDate ) AND edited IS NULL',
                [
                    'validDate' => $this->newRawValidDate,
                ]
            );
        } catch (Throwable $e) {
            echo "SQL : No more queue to process \n";
        }

        return $raw;
    }

    public function sendCompletedData(array $finalData): bool
    {
        try {
            $result = $this->db->update(
                'TempRawOpti',
                ['raw' => $finalData['raw']], // condition
                $finalData
            );
        } catch (MysqlException $e) {
            dump($e);

            return false;
        }

        return !empty($result);
    }

    /**
     * Get new raw text (template) to process
     *
     * @return string|null
     */
    public function getCompletedData(): ?string
    {
        $json = null;

        try {
            $data = $this->db->fetchRow(
                'SELECT * FROM TempRawOpti WHERE (optidate > :validDate AND notcosmetic=1) ORDER BY RAND() LIMIT 1',
                [
                    'validDate' => $this->newRawValidDate,
                ]
            );
            $json = json_encode($data);
        } catch (Throwable $e) {
            echo "SQL : No more queue to process \n";
        }

        return $json;
    }

    public function sendEditedData(array $data): bool
    {
        try {
            $result = $this->db->update(
                'TempRawOpti',
                ['id' => $data['id']], // condition
                ['edited' => 1]
            );
        } catch (MysqlException $e) {
            dump($e);

            return false;
        }

        return !empty($result);
    }

    /**
     * Get all lines from an article.
     *
     * @param string $pageTitle
     *
     * @return string|null
     */
    public function getPageRows(string $pageTitle): ?string
    {
        $json = null;

        try {
            $data = $this->db->fetchRowMany(
                'SELECT * FROM TempRawOpti WHERE optidate > :validDate AND edited IS NULL AND page = :page LIMIT 5',
                [
                    'validDate' => $this->newRawValidDate,
                    'page' => $pageTitle
                ]
            );
            $json = json_encode($data);
        } catch (Throwable $e) {
            echo "SQL : No more queue to process \n";
        }

        return $json;
    }


}
