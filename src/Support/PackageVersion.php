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
            $version = (string) \Composer\InstalledVersions::getPrettyVersion($package);

            if ($version !== '' && ! str_contains($version, 'no-version-set')) {
                return $version;
            }
        }

        return $fallback;
    }
}
