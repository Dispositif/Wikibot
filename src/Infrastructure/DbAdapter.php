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
 * Temporary SQL play. https://packagist.org/packages/simplon/mysql.
 * Class DbAdapter.
 */
class DbAdapter implements QueueInterface
{
    private $db;

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
     *
     * @throws Exception
     */
    public function insertTempRawOpti($datas)
    {
        $id = $this->db->insertMany('TempRawOpti', $datas);

        return $id;
    }

    public function getNewRaw(): string
    {
        $raw = null;

        try {
            $raw = $this->db->fetchColumn('SELECT raw FROM TempRawOpti WHERE optidate IS NULL', []);
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
}
