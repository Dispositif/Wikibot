<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Infrastructure;

use App\Domain\Models\PageOuvrageDTO;
use Simplon\Mysql\Crud\CrudStore;
use Simplon\Mysql\MysqlException;
use Simplon\Mysql\QueryBuilder\CreateQueryBuilder;
use Simplon\Mysql\QueryBuilder\DeleteQueryBuilder;
use Simplon\Mysql\QueryBuilder\ReadQueryBuilder;
use Simplon\Mysql\QueryBuilder\UpdateQueryBuilder;

class PageOuvrageStore extends CrudStore
{
    public function getTableName(): string
    {
        return 'page_ouvrages';
    }

    public function getModel(): PageOuvrageDTO
    {
        return new PageOuvrageDTO();
    }

    /**
     * @throws MysqlException
     */
    public function create(CreateQueryBuilder $builder): PageOuvrageDTO
    {
        /** @var PageOuvrageDTO $model */
        $model = $this->crudCreate($builder);

        return $model;
    }

    /**
     * @return PageOuvrageDTO[]|null
     * @throws MysqlException
     */
    public function read(?ReadQueryBuilder $builder = null): ?array
    {
        /** @var PageOuvrageDTO[]|null $response */
        $response = $this->crudRead($builder);

        return $response;
    }

    /**
     * @throws MysqlException
     */
    public function readOne(ReadQueryBuilder $builder): ?PageOuvrageDTO
    {
        /** @var PageOuvrageDTO|null $response */
        $response = $this->crudReadOne($builder);

        return $response;
    }

    /**
     * @throws MysqlException
     */
    public function update(UpdateQueryBuilder $builder): PageOuvrageDTO
    {
        /** @var PageOuvrageDTO|null $model */
        $model = $this->crudUpdate($builder);

        return $model;
    }

    /**
     * @throws MysqlException
     */
    public function delete(DeleteQueryBuilder $builder): bool
    {
        return $this->crudDelete($builder);
    }

//    /**
//     * @throws MysqlException
//     */
//    public function customMethod(int $id): ?PageOuvrageDTO
//    {
//        $query = 'SELECT * FROM ' . $this->getTableName() . ' WHERE id=:id';
//
//        if ($result = $this->getCrudManager()->getMysql()->fetchRow($query, ['id' => $id]))
//        {
//            return (new PageOuvrageDTO())->fromArray($result);
//        }
//
//        return null;
//    }
}