<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Support\SimpleCliDumper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class SimpleCliDumperTest extends TestCase
{
    public function test_extends_cli_dumper(): void
    {
        $dumper = new SimpleCliDumper();
        $this->assertInstanceOf(CliDumper::class, $dumper);
    }

    public function test_control_chars_map_has_expected_keys(): void
    {
        $reflection = new \ReflectionClass(SimpleCliDumper::class);
        $property = $reflection->getProperty('controlCharsMap');
        $property->setAccessible(true);
        $map = $property->getValue();

        $this->assertArrayHasKey("\t", $map);
        $this->assertArrayHasKey("\n", $map);
        $this->assertArrayHasKey("\r", $map);
        $this->assertArrayHasKey("\x00", $map);
    }

    public function test_tab_replaced_with_spaces(): void
    {
        $reflection = new \ReflectionClass(SimpleCliDumper::class);
        $property = $reflection->getProperty('controlCharsMap');
        $property->setAccessible(true);
        $map = $property->getValue();

        $this->assertSame('    ', $map["\t"]);
    }

    public function test_newline_and_carriage_return_are_empty(): void
    {
        $reflection = new \ReflectionClass(SimpleCliDumper::class);
        $property = $reflection->getProperty('controlCharsMap');
        $property->setAccessible(true);
        $map = $property->getValue();

        $this->assertSame('', $map["\n"]);
        $this->assertSame('', $map["\r"]);
        $this->assertSame('', $map["\x00"]);
    }

    public function test_can_dump_to_string(): void
    {
        $dumper = new SimpleCliDumper();
        $cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();

        $output = '';
        $dumper->setOutput(function ($line) use (&$output) {
            $output .= $line;
        });

        $dumper->dump($cloner->cloneVar(['key' => 'value']));

        $this->assertStringContainsString('key', $output);
        $this->assertStringContainsString('value', $output);
    }
}
