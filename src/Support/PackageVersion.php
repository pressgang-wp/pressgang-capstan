<?php

namespace PressGang\Capstan\Support;

/**
 * Resolves a composer package's version from the installed metadata — the git
 * tag Composer actually resolved — so nothing has to be hand-maintained or kept
 * in sync with a `version` field.
 *
 * Composer's root-package placeholder ("…+no-version-set", emitted for a dev
 * checkout with no derivable tag) is treated as "unknown" and yields the
 * fallback, so callers never surface it.
 */
final class PackageVersion
{
    public static function of(string $package, string $fallback = 'dev'): string
    {
        if (
            class_exists(\Composer\InstalledVersions::class)
            && \Composer\InstalledVersions::isInstalled($package)
        ) {
            return self::normalize((string) \Composer\InstalledVersions::getPrettyVersion($package), $fallback);
        }

        return $fallback;
    }

    /**
     * Treat an empty string or Composer's root-package placeholder
     * ("…+no-version-set") as unknown, yielding the fallback. A real resolved
     * version — a tag like `v0.3.0`, or a branch alias like `dev-main` — passes
     * through unchanged.
     */
    public static function normalize(string $version, string $fallback = 'dev'): string
    {
        return $version !== '' && ! str_contains($version, 'no-version-set') ? $version : $fallback;
    }
}
