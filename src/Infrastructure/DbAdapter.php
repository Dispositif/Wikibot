<?php
declare(strict_types=1);

namespace App\Infrastructure;

use Simplon\Mysql\Mysql;
use Simplon\Mysql\MysqlException;
use Simplon\Mysql\PDOConnector;

/**
 * Temporary SQL play. https://packagist.org/packages/simplon/mysql
 *
 * Class DbAdapter
 *
 * @package App\Infrastructure
 */
class DbAdapter
{
    private $dbConn;

    public function __construct() {

        $pdo = new PDOConnector(
            getenv('MYSQL_HOST'),
            getenv('MYSQL_USER'),
            getenv('MYSQL_PASSWORD'),
            getenv('MYSQL_DATABASE')
        );
        $pdoConn = $pdo->connect('utf8', ['port' => getenv('MYSQL_PORT')]);
        $this->dbConn = new Mysql($pdoConn);
    }

    /**
     * @param $datas
     *
     * @return int|null
     * @throws \Exception
     */
    public function  insertTempRawOpti($datas):?int
    {
        $id = $this->dbConn->insertMany('TempRawOpti', $datas);
        return $id;
    }

    /**
     * @param $text
     *
     * @return array
     */
    public function wstatTextToArrayData($text):array
    {
        $data = [];
        if (preg_match_all('#^([^\{\}]+) : (\{\{.+\}\})$#m', $text, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $key => $val){
                $data[] = ['page' => $val[1], 'raw' => $val[2]];
            }
        }
        return $data;
    }

}
