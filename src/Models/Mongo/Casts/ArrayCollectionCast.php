<?php

namespace ArtemYurov\JobLog\Models\Mongo\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Stores the value as a native BSON array or document (queryable with `$all` and
 * dotted paths) and reads it back as a Collection.
 */
class ArrayCollectionCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Collection
    {
        return $value === null ? null : collect($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        // Keyed by the attribute: Eloquent merges an array returned from set() into the attributes.
        return [$key => $value === null ? null : json_decode(json_encode($value, JSON_THROW_ON_ERROR), true)];
    }
}
