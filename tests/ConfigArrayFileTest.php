<?php

namespace PressGang\Capstan\Tests;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Support\ConfigArrayFile;

class ConfigArrayFileTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/capstan-config-' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testReadReturnsTheArrayOrNull(): void
    {
        $this->assertNull(ConfigArrayFile::read($this->file));

        file_put_contents($this->file, "<?php\nreturn [ 'a' => 1 ];\n");
        $this->assertSame(['a' => 1], ConfigArrayFile::read($this->file));

        file_put_contents($this->file, "<?php\nreturn 'not an array';\n");
        $this->assertNull(ConfigArrayFile::read($this->file));
    }

    public function testAppendInsertsBeforeTheClosingBracket(): void
    {
        file_put_contents($this->file, "<?php\n\nreturn [\n\t'a' => 1,\n];\n");

        $this->assertTrue(ConfigArrayFile::append($this->file, "\t'b' => 2,"));
        $this->assertSame(['a' => 1, 'b' => 2], ConfigArrayFile::read($this->file));
    }

    public function testAppendRefusesUnrecognisableFiles(): void
    {
        file_put_contents($this->file, "<?php\nreturn array( 'a' => 1 );\n");

        $this->assertFalse(ConfigArrayFile::append($this->file, "\t'b' => 2,"));
        $this->assertSame(['a' => 1], ConfigArrayFile::read($this->file));
    }

    public function testCreateProducesAValidConfigFile(): void
    {
        $this->assertTrue(ConfigArrayFile::create($this->file, "Header\n\nMore.", "\t'event' => [ 'public' => true ],"));

        $this->assertSame(['event' => ['public' => true]], ConfigArrayFile::read($this->file));
        $this->assertStringContainsString(' * Header', (string) file_get_contents($this->file));
    }

    public function testCreatedFilesAcceptSubsequentAppends(): void
    {
        ConfigArrayFile::create($this->file, 'Header', "\t'a' => 1,");

        $this->assertTrue(ConfigArrayFile::append($this->file, "\t'b' => 2,"));
        $this->assertSame(['a' => 1, 'b' => 2], ConfigArrayFile::read($this->file));
    }
}
