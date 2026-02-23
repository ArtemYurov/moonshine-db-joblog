<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Logger\DatabaseHandler;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DatabaseHandler — setters/getters, inheritance
 */
class DatabaseHandlerTest extends TestCase
{
    public function test_extends_abstract_processing_handler(): void
    {
        $handler = new DatabaseHandler(Level::Debug, true);
        $this->assertInstanceOf(AbstractProcessingHandler::class, $handler);
    }

    public function test_set_and_get_step(): void
    {
        $handler = new DatabaseHandler(Level::Debug, true);

        $this->assertNull($handler->getStep());

        $handler->setStep('load_data');
        $this->assertSame('load_data', $handler->getStep());

        $handler->setStep(null);
        $this->assertNull($handler->getStep());
    }

    public function test_default_level_is_debug(): void
    {
        $handler = new DatabaseHandler(Level::Debug, true);

        // Handler should process records at Debug level and above
        $this->assertTrue($handler->isHandling(new \Monolog\LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Debug,
            message: 'test',
        )));
    }

    public function test_handles_all_levels_above_debug(): void
    {
        $handler = new DatabaseHandler(Level::Debug, true);

        foreach (Level::cases() as $level) {
            $this->assertTrue($handler->isHandling(new \Monolog\LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'test',
                level: $level,
                message: 'test',
            )));
        }
    }
}
