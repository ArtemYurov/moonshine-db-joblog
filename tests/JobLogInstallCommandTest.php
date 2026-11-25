<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Commands\JobLogInstallCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JobLogInstallCommandTest extends TestCase
{
    public static function envFiles(): array
    {
        return [
            'empty file' => ['', "JOBLOG_DB_CONNECTION=mongodb\n"],
            'appended at the end' => ["APP_NAME=x\nDB_CONNECTION=pgsql\n", "APP_NAME=x\nDB_CONNECTION=pgsql\nJOBLOG_DB_CONNECTION=mongodb\n"],
            'no trailing newline' => ['APP_NAME=x', "APP_NAME=x\nJOBLOG_DB_CONNECTION=mongodb\n"],
            'replaced in place' => ["A=1\nJOBLOG_DB_CONNECTION=old\nB=2\n", "A=1\nJOBLOG_DB_CONNECTION=mongodb\nB=2\n"],
            'empty value replaced' => ["JOBLOG_DB_CONNECTION=\nB=2\n", "JOBLOG_DB_CONNECTION=mongodb\nB=2\n"],
            'comment untouched' => ["# JOBLOG_DB_CONNECTION=x\nA=1\n", "# JOBLOG_DB_CONNECTION=x\nA=1\nJOBLOG_DB_CONNECTION=mongodb\n"],
            'similar key untouched' => ["JOBLOG_DB_CONNECTION_X=1\n", "JOBLOG_DB_CONNECTION_X=1\nJOBLOG_DB_CONNECTION=mongodb\n"],
        ];
    }

    #[DataProvider('envFiles')]
    public function test_with_env_line(string $content, string $expected): void
    {
        $result = JobLogInstallCommand::withEnvLine($content, 'JOBLOG_DB_CONNECTION', 'mongodb');

        $this->assertSame($expected, $result);
        $this->assertSame($result, JobLogInstallCommand::withEnvLine($result, 'JOBLOG_DB_CONNECTION', 'mongodb'), 'a repeated call changes nothing');
    }

    public function test_value_is_inserted_literally(): void
    {
        $result = JobLogInstallCommand::withEnvLine("JOBLOG_DB_CONNECTION=old\n", 'JOBLOG_DB_CONNECTION', 'a$1b\0');

        $this->assertSame('JOBLOG_DB_CONNECTION=a$1b\0' . "\n", $result);
        $this->assertSame($result, JobLogInstallCommand::withEnvLine($result, 'JOBLOG_DB_CONNECTION', 'a$1b\0'), 'a repeated call changes nothing');
    }
}
