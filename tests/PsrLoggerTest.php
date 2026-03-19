<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Logger\PsrLogger;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for PsrLogger — PSR-3 compliance, Monolog delegation, console prefix
 */
class PsrLoggerTest extends TestCase
{
    private TestHandler $handler;
    private PsrLogger $psrLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new TestHandler();
        $monolog = new Logger('test', [$this->handler]);
        $this->psrLogger = new PsrLogger($monolog);
    }

    public function test_implements_psr3_logger_interface(): void
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->psrLogger);
    }

    public function test_info_delegates_to_monolog(): void
    {
        $this->psrLogger->info('test message');

        $this->assertTrue($this->handler->hasInfoRecords());
        $this->assertTrue($this->handler->hasInfo('test message'));
    }

    public function test_error_delegates_to_monolog(): void
    {
        $this->psrLogger->error('error occurred');

        $this->assertTrue($this->handler->hasErrorRecords());
        $this->assertTrue($this->handler->hasError('error occurred'));
    }

    public function test_warning_delegates_to_monolog(): void
    {
        $this->psrLogger->warning('be careful');

        $this->assertTrue($this->handler->hasWarningRecords());
        $this->assertTrue($this->handler->hasWarning('be careful'));
    }

    public function test_debug_delegates_to_monolog(): void
    {
        $this->psrLogger->debug('debug info');

        $this->assertTrue($this->handler->hasDebugRecords());
        $this->assertTrue($this->handler->hasDebug('debug info'));
    }

    public function test_all_psr3_levels_delegate_to_monolog(): void
    {
        $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

        foreach ($levels as $level) {
            $handler = new TestHandler();
            $monolog = new Logger('test', [$handler]);
            $logger = new PsrLogger($monolog);

            $logger->$level("message at {$level}");

            $this->assertTrue(
                $handler->hasRecordThatContains("message at {$level}", Level::fromName($level)),
                "Failed asserting that {$level} delegates to Monolog"
            );
        }
    }

    public function test_context_is_passed_to_monolog(): void
    {
        $context = ['key' => 'value', 'count' => 42];
        $this->psrLogger->info('with context', $context);

        $records = $this->handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('value', $records[0]->context['key']);
        $this->assertSame(42, $records[0]->context['count']);
    }

    public function test_log_method_with_string_level(): void
    {
        $this->psrLogger->log('info', 'via log method');

        $this->assertTrue($this->handler->hasInfo('via log method'));
    }

    public function test_prefix_does_not_affect_monolog_message(): void
    {
        $handler = new TestHandler();
        $monolog = new Logger('test', [$handler]);
        $logger = new PsrLogger($monolog, '[STEP: load]');

        $logger->info('step message');

        // Monolog message should NOT contain the prefix
        $records = $handler->getRecords();
        $this->assertSame('step message', $records[0]->message);
    }

    public function test_stringable_message(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable message';
            }
        };

        $this->psrLogger->info($stringable);

        $this->assertTrue($this->handler->hasInfo('stringable message'));
    }

    public function test_console_output_disabled_does_not_echo(): void
    {
        $handler = new TestHandler();
        $monolog = new Logger('test', [$handler]);
        $logger = new PsrLogger($monolog, consoleOutput: false);

        ob_start();
        $logger->info('silent message');
        $output = ob_get_clean();

        $this->assertEmpty($output);
        $this->assertTrue($handler->hasInfo('silent message'));
    }

    public function test_console_output_enabled_produces_echo(): void
    {
        $handler = new TestHandler();
        $monolog = new Logger('test', [$handler]);
        $logger = new PsrLogger($monolog, consoleOutput: true);

        ob_start();
        $logger->info('visible message');
        $output = ob_get_clean();

        $this->assertStringContainsString('visible message', $output);
        $this->assertStringContainsString('[INFO]', $output);
    }

    public function test_console_output_with_prefix(): void
    {
        $handler = new TestHandler();
        $monolog = new Logger('test', [$handler]);
        $logger = new PsrLogger($monolog, '[STEP: test]', consoleOutput: true);

        ob_start();
        $logger->info('prefixed message');
        $output = ob_get_clean();

        $this->assertStringContainsString('[STEP: test]', $output);
        $this->assertStringContainsString('prefixed message', $output);
    }

    public function test_default_console_output_is_false(): void
    {
        $handler = new TestHandler();
        $monolog = new Logger('test', [$handler]);
        $logger = new PsrLogger($monolog);

        ob_start();
        $logger->info('default silent');
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }
}
