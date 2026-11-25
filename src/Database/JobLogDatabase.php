<?php

namespace ArtemYurov\JobLog\Database;

use ArtemYurov\JobLog\Database\Exceptions\DriverConnectionMismatchException;
use ArtemYurov\JobLog\Database\Exceptions\MissingMongoDbPackageException;
use ArtemYurov\JobLog\Database\Exceptions\UnsupportedDatabaseDriverException;

/**
 * Resolves which connection and driver the log storage runs on.
 *
 * Flow (agreed):
 *  - joblog.database.connection is null → the application default connection
 *    is used (whatever DB_CONNECTION points to);
 *  - joblog.database.driver is 'auto' (default) → the driver is taken from
 *    the actual connection settings;
 *  - an explicit driver must match the actual connection driver;
 *  - anything outside {pgsql, mongodb} is rejected with a link to the
 *    repository issues.
 *
 * All lookups go through config() and tolerate its absence (unit-test
 * context without a booted framework): the driver then resolves to null and
 * guard methods simply skip validation.
 */
final class JobLogDatabase
{
    public const DRIVER_AUTO = 'auto';

    public const DRIVER_PGSQL = 'pgsql';
    public const DRIVER_MONGODB = 'mongodb';

    /** Drivers the package can store its data in. */
    public const SUPPORTED_DRIVERS = [self::DRIVER_PGSQL, self::DRIVER_MONGODB];

    public const REPOSITORY_ISSUES_URL = 'https://github.com/ArtemYurov/moonshine-db-joblog/issues';

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
     * Driver actually in effect: the explicit joblog.database.driver, or the
     * real driver of the effective connection when set to 'auto'. Never
     * throws; null when it cannot be determined (no framework, no config).
     */
    public static function effectiveDriver(): ?string
    {
        $configured = self::config('joblog.database.driver');

        if (is_string($configured) && $configured !== '' && $configured !== self::DRIVER_AUTO) {
            return $configured;
        }

        $connection = self::connectionName();

        if ($connection === null) {
            return null;
        }

        $actual = self::config("database.connections.{$connection}.driver");

        return is_string($actual) && $actual !== '' ? $actual : null;
    }

    /** True when the log storage runs on MongoDB. */
    public static function isMongo(): bool
    {
        return self::effectiveDriver() === self::DRIVER_MONGODB;
    }

    /** True when the log storage runs on a supported SQL driver (pgsql). */
    public static function isSql(): bool
    {
        return self::effectiveDriver() === self::DRIVER_PGSQL;
    }

    /**
     * Whether the mongodb/laravel-mongodb package is available in the host
     * application (it is optional and lives in "suggest").
     */
    public static function mongodbPackageInstalled(): bool
    {
        return class_exists(\MongoDB\Laravel\Eloquent\Model::class)
            || class_exists(\MongoDB\Client::class);
    }

    /**
     * Validate the resolved configuration, throwing a descriptive exception
     * on every failure mode: no default connection, unknown connection,
     * unsupported driver (with the issues link), explicit-driver mismatch,
     * MongoDB selected without the package installed.
     */
    public static function assertSupported(): void
    {
        $connection = self::connectionName();

        if ($connection === null) {
            throw UnsupportedDatabaseDriverException::noDefaultConnection();
        }

        $actual = self::config("database.connections.{$connection}.driver");

        if (!is_string($actual) || $actual === '') {
            throw UnsupportedDatabaseDriverException::connectionNotFound($connection);
        }

        $driver = self::effectiveDriver() ?? '';

        if (!in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw UnsupportedDatabaseDriverException::unsupported($driver);
        }

        $configured = self::config('joblog.database.driver');

        if (is_string($configured) && $configured !== '' && $configured !== self::DRIVER_AUTO && $configured !== $actual) {
            throw DriverConnectionMismatchException::mismatch($configured, $actual, $connection);
        }

        if ($driver === self::DRIVER_MONGODB && !self::mongodbPackageInstalled()) {
            throw MissingMongoDbPackageException::make();
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

    /** For SQL migrations: run only on a supported SQL driver. */
    public static function shouldRunSqlSchema(): bool
    {
        // No framework database context (unit harness): SQL migrations
        // target the application default connection and may proceed.
        if (self::connectionName() === null) {
            return !self::isMongo();
        }

        self::assertSupported();

        return self::effectiveDriver() !== self::DRIVER_MONGODB;
    }

    /** For MongoDB migrations: run only when MongoDB is the driver. */
    public static function shouldRunMongoSchema(): bool
    {
        if (self::connectionName() === null) {
            return self::isMongo();
        }

        self::assertSupported();

        return self::effectiveDriver() === self::DRIVER_MONGODB;
    }

    private static function config(string $key, mixed $default = null): mixed
    {
        if (!function_exists('config')) {
            return $default;
        }

        return config($key, $default);
    }
}
