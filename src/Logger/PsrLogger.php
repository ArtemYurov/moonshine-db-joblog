<?php

namespace ArtemYurov\JobLog\Logger;

use ArtemYurov\JobLog\Support\SimpleCliDumper;
use Psr\Log\AbstractLogger;
use Monolog\Logger;

/**
 * PSR-3 compatible logger: writes to DB via Monolog + outputs to console.
 * Used as the core in JobLogger/JobLoggerStep and exposed via getLoggerInterface().
 */
class PsrLogger extends AbstractLogger
{
    private static array $levelColors = [
        'EMERGENCY' => "\033[1;37;45m",
        'ALERT'     => "\033[1;37;41m",
        'CRITICAL'  => "\033[1;37;41m",
        'ERROR'     => "\033[0;37;41m",
        'WARNING'   => "\033[0;30;43m",
        'NOTICE'    => "\033[0;36m",
        'INFO'      => "\033[0;32m",
        'DEBUG'     => "\033[0;33m",
    ];

    public function __construct(
        private Logger  $monolog,
        private ?string $consolePrefix = null,
        private bool    $consoleOutput = false,
    ) {}

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($this->consoleOutput) {
            $this->consoleLog($level, (string) $message, $context);
        }

        $this->monolog->log($level, $message, $context);
    }

    private function consoleLog(mixed $level, string $message, array $context): void
    {
        $levelStr = strtoupper(is_string($level) ? $level : ($level->name ?? (string) $level));
        $color = self::$levelColors[$levelStr] ?? "\033[0;37m";
        $prefix = $this->consolePrefix ? "\033[1m{$this->consolePrefix}\033[0m " : '';

        echo "{$prefix}{$color}[{$levelStr}]\033[0m {$message}" . PHP_EOL;

        if (!empty($context)) {
            $cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
            (new SimpleCliDumper())->dump($cloner->cloneVar($context));
        }
    }
}
