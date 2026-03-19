<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Logger\PsrLogger;
use ArtemYurov\JobLog\Traits\JobLoggerMethods;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for getLoggerInterface() and PSR-3 delegation through JobLoggerMethods trait
 */
class GetLoggerInterfaceTest extends TestCase
{
    private TestHandler $handler;
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new TestHandler();
        $monolog = new Logger('test', [$this->handler]);

        // Anonymous class using the trait — avoids DB dependency of JobLogger/JobLoggerStep
        $this->subject = new class($monolog) {
            use JobLoggerMethods;

            public function __construct(Logger $monolog)
            {
                $this->psrLogger = new PsrLogger($monolog);
            }

            protected function getModel()
            {
                return null; // not needed for logging tests
            }
        };
    }

    public function test_get_logger_interface_returns_psr3(): void
    {
        $logger = $this->subject->getLoggerInterface();

        $this->assertInstanceOf(LoggerInterface::class, $logger);
        $this->assertInstanceOf(PsrLogger::class, $logger);
    }

    public function test_fluent_info_returns_self(): void
    {
        $result = $this->subject->info('test');

        $this->assertSame($this->subject, $result);
    }

    public function test_fluent_error_returns_self(): void
    {
        $result = $this->subject->error('test');

        $this->assertSame($this->subject, $result);
    }

    public function test_fluent_chain(): void
    {
        $result = $this->subject
            ->info('step 1')
            ->warning('step 2')
            ->debug('step 3');

        $this->assertSame($this->subject, $result);
        $this->assertCount(3, $this->handler->getRecords());
    }

    public function test_trait_info_delegates_to_psr_logger(): void
    {
        $this->subject->info('trait info');

        $this->assertTrue($this->handler->hasInfo('trait info'));
    }

    public function test_trait_error_delegates_to_psr_logger(): void
    {
        $this->subject->error('trait error');

        $this->assertTrue($this->handler->hasError('trait error'));
    }

    public function test_trait_all_levels_delegate(): void
    {
        $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

        foreach ($levels as $level) {
            $this->subject->$level("msg at {$level}");
        }

        $records = $this->handler->getRecords();
        $this->assertCount(8, $records);
    }

    public function test_trait_log_method_delegates(): void
    {
        $this->subject->log('info', 'via log');

        $this->assertTrue($this->handler->hasInfo('via log'));
    }

    public function test_trait_passes_context(): void
    {
        $this->subject->info('with ctx', ['foo' => 'bar']);

        $records = $this->handler->getRecords();
        $this->assertSame('bar', $records[0]->context['foo']);
    }

    public function test_get_logger_interface_writes_to_same_handler(): void
    {
        $psr = $this->subject->getLoggerInterface();

        // Write via PSR-3 interface
        $psr->info('via psr');

        // Write via fluent trait
        $this->subject->info('via trait');

        // Both should go to the same Monolog handler
        $records = $this->handler->getRecords();
        $this->assertCount(2, $records);
        $this->assertSame('via psr', $records[0]->message);
        $this->assertSame('via trait', $records[1]->message);
    }

    public function test_is_console_output_enabled_returns_false_without_laravel(): void
    {
        // Without Laravel app, config() and app() are unavailable
        $result = $this->callIsConsoleOutputEnabled();

        $this->assertFalse($result);
    }

    private function callIsConsoleOutputEnabled(): bool
    {
        $method = new \ReflectionMethod($this->subject, 'isConsoleOutputEnabled');
        $method->setAccessible(true);

        return $method->invoke($this->subject);
    }
}
