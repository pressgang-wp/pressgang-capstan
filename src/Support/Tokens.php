<?php

declare(strict_types=1);

namespace PressGang\Capstan\Support;

class Tokens
{
    /**
     * Convert a slug like "my-theme" to "My Theme".
     */
    public static function deriveThemeNameFromSlug(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }

    /**
     * Convert a slug like "my-theme" to "MyTheme".
     */
    public static function deriveNamespaceFromSlug(string $slug): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
    }
}
