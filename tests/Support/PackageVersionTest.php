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

    public function testNormalizeTreatsPlaceholderAndEmptyAsUnknown(): void
    {
        // Composer's root-package placeholder and an empty string are "unknown".
        $this->assertSame('dev', PackageVersion::normalize('1.0.0+no-version-set'));
        $this->assertSame('dev', PackageVersion::normalize(''));
        $this->assertSame('0.0.0', PackageVersion::normalize('', '0.0.0'));
    }

    public function testNormalizePassesRealVersionsThrough(): void
    {
        // A resolved tag or branch alias is a real answer — never overridden.
        $this->assertSame('v0.3.0', PackageVersion::normalize('v0.3.0'));
        $this->assertSame('dev-main', PackageVersion::normalize('dev-main'));
    }
}
