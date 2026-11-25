<?php

namespace ArtemYurov\JobLog\Database\Exceptions;

/**
 * An explicit joblog.database.driver contradicts the actual driver of the
 * configured connection. Configuration must be fixed, not reported upstream.
 */
class DriverConnectionMismatchException extends DatabaseConfigurationException
{
    public static function mismatch(string $configuredDriver, string $actualDriver, string $connection): self
    {
        return new self(sprintf(
            'JobLog: joblog.database.driver is set to "%s", but connection "%s" actually uses driver "%s". '
            . 'Either remove the explicit driver setting (use "auto") or point joblog.database.connection '
            . 'to a "%s" connection.',
            $configuredDriver,
            $connection,
            $actualDriver,
            $configuredDriver,
        ));
    }
}
