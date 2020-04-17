<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\QueueInterface;
use App\Infrastructure\unused\DbEditedPage;
use DateInterval;
use DateTime;
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
const OPTI_VALID_DATE = '2019-11-20 14:00:00';
    protected $db;
    protected $pdoConn; // v.34 sous-titre sans maj

    public function __construct()
    {
        $pdo = new PDOConnector(
            getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE')
        );
        $this->pdoConn = $pdo->connect('utf8', ['port' => getenv('MYSQL_PORT')]);
        $this->db = new Mysql($this->pdoConn);
    }

    /**
     * @param $datas
     *
     * @return array|bool
     * @throws Exception
     */
    public function insertPageOuvrages(array $datas)
    {
        // check if article already in db
        $page = $datas[0]['page'];
        $count = $this->db->fetchRowMany(
            'SELECT id from page_ouvrages WHERE page=:page',
            ['page' => $page]
        );
        if (null !== $count) {
            return false;
        }

        // add the citations
        return $this->db->insertMany('page_ouvrages', $datas);
    }

    /**
     * Get one new row (page, raw) to complete.
     *
     * @return array|null
     */
    public function getNewRaw(): ?array
    {
        try {
            $row = $this->db->fetchRow(
                'SELECT page,raw FROM page_ouvrages 
                WHERE raw <> "" AND (opti = "" OR optidate IS NULL OR optidate < :validDate ) AND (edited IS NULL)
                ORDER BY priority DESC,id
                LIMIT 1',
                [
                    'validDate' => self::OPTI_VALID_DATE,
                ]
            );
        } catch (Throwable $e) {
            echo "SQL : No more queue to process \n";
        }

        return $row ?? null;
    }

    /**
     * Update DB with completed data from CompleteProcess.
     *
     * @param array $finalData
     *
     * @return bool
     */
    public function sendCompletedData(array $finalData): bool
    {
        try {
            $result = $this->db->update(
                'page_ouvrages',
                ['raw' => $finalData['raw']], // condition
                $finalData
            );
        } catch (MysqlException $e) {
            dump($e);

            return false;
        }

        return !empty($result);
    }

    //------------------------------------------------------
    //          EDIT QUEUE
    //------------------------------------------------------

    /**
     * Get batch of citations(template) for edit process.
     *
     * @param int|null $limit
     *
     * @return string|null
     * @throws Exception
     */
    public function getAllRowsToEdit(?int $limit = 100): ?string
    {
        try {
            $pageInfo = $this->pdoConn->query(
                '
                SELECT A.page FROM page_ouvrages A
                WHERE notcosmetic=1. 
                AND NOT EXISTS
                    (SELECT B.* FROM page_ouvrages B
                    WHERE (
                        B.edited IS NOT NULL 
                        OR B.optidate < "'.self::OPTI_VALID_DATE.'" 
                        OR B.optidate IS NULL 
                        OR B.opti="" 
                        OR B.skip=1
                        OR B.raw=""
                        )
                    AND A.page = B.page
                    )
                ORDER BY A.priority,RAND()
                LIMIT '.$limit.'
                '
            );

            // No page to edit
            $rows = $pageInfo->fetchAll();
            if (empty($rows)) {
                return '[]';
            }

            $page = $rows[0]['page'];

            // Order by optidate for first version in edit commentary ?
            $data = $this->db->fetchRowMany(
                'SELECT * FROM page_ouvrages WHERE page=:page ORDER BY optidate DESC',
                ['page' => $page]
            );
            $json = json_encode($data);
        } catch (Throwable $e) {
            throw new Exception('SQL : No more queue to process');
        }

        return $json;
    }

    public function skipArticle(string $title): bool
    {
        try {
            $result = $this->db->update(
                'page_ouvrages',
                ['page' => $title], // condition
                ['skip' => true]
            );
        } catch (MysqlException $e) {
            dump($e);

            return false;
        }

        return !empty($result);
    }

    public function skipRow(int $id): bool
    {
        try {
            $result = $this->db->update(
                'page_ouvrages',
                ['id' => $id], // condition
                ['skip' => true]
            );
        } catch (MysqlException $e) {
            dump($e);

            return false;
        }

        return !empty($result);
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
                'page_ouvrages',
                ['id' => $data['id']], // condition
                ['edited' => date('Y-m-d H:i:s')]
            );
        } catch (MysqlException $e) {
            dump($e);

            return false;
        }

        return !empty($result);
    }

    //------------------------------------------------------

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
            /*
             * @var $object DbEditedPage
             */
            try {
                return $this->db->replace('editedpages', $object->getVars());
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
            /*
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

    /**
     * Get a row to monitor edits.
     */
    public function getMonitor(): ?array
    {
        $data = null;
        // 2 hours ago
        $beforeTime = (new DateTime())->sub(new DateInterval('PT3H'));

        try {
            $data = $this->db->fetchRowMany(
                'SELECT id,page,raw,opti,optidate,edited,verify,skip FROM page_ouvrages WHERE page = (
                    SELECT page FROM page_ouvrages
                    WHERE edited IS NOT NULL 
                    and edited > :afterDate and edited < :beforeDate
                    and (verify is null or verify < :nextVerifyDate )
             		ORDER BY verify,edited
                    LIMIT 1)',
                [
                    'afterDate' => '2019-11-26 06:00:00',
                    'beforeDate' => $beforeTime->format('Y-m-d H:i:s'),
                    'nextVerifyDate' => (new DateTime())->sub(new DateInterval('P2D'))->format('Y-m-d H:i:s'),
                ]
            );
        } catch (Throwable $e) {
            echo "SQL : No more queue to process \n";
        }

        return $data;
    }

    public function updateMonitor(array $data): bool
    {
        if (empty($data['page'])) {
            throw new Exception('pas de page');
        }

        try {
            $result = $this->db->update(
                'page_ouvrages',
                ['page' => $data['page']], // condition
                $data
            );
        } catch (MysqlException $e) {
            dump($e);

            return false;
        }

        return !empty($result);
    }
}
