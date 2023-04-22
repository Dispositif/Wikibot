<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\InfrastructurePorts\DbAdapterInterface;
use DateInterval;
use DateTime;
use Exception;
use Simplon\Mysql\Mysql;
use Simplon\Mysql\MysqlException;
use Simplon\Mysql\PDOConnector;
use Throwable;

/**
 * TODO return DTO !!!
 * Temporary SQL play. https://github.com/fightbulc/simplon_mysql .
 * Class DbAdapter.
 */
class DbAdapter implements DbAdapterInterface
{
    public const OPTI_VALID_DATE = '2020-04-05 00:00:00'; // v0.77
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

    public function getOptiValidDate(): string
    {
        return self::OPTI_VALID_DATE;
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
     * Order by isbn (NULL first)
     *
     * @return array|null
     */
    public function getNewRaw(): ?array
    {
        try {
            $row = $this->db->fetchRow(
                'SELECT id,page,raw FROM page_ouvrages 
                WHERE raw <> "" AND (opti IS NULL OR opti = "" OR optidate IS NULL OR optidate < :validDate ) AND (edited IS NULL) AND skip=0
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
     * TODO DTO !!
     * Get batch of citations(template) for edit process.
     *
     * @param int|null $limit
     *
     * @return string|null
     * @throws Exception
     */
    public function getAllRowsOfOneTitleToEdit(?int $limit = 100): ?string
    {
//        // ----------- TEST ----
//        // it works
//        $store = new PageOuvrageStore($this->db);
//        $pageOuvrageModel = $store->read(
//            (new ReadQueryBuilder())->addCondition(PageOuvrageDTO::COLUMN_PAGE, 'Autorail Pauline')
//        );
//        dump($pageOuvrageModel);
//        die('stooooop');
//
//        // ---------- end TEST ----

        $e = null;
        try {
            $pageInfo = $this->pdoConn->query(
                '
                SELECT A.page FROM page_ouvrages A
                WHERE A.notcosmetic=1 AND A.opti IS NOT NULL
                AND NOT EXISTS
                    (SELECT B.* FROM page_ouvrages B
                    WHERE (
                        B.edited IS NOT NULL 
                        OR B.optidate < "'.self::OPTI_VALID_DATE.'" 
                        OR B.optidate IS NULL 
                        OR B.opti IS NULL
                        OR B.opti="" 
                        OR B.skip=1
                        OR B.raw=""
                        )
                    AND A.page = B.page
                    )
                ORDER BY A.priority DESC,A.optidate,RAND()
                LIMIT '.$limit.'
                '
            );

            // No page to edit
            $rows = $pageInfo->fetchAll();
            if (empty($rows)) {
                return '[]';
            }

            $page = $rows[0]['page']; // get first page to edit ?

            // todo Replace bellow with PageOuvrageDTO
            // Order by optidate for first version in edit commentary ?
            $data = $this->db->fetchRowMany(
                'SELECT * FROM page_ouvrages WHERE page=:page ORDER BY optidate DESC',
                ['page' => $page]
            );
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new Exception('SQL : No more queue to process', $e->getCode(), $e);
        }

        return $json;
    }

    public function deleteArticle(string $title): bool
    {
        try {
            $result = $this->db->delete(
                'page_ouvrages',
                ['page' => $title] // condition
            );
        } catch (MysqlException $e) {
            dump($e);

            return false;
        }

        return !empty($result);
    }

    public function skipArticle(string $title): bool
    {
        try {
            $result = $this->db->update(
                'page_ouvrages',
                ['page' => $title], // condition
                ['skip' => 1]
            );
        } catch (MysqlException $e) {
            dump($e);

            return false;
        }

        return !empty($result);
    }

    public function setLabel(string $title, ?int $val = 0): bool
    {
        try {
            $result = $this->db->update(
                'page_ouvrages',
                ['page' => $title], // condition
                ['label' => $val]
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
                ['skip' => 1]
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
