<?php

namespace ArtemYurov\JobLog\Database;

use ArtemYurov\JobLog\Database\Exceptions\DatabaseConfigurationException;

/**
 * Resolves which connection and driver the log storage runs on: the connection
 * is joblog.database.connection or, when unset, the application default; the
 * driver is always that connection's own driver.
 *
 * All lookups go through config() and tolerate its absence (unit-test
 * context without a booted framework): the driver then resolves to null and
 * guard methods simply skip validation.
 */
final class JobLogStorage
{
    public const DRIVER_MONGODB = 'mongodb';

    /**
     * Effective connection name: the explicit joblog.database.connection or,
     * when unset, the application default (database.default). Null means
     * "no framework database context at all".
     */
    public static function connectionName(): ?string
    {
        $configured = self::config('joblog.database.connection');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $default = self::config('database.default');

        return is_string($default) && $default !== '' ? $default : null;
    }

    /**
     * Driver of the effective connection. Never throws; null when it cannot be
     * determined (no framework, no config, unknown connection).
     */
    public static function driver(): ?string
    {
        $connection = self::connectionName();

        if ($connection === null) {
            return null;
        }

        $driver = self::config("database.connections.{$connection}.driver");

        return is_string($driver) && $driver !== '' ? $driver : null;
    }

    /** True when the log storage runs on MongoDB. */
    public static function isMongo(): bool
    {
        return self::driver() === self::DRIVER_MONGODB;
    }

    /**
     * Whether the mongodb/laravel-mongodb package is available in the host
     * application (it is optional and lives in "suggest").
     */
    public static function hasMongoPackage(): bool
    {
        return class_exists(\MongoDB\Laravel\Eloquent\Model::class);
    }

    /**
     * Validate the resolved configuration, throwing a descriptive exception
     * on every failure mode: no default connection, unknown connection,
     * MongoDB selected without the package installed.
     */
    public static function assertSupported(): void
    {
        $connection = self::connectionName();

        if ($connection === null) {
            throw DatabaseConfigurationException::noDefaultConnection();
        }

        $driver = self::driver();

        if ($driver === null) {
            throw DatabaseConfigurationException::connectionNotFound($connection);
        }

        if ($driver === self::DRIVER_MONGODB && !self::hasMongoPackage()) {
            throw DatabaseConfigurationException::missingMongoPackage();
        }
    }

    /**
     * Boot-time guard: validate only when a framework database context
     * exists. Test harnesses without database config are left alone.
     */
    public static function guardWhenConfigured(): void
    {
        if (self::config('database.default') === null && self::config('joblog.database.connection') === null) {
            return;
        }

        self::assertSupported();
    }

    private static function config(string $key, mixed $default = null): mixed
    {
        if (!function_exists('config')) {
            return $default;
        }

        return config($key, $default);
    }
}
