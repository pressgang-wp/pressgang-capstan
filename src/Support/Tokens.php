<?php

namespace PressGang\Capstan\Support;

class Tokens
{
    /**
     * Convert a slug like "my-theme" to "My Theme".
     *
     * @param string $slug Kebab-case slug.
     * @return string Title-cased name with spaces.
     */
    public static function deriveThemeNameFromSlug(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }

    /**
     * Convert a slug like "my-theme" to "MyTheme".
     *
     * @param string $slug Kebab-case slug.
     * @return string PascalCase namespace string.
     */
    public static function deriveNamespaceFromSlug(string $slug): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
    }

    /**
     * Check whether a string is a valid kebab-case slug.
     *
     * Valid slugs contain only lowercase letters, numbers, and hyphens,
     * with no leading, trailing, or consecutive hyphens.
     *
     * @param string $slug The string to validate.
     * @return bool True if the slug is valid kebab-case.
     */
    public static function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    /**
     * Check whether a string is a valid PHP namespace.
     *
     * Must be one or more PascalCase segments separated by backslashes.
     * Rejects values containing spaces, semicolons, or other characters
     * that would produce invalid PHP when injected into autoload config.
     *
     * @param string $namespace The string to validate.
     * @return bool True if the namespace is valid.
     */
    public static function isValidNamespace(string $namespace): bool
    {
        return (bool) preg_match('/^[A-Z][A-Za-z0-9]*(\\\\[A-Z][A-Za-z0-9]*)*$/', $namespace);
    }
}
