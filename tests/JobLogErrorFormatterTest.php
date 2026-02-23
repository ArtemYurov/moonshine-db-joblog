<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\MoonShine\Traits\JobLogErrorFormatter;
use PHPUnit\Framework\TestCase;

class JobLogErrorFormatterTest extends TestCase
{
    private object $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        // Create anonymous class using the trait
        $this->formatter = new class {
            use JobLogErrorFormatter {
                formatLastError as public;
                formatMessage as public;
            }
        };
    }

    public function test_format_last_error_null_returns_empty(): void
    {
        $this->assertSame('', $this->formatter->formatLastError(null));
    }

    public function test_format_last_error_null_returns_custom_message(): void
    {
        $this->assertSame('Нет ошибок', $this->formatter->formatLastError(null, 100, 'Нет ошибок'));
    }

    public function test_format_last_error_short_message(): void
    {
        $record = (object) ['message' => 'Ошибка подключения'];
        $this->assertSame('Ошибка подключения', $this->formatter->formatLastError($record));
    }

    public function test_format_last_error_long_message_truncated(): void
    {
        $record = (object) ['message' => str_repeat('A', 200)];
        $result = $this->formatter->formatLastError($record, 100);

        $this->assertSame(103, mb_strlen($result)); // 100 + '...'
        $this->assertStringEndsWith('...', $result);
    }

    public function test_format_message_short(): void
    {
        $this->assertSame('hello', $this->formatter->formatMessage('hello', 100));
    }

    public function test_format_message_exact_length(): void
    {
        $message = str_repeat('X', 100);
        $this->assertSame($message, $this->formatter->formatMessage($message, 100));
    }

    public function test_format_message_truncated(): void
    {
        $message = str_repeat('X', 101);
        $result = $this->formatter->formatMessage($message, 100);
        $this->assertSame(103, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function test_format_message_custom_max_length(): void
    {
        $message = 'Длинное сообщение об ошибке';
        $result = $this->formatter->formatMessage($message, 10);
        $this->assertSame(13, mb_strlen($result)); // 10 + '...'
    }
}
