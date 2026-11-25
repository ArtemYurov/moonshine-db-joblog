<?php

namespace ArtemYurov\JobLog\Database\Exceptions;

/**
 * The MongoDB driver was selected, but the host application does not have
 * the mongodb/laravel-mongodb package installed.
 */
class MissingMongoDbPackageException extends DatabaseConfigurationException
{
    public static function make(): self
    {
        return new self(
            'JobLog: driver "mongodb" requires the mongodb/laravel-mongodb package. '
            . 'Install it with: composer require mongodb/laravel-mongodb '
            . '(the ext-mongodb PHP extension is required as well).'
        );
    }
}
