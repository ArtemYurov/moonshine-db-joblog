<?php

namespace ArtemYurov\JobLog\Database\Exceptions;

use RuntimeException;

/**
 * Base class for every "the log storage cannot be used as configured" case.
 * Catch this when you only care that configuration is broken.
 */
class DatabaseConfigurationException extends RuntimeException
{
}
