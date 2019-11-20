<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\QueueInterface;
use App\Infrastructure\entities\DbEditedPage;
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

    private $newRawValidDate = '2019-11-20 14:00:00'; // v.34 sous-titre sans maj

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
     * Get one new raw text (template) to complete.
     *
     * @return string|null
     */
    public function getNewRaw(): ?string
    {
        $raw = null;

        try {
            $raw = $this->db->fetchColumn(
                'SELECT raw FROM TempRawOpti 
                WHERE (opti = "" OR optidate IS NULL OR optidate < :validDate ) AND (edited IS NULL)
                ORDER BY priority DESC,optidate,id',
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
     * Get one new raw text (template) for edit process.
     *
     * @return string|null
     */
    public function getCompletedData(): ?string
    {
        $json = null;

        try {
            $data = $this->db->fetchRow(
                'SELECT * FROM TempRawOpti 
                WHERE (opti IS NOT NULL AND opti <> "" AND optidate > :validDate AND edited IS NULL AND version IS NOT NULL AND notcosmetic=1) 
                ORDER BY priority DESC,RAND() 
                LIMIT 1',
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

    /**
     * Update DB after wiki edition.
     *
     * @param array $data
     *
     * @return bool
     */
    public function sendEditedData(array $data): bool
    {
        try {
            $result = $this->db->update(
                'TempRawOpti',
                ['id' => $data['id']], // condition
                ['edited' => date("Y-m-d H:i:s")]
            );
        } catch (MysqlException $e) {
            dump($e);

            return false;
        }

        return !empty($result);
    }

    /**
     * Get all lines from article for edit process.
     *
     * @param string   $pageTitle
     * @param int|null $limit
     *
     * @return string|null
     */
    public function getPageRows(string $pageTitle, ?int $limit = 40): ?string
    {
        $json = null;

        try {
            $data = $this->db->fetchRowMany(
            // optidate > :validDate (pour vérification que Article entièrement analysé
            // retirer "AND notcosmetic=1" pour homogenisation citations
                'SELECT * FROM TempRawOpti 
                        WHERE OPTI IS NOT NULL AND OPTI <> "" AND edited IS NULL AND notcosmetic=1 AND page = :page AND optidate > :validDate
                        LIMIT :limit',
                [
                    'validDate' => $this->newRawValidDate,
                    'page' => $pageTitle,
                    'limit' => $limit,
                ]
            );
            $json = json_encode($data);
        } catch (Throwable $e) {
            echo "SQL : No more queue to process \n";
        }

        return $json;
    }

    /**
     * Dirty naive ORM.
     *
     * @param object $object
     *
     * @return array|bool
     */
    public function saveEntity(object $object)
    {
        if ($object instanceof DbEditedPage) {
            /**
             * @var $object DbEditedPage
             */
            try {
                $id = $this->db->replace('editedpages', $object->getVars());

                return $id;
            } catch (MysqlException $e) {
                unset($e);
            }
        }

        return false;
    }

    /**
     * Dirty naive ORM.
     *
     * @param $table
     * @param $primary
     *
     * @return object|null
     */
    public function findEntity($table, $primary): ?object
    {
        if ('editedpages' === $table) {
            /**
             * @var $object DbEditedPage
             */
            try {
                $res = $this->db->fetchRow('SELECT * FROM editedpages WHERE title = :title', ['title' => $primary]);
                $obj = new DbEditedPage($this);
                $obj->setTitle($primary);
                $obj->setCompleted($res['completed']);
                $obj->setEdited($res['edited']);

                return $obj;
            } catch (MysqlException $e) {
                unset($e);
            }
        }

        return null;
    }
}
