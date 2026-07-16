<?php

namespace PressGang\Capstan\Tests\Support;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Support\PackageVersion;

class PackageVersionTest extends TestCase
{
    public function testResolvesAnInstalledPackage(): void
    {
        // phpunit is a dev dependency, so it always has a real resolved version.
        $version = PackageVersion::of('phpunit/phpunit');

        $this->assertNotSame('dev', $version);
        $this->assertStringNotContainsString('no-version-set', $version);
    }

    public function testFallsBackForAnUnknownPackage(): void
    {
        $this->assertSame('dev', PackageVersion::of('nope/nope'));
        $this->assertSame('0.0.0', PackageVersion::of('nope/nope', '0.0.0'));
    }

    public function testTreatsNoVersionSetPlaceholderAsUnknown(): void
    {
        // In its own working clone, capstan is the root package with no
        // derivable tag — the placeholder must become the fallback, not leak.
        $this->assertSame('dev', PackageVersion::of('pressgang-wp/capstan'));
    }
}
