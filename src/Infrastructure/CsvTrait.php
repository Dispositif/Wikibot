<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use Exception;

trait CsvTrait
{
    /**
     * Search string in a simple list CSV.
     *
     * @param string   $filename
     * @param string   $search
     * @param int|null $col
     *
     * @return bool
     * @throws Exception
     */
    public function isStringInCSV(string $filename, string $search, ?int $col = 0): bool
    {
        return !empty($this->findCSVline($filename, $search, $col));
    }

    /**
     * @param string $filename
     *
     * @return array
     * @throws Exception
     */
    public function getCSVfirstLine(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new Exception('no file '.$filename);
        }
        $f = fopen($filename, 'r');
        if ($f === false) {
            throw new Exception('can not open '.$filename);
        }
        $row = fgetcsv($f);
        fclose($f);

        return (is_array($row)) ? $row : [];
    }

    public function findCSVline(string $filename, string $search, ?int $col = 0): array
    {
        if (!file_exists($filename)) {
            throw new Exception('no file '.$filename);
        }
        $f = fopen($filename, 'r');
        if ($f === false) {
            throw new Exception('can not open '.$filename);
        }
        while ($row = fgetcsv($f)) {
            if (isset($row[$col]) && $row[$col] === $search) {
                return $row;
            }
        }
        fclose($f);

        return [];
    }

    /**
     * todo: Ugly. Memory consuming.
     *
     * @param string $filename
     *
     * @throws Exception
     */
    public function deleteFirstLineCsv(string $filename): void
    {
        if (!file_exists($filename)) {
            throw new Exception('no file '.$filename);
        }
        $f = fopen($filename, 'r');
        if ($f === false) {
            throw new Exception('can not open '.$filename);
        }
        while (false !== ($line = fgetcsv($f))) {
            $data[] = $line;
        }
        fclose($f);
        if (empty($data)) {
            return;
        }
        array_shift($data);
        $f = fopen($filename, 'w');
        if ($f === false) {
            throw new Exception('can not open '.$filename);
        }
        foreach ($data as $fields) {
            fputcsv($f, $fields);
        }
        fclose($f);
    }

    public function putArrayInCSV(string $filename, array $array)
    {
        // create file if not exists
        $fp = fopen($filename, 'a+');
        if ($fp === false) {
            throw new Exception('can not open '.$filename);
        }
        if (is_array($array[0])) {
            foreach ($array as $ar) {
                fputcsv($fp, $ar);
            }
        }
        if (!is_array($array[0])) {
            fputcsv($fp, $array);
        }

        fclose($fp);
    }
}
