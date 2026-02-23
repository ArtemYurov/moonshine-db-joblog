<?php

namespace ArtemYurov\JobLog\Support;

use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * Simple CLI dumper without control character processing
 *
 * Control characters (including \n, \r, \t) are displayed as-is
 */
class SimpleCliDumper extends CliDumper
{
    /**
     * Override control character mapping
     */
    protected static array $controlCharsMap = [
        "\t" => '    ',
        "\n" => '',
        "\r" => '',
        "\x00" => '',
    ];
}
