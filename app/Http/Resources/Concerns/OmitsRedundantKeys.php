<?php

namespace App\Http\Resources\Concerns;

trait OmitsRedundantKeys
{
    /**
     * Remove $foreignKey from the payload when $relationKey is present and non-empty.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function omitIfRelationPresent(array $data, string $foreignKey, string $relationKey): array
    {
        if (! array_key_exists($relationKey, $data)) {
            return $data;
        }

        $relation = $data[$relationKey];

        if ($relation === null) {
            return $data;
        }

        if ($relation instanceof \Illuminate\Http\Resources\MissingValue) {
            return $data;
        }

        if (is_array($relation) && $relation === []) {
            return $data;
        }

        unset($data[$foreignKey]);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return array<string, mixed>
     */
    protected function omitRedundantRelationKeys(array $data, array $pairs): array
    {
        foreach ($pairs as [$foreignKey, $relationKey]) {
            $data = $this->omitIfRelationPresent($data, $foreignKey, $relationKey);
        }

        return $data;
    }
}
