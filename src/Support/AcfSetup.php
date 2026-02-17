<?php

namespace PressGang\Capstan\Support;

class AcfSetup
{
    /**
     * Build a root composer.json for ACF Pro as an MU-plugin.
     *
     * Configures the ACF Pro Composer repository, requires the package via
     * composer/installers, and maps it to wp-content/mu-plugins/.
     *
     * @param string $slug Project slug used in the package name.
     * @param string $name Human-readable project name for the description.
     * @return string JSON-encoded composer.json content.
     */
    public static function composerJson(string $slug, string $name): string
    {
        $composer = [
            'name' => 'pressgang-wp/' . $slug . '-project',
            'description' => 'WordPress project for ' . $name,
            'type' => 'project',
            'repositories' => [
                [
                    'type' => 'composer',
                    'url' => 'https://connect.advancedcustomfields.com',
                ],
            ],
            'require' => [
                'composer/installers' => '^2.0',
                'wpengine/advanced-custom-fields-pro' => '^6.0',
            ],
            'extra' => [
                'installer-paths' => [
                    'wp-content/mu-plugins/{$name}/' => [
                        'wpengine/advanced-custom-fields-pro',
                    ],
                ],
            ],
            'config' => [
                'allow-plugins' => [
                    'composer/installers' => true,
                ],
            ],
        ];

        return json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * Return the MU-plugin loader content for ACF Pro.
     *
     * WordPress does not auto-load MU-plugins from subdirectories, so this
     * loader bridges the gap for Composer-installed packages.
     *
     * @return string PHP source for the loader file.
     */
    public static function loaderContent(): string
    {
        return <<<'PHP'
<?php
/**
 * Plugin Name: ACF Pro Loader
 * Description: Loads ACF Pro from the Composer-managed mu-plugins directory.
 */

if (file_exists(__DIR__ . '/advanced-custom-fields-pro/acf.php')) {
    require_once __DIR__ . '/advanced-custom-fields-pro/acf.php';
}
PHP;
    }
}
