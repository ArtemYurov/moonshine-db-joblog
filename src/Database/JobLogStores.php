<?php

namespace ArtemYurov\JobLog\Database;

use ArtemYurov\JobLog\Database\Contracts\JobLogStore;
use ArtemYurov\JobLog\Database\Stores\EloquentJobLogStore;
use ArtemYurov\JobLog\Database\Stores\MongoDbJobLogStore;

/**
 * Single resolution point for the log storage.
 *
 * Prefers the container binding registered by the service provider; falls
 * back to constructing the right implementation directly, so consumers
 * work even in harnesses that wire the package partially (integration
 * tests that only boot the queue event listeners, for example).
 */
final class JobLogStores
{
    public static function resolve(): JobLogStore
    {
        if (function_exists('app') && app()->bound(JobLogStore::class)) {
            return app(JobLogStore::class);
        }

        return JobLogDatabase::isMongo()
            ? new MongoDbJobLogStore()
            : new EloquentJobLogStore();
    }
}
