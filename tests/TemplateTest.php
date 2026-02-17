<?php

namespace PressGang\Capstan\Tests;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Support\Filesystem;
use PressGang\Capstan\Support\Templates\PresetLoader;
use PressGang\Capstan\Support\Templates\TemplateApplier;
use PressGang\Capstan\Support\Tokens;

class TemplateTest extends TestCase
{
    private string $presetDir;

    protected function setUp(): void
    {
        $this->presetDir = dirname(__DIR__) . '/templates/child';
    }

    // --- Slug validation ---

    public function testValidSlugs(): void
    {
        $valid = ['my-theme', 'starter', 'theme-2026', 'a', 'foo-bar-baz'];

        foreach ($valid as $slug) {
            $this->assertMatchesRegularExpression(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug,
                "Slug '{$slug}' should be valid."
            );
        }
    }

    public function testInvalidSlugs(): void
    {
        $invalid = ['-leading', 'trailing-', 'UPPER', 'has space', 'under_score', 'double--hyphen', ''];

        foreach ($invalid as $slug) {
            $this->assertDoesNotMatchRegularExpression(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug,
                "Slug '{$slug}' should be invalid."
            );
        }
    }

    // --- Token derivation ---

    public function testDeriveThemeName(): void
    {
        $this->assertSame('My Theme', Tokens::deriveThemeNameFromSlug('my-theme'));
        $this->assertSame('Starter', Tokens::deriveThemeNameFromSlug('starter'));
        $this->assertSame('Theme 2026', Tokens::deriveThemeNameFromSlug('theme-2026'));
    }

    public function testDeriveNamespace(): void
    {
        $this->assertSame('MyTheme', Tokens::deriveNamespaceFromSlug('my-theme'));
        $this->assertSame('Starter', Tokens::deriveNamespaceFromSlug('starter'));
        $this->assertSame('Theme2026', Tokens::deriveNamespaceFromSlug('theme-2026'));
    }

    // --- Preset loading ---

    public function testPresetLoaderLoadsManifest(): void
    {
        $preset = PresetLoader::load($this->presetDir);

        $this->assertContains('php', $preset->textExtensions);
        $this->assertContains('css', $preset->textExtensions);
        $this->assertContains('json', $preset->textExtensions);
        $this->assertContains('scss', $preset->textExtensions);
        $this->assertContains('pot', $preset->textExtensions);
        $this->assertArrayHasKey('lang/theme.pot', $preset->renameMap);
        $this->assertSame('lang/{{textdomain}}.pot', $preset->renameMap['lang/theme.pot']);
        $this->assertSame('pressgang', $preset->defaults['pressgang_parent_slug']);
    }

    public function testPresetLoaderThrowsOnMissingManifest(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Preset manifest not found');
        PresetLoader::load('/nonexistent/path');
    }

    public function testResolveBuiltinPath(): void
    {
        $path = PresetLoader::resolveBuiltinPath('child');
        $this->assertSame($this->presetDir, $path);
        $this->assertDirectoryExists($path . '/theme');
    }

    // --- Template applier plan ---

    public function testPlanReturnsCorrectFileList(): void
    {
        $preset = PresetLoader::load($this->presetDir);
        $applier = new TemplateApplier($preset, new Filesystem());

        $tokens = [
            'theme_slug' => 'my-theme',
            'theme_name' => 'My Theme',
            'theme_namespace' => 'MyTheme',
            'textdomain' => 'my-theme',
            'pressgang_parent_slug' => 'pressgang',
        ];

        $plan = $applier->plan($tokens, '/tmp/test-target');

        $relativePaths = array_column($plan, 'relativePath');

        // Key files must be present
        $this->assertContains('style.css', $relativePaths);
        $this->assertContains('functions.php', $relativePaths);
        $this->assertContains('composer.json', $relativePaths);
        $this->assertContains('index.php', $relativePaths);
        $this->assertContains('theme.json', $relativePaths);
        $this->assertContains('views/front-page.twig', $relativePaths);
        $this->assertContains('scss/styles.scss', $relativePaths);

        // .gitkeep files
        $this->assertContains('config/.gitkeep', $relativePaths);
        $this->assertContains('src/.gitkeep', $relativePaths);
    }

    public function testPlanAppliesRenameMap(): void
    {
        $preset = PresetLoader::load($this->presetDir);
        $applier = new TemplateApplier($preset, new Filesystem());

        $tokens = [
            'theme_slug' => 'my-theme',
            'theme_name' => 'My Theme',
            'theme_namespace' => 'MyTheme',
            'textdomain' => 'my-theme',
            'pressgang_parent_slug' => 'pressgang',
        ];

        $plan = $applier->plan($tokens, '/tmp/test-target');
        $relativePaths = array_column($plan, 'relativePath');

        // Renamed: lang/theme.pot -> lang/my-theme.pot
        $this->assertContains('lang/my-theme.pot', $relativePaths);
        $this->assertNotContains('lang/theme.pot', $relativePaths);
    }

    public function testPlanMarksTokenizedFiles(): void
    {
        $preset = PresetLoader::load($this->presetDir);
        $applier = new TemplateApplier($preset, new Filesystem());

        $tokens = [
            'theme_slug' => 'test',
            'theme_name' => 'Test',
            'theme_namespace' => 'Test',
            'textdomain' => 'test',
            'pressgang_parent_slug' => 'pressgang',
        ];

        $plan = $applier->plan($tokens, '/tmp/test-target');
        $indexed = [];
        foreach ($plan as $entry) {
            $indexed[$entry['relativePath']] = $entry['tokenized'];
        }

        $this->assertTrue($indexed['style.css']);
        $this->assertTrue($indexed['functions.php']);
        $this->assertTrue($indexed['composer.json']);
        $this->assertTrue($indexed['theme.json']); // .json is in text_extensions
        $this->assertFalse($indexed['config/.gitkeep']); // no extension match
    }

    // --- Token replacement ---

    public function testTokenReplacement(): void
    {
        $preset = PresetLoader::load($this->presetDir);
        $applier = new TemplateApplier($preset, new Filesystem());

        $tokens = [
            'theme_slug' => 'my-theme',
            'theme_name' => 'My Theme',
        ];

        $input = "Theme Name: {{theme_name}}\nSlug: {{theme_slug}}\nStatic: untouched";
        $output = $applier->replaceTokens($input, $tokens);

        $this->assertSame("Theme Name: My Theme\nSlug: my-theme\nStatic: untouched", $output);
    }

    public function testTokenReplacementLeavesUnknownTokens(): void
    {
        $preset = PresetLoader::load($this->presetDir);
        $applier = new TemplateApplier($preset, new Filesystem());

        $output = $applier->replaceTokens('{{unknown_token}}', ['theme_slug' => 'test']);

        $this->assertSame('{{unknown_token}}', $output);
    }
}
