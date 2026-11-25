<?php

namespace ArtemYurov\JobLog\Database\Exceptions;

use ArtemYurov\JobLog\Database\JobLogDatabase;

/**
 * The resolved database driver is not one of the supported drivers
 * (see JobLogDatabase::SUPPORTED_DRIVERS). The message tells the user to
 * ask the author for support and points to the repository issues.
 */
class UnsupportedDatabaseDriverException extends DatabaseConfigurationException
{
    public static function unsupported(string $driver): self
    {
        return new self(sprintf(
            'JobLog: database driver "%s" is not supported. Supported drivers: %s. '
            . 'If you need "%s" support, please ask the author by opening an issue: %s',
            $driver,
            implode(', ', JobLogDatabase::SUPPORTED_DRIVERS),
            $driver,
            JobLogDatabase::REPOSITORY_ISSUES_URL,
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
}
