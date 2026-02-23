<?php

declare(strict_types=1);

namespace ArtemYurov\JobLog\MoonShine\Traits;

trait JobLogErrorFormatter
{
    protected function formatLastError(?object $errorRecord, int $maxLength = 100, string $emptyMessage = ''): string
    {
        if (!$errorRecord) {
            return $emptyMessage;
        }

        return $this->formatMessage($errorRecord->message, $maxLength);
    }

    protected function formatMessage(string $message, int $maxLength = 100): string
    {
        return mb_strlen($message) > $maxLength ? mb_substr($message, 0, $maxLength) . '...' : $message;
    }
}
