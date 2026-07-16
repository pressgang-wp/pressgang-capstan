<?php

namespace PressGang\Capstan\Tests\Support;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Support\LogReader;

class LogReaderTest extends TestCase
{
    private string $log;

    protected function setUp(): void
    {
        $this->log = sys_get_temp_dir() . '/capstan-log-' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (is_file($this->log)) {
            unlink($this->log);
        }
    }

    public function testReportsMissingLogWithoutError(): void
    {
        $result = (new LogReader($this->log))->tail();

        $this->assertFalse($result['exists']);
        $this->assertSame([], $result['lines']);
        $this->assertSame($this->log, $result['path']);
    }

    public function testReturnsTrailingLines(): void
    {
        file_put_contents($this->log, implode("\n", ['one', 'two', 'three', 'four']) . "\n");

        $result = (new LogReader($this->log))->tail(2);

        $this->assertTrue($result['exists']);
        $this->assertSame(['three', 'four'], $result['lines']);
    }

    public function testClampsLineCountToAtLeastOne(): void
    {
        file_put_contents($this->log, "a\nb\n");

        $this->assertSame(['b'], (new LogReader($this->log))->tail(0)['lines']);
    }
}
