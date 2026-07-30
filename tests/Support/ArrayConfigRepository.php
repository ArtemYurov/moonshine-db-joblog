<?php

namespace ArtemYurov\JobLog\Tests\Support;

use ArrayAccess;
use Illuminate\Support\Arr;

/**
 * Minimal dot-aware config repository for the integration harness — illuminate/config
 * isn't a dependency and Fluent can't back dotted keys like config('queue.connections.sync').
 */
class ArrayConfigRepository implements ArrayAccess
{
    public function __construct(private array $items = []) {}

    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    public function get($key, $default = null)
    {
        return Arr::get($this->items, $key, $default);
    }

    public function set($key, $value = null): void
    {
        Arr::set($this->items, $key, $value);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        Arr::forget($this->items, $offset);
    }
}
