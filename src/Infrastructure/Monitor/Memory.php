<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

namespace App\Infrastructure\Monitor;

use App\Application\InfrastructurePorts\MemoryInterface;

/**
 * todo move /Monitor
 * php -i | grep memory
 * Class Memory.
 */
class Memory implements MemoryInterface
{
    /**
     * $real=true shows allocated memory (-> system monitoring)
     * $real=null|false shows memory used by script (-> memory leak search)
     * Do not take PHP external resources into account (remote/DB connection, SimpleXML, etc)
     * See http://drib.tech/programming/get-real-amount-memory-allocated-php.
     *
     * @param bool|null $real
     *
     * @return string
     * @return string
     */
    public function getMemory(?bool $real = null): string
    {
        return sprintf(
            "Memory %s: %s %s \n",
            ($real) ? '(true)' : '',
            $this->memoryUsage(),
            $this->memoryPeak()
        );
    }

    public function memoryUsage(?bool $real = null): string
    {
        $memUsage = memory_get_usage($real);

        return sprintf('usage: %s', $this->convert($memUsage));
    }

    public function memoryPeak(?bool $real = null): string
    {
        $memUsage = memory_get_peak_usage($real);

        return sprintf('peak: %s', $this->convert($memUsage));
    }

    private function convert(int $size): string
    {
        $unit = ['b', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb'];

        return @round($size / 1024 ** ($i = floor(log($size, 1024))), 2).' '.$unit[(int) $i];
    }
}
