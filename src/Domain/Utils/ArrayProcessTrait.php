<?php
declare(strict_types=1);

namespace App\Domain\Utils;


trait ArrayProcessTrait
{
    /**
     * Delete keys with empty string value ""
     *
     * @param array $myArray
     *
     * @return array
     */
    public function deleteEmptyValueArray(array $myArray)
    {
        $result = [];
        foreach ($myArray as $key => $value) {
            if (!empty($key) && !empty($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
