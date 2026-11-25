<?php

namespace ArtemYurov\JobLog\Database\Exceptions;

use ArtemYurov\JobLog\Database\JobLogStorage;
use RuntimeException;

/**
 * The log storage cannot be used as configured; the message says what to fix.
 */
class DatabaseConfigurationException extends RuntimeException
{
    public static function unsupported(string $driver): self
    {
        return new self(sprintf(
            'JobLog: database driver "%s" is not supported. Supported drivers: %s. '
            . 'If you need "%s" support, please ask the author by opening an issue: %s',
            $driver,
            implode(', ', JobLogStorage::SUPPORTED_DRIVERS),
            $driver,
            JobLogStorage::REPOSITORY_ISSUES_URL,
        ));
    }

    public static function noDefaultConnection(): self
    {
        return new self(
            'JobLog: no database connection is configured (database.default is empty). '
            . 'Configure a database connection or set joblog.database.connection explicitly.'
        );
    }

    public static function connectionNotFound(string $connection): self
    {
        return new self(sprintf(
            'JobLog: connection "%s" is not defined in database.connections. '
            . 'Check the spelling or add it to config/database.php.',
            $connection,
        ));
    }

    public static function missingMongoPackage(): self
    {
        return new self(
            'JobLog: driver "mongodb" requires the mongodb/laravel-mongodb package. '
            . 'Install it with: composer require mongodb/laravel-mongodb '
            . '(the ext-mongodb PHP extension is required as well).'
        );
    }
}
