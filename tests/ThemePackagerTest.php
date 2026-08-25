<?php

namespace PressGang\Capstan\Tests;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Support\ThemePackager;

class ThemePackagerTest extends TestCase
{
    private ThemePackager $packager;

    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->packager = new ThemePackager();
        $this->fixtureDir = sys_get_temp_dir() . '/capstan-test-' . uniqid();
        mkdir($this->fixtureDir . '/my-theme', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixtureDir);
    }

    // --- Validation ---

    public function testValidatePassesWithStyleCss(): void
    {
        file_put_contents($this->fixtureDir . '/my-theme/style.css', '/* Theme */');

        $result = $this->packager->validate($this->fixtureDir . '/my-theme');

        $this->assertSame([], $result);
    }

    public function testValidateFailsWithoutStyleCss(): void
    {
        $result = $this->packager->validate($this->fixtureDir . '/my-theme');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('missing style.css', $result['error']);
    }

    public function testValidateFailsForNonexistentDirectory(): void
    {
        $result = $this->packager->validate($this->fixtureDir . '/nonexistent');

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('does not exist', $result['error']);
    }

    // --- Slug Derivation ---

    public function testDeriveSlugReturnsBasename(): void
    {
        $this->assertSame('my-theme', $this->packager->deriveSlug('/path/to/my-theme'));
        $this->assertSame('starter', $this->packager->deriveSlug('/var/www/starter'));
    }

    // --- File Collection ---

    public function testCollectFilesIncludesStandardFiles(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/functions.php', '<?php');
        mkdir($themeDir . '/src', 0755);
        file_put_contents($themeDir . '/src/Theme.php', '<?php');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertContains('style.css', $files);
        $this->assertContains('functions.php', $files);
        $this->assertContains('src/Theme.php', $files);
    }

    public function testCollectFilesExcludesGitDirectory(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        mkdir($themeDir . '/.git', 0755);
        file_put_contents($themeDir . '/.git/config', 'git config');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('.git/config', $files);
    }

    public function testCollectFilesExcludesVendorDirectory(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        mkdir($themeDir . '/vendor/autoload', 0755, true);
        file_put_contents($themeDir . '/vendor/autoload.php', '<?php');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('vendor/autoload.php', $files);
    }

    public function testCollectFilesExcludesNodeModules(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        mkdir($themeDir . '/node_modules/package', 0755, true);
        file_put_contents($themeDir . '/node_modules/package/index.js', '');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('node_modules/package/index.js', $files);
    }

    public function testCollectFilesExcludesReleaseDirectory(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        mkdir($themeDir . '/release', 0755);
        file_put_contents($themeDir . '/release/old.zip', '');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('release/old.zip', $files);
    }

    public function testCollectFilesExcludesEditorDirectories(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        mkdir($themeDir . '/.idea', 0755);
        file_put_contents($themeDir . '/.idea/workspace.xml', '');
        mkdir($themeDir . '/.vscode', 0755);
        file_put_contents($themeDir . '/.vscode/settings.json', '');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('.idea/workspace.xml', $files);
        $this->assertNotContains('.vscode/settings.json', $files);
    }

    public function testCollectFilesExcludesTestsDirectory(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        mkdir($themeDir . '/tests', 0755);
        file_put_contents($themeDir . '/tests/SomeTest.php', '<?php');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('tests/SomeTest.php', $files);
    }

    public function testCollectFilesExcludesGithubDirectory(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        mkdir($themeDir . '/.github/workflows', 0755, true);
        file_put_contents($themeDir . '/.github/workflows/ci.yml', '');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('.github/workflows/ci.yml', $files);
    }

    public function testCollectFilesExcludesHuskyDirectory(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        mkdir($themeDir . '/.husky', 0755);
        file_put_contents($themeDir . '/.husky/pre-commit', '#!/bin/sh');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('.husky/pre-commit', $files);
    }

    public function testCollectFilesExcludesDsStore(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/.DS_Store', '');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('.DS_Store', $files);
    }

    public function testCollectFilesExcludesSwapFiles(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/style.css.swp', '');
        file_put_contents($themeDir . '/functions.php.swo', '');
        file_put_contents($themeDir . '/notes.tmp', '');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('style.css.swp', $files);
        $this->assertNotContains('functions.php.swo', $files);
        $this->assertNotContains('notes.tmp', $files);
    }

    public function testCollectFilesExcludesComposerLock(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/composer.lock', '{}');
        file_put_contents($themeDir . '/composer.phar', '');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('composer.lock', $files);
        $this->assertNotContains('composer.phar', $files);
    }

    public function testCollectFilesExcludesEnvFiles(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/.env', 'SECRET=abc');
        file_put_contents($themeDir . '/.env.local', 'SECRET=local');
        file_put_contents($themeDir . '/.env.production', 'SECRET=prod');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('.env', $files);
        $this->assertNotContains('.env.local', $files);
        $this->assertNotContains('.env.production', $files);
    }

    public function testCollectFilesExcludesGitMetadataFiles(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/.gitignore', 'vendor/');
        file_put_contents($themeDir . '/.gitattributes', '* text=auto');
        file_put_contents($themeDir . '/.editorconfig', 'root = true');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('.gitignore', $files);
        $this->assertNotContains('.gitattributes', $files);
        $this->assertNotContains('.editorconfig', $files);
    }

    public function testCollectFilesExcludesJsToolingFiles(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/package.json', '{}');
        file_put_contents($themeDir . '/package-lock.json', '{}');
        file_put_contents($themeDir . '/yarn.lock', '');
        file_put_contents($themeDir . '/pnpm-lock.yaml', '');
        file_put_contents($themeDir . '/webpack.config.js', '');
        file_put_contents($themeDir . '/vite.config.js', '');
        file_put_contents($themeDir . '/tsconfig.json', '{}');
        file_put_contents($themeDir . '/tailwind.config.js', '');
        file_put_contents($themeDir . '/postcss.config.js', '');
        file_put_contents($themeDir . '/.browserslistrc', '');
        file_put_contents($themeDir . '/.nvmrc', '18');
        file_put_contents($themeDir . '/.node-version', '18');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('package.json', $files);
        $this->assertNotContains('package-lock.json', $files);
        $this->assertNotContains('yarn.lock', $files);
        $this->assertNotContains('pnpm-lock.yaml', $files);
        $this->assertNotContains('webpack.config.js', $files);
        $this->assertNotContains('vite.config.js', $files);
        $this->assertNotContains('tsconfig.json', $files);
        $this->assertNotContains('tailwind.config.js', $files);
        $this->assertNotContains('postcss.config.js', $files);
        $this->assertNotContains('.browserslistrc', $files);
        $this->assertNotContains('.nvmrc', $files);
        $this->assertNotContains('.node-version', $files);
    }

    public function testCollectFilesExcludesRcConfigFiles(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/.eslintrc', '{}');
        file_put_contents($themeDir . '/.eslintrc.js', '');
        file_put_contents($themeDir . '/.eslintignore', '');
        file_put_contents($themeDir . '/.stylelintrc', '{}');
        file_put_contents($themeDir . '/.stylelintrc.json', '{}');
        file_put_contents($themeDir . '/.prettierrc', '{}');
        file_put_contents($themeDir . '/.prettierrc.js', '');
        file_put_contents($themeDir . '/.prettierignore', '');
        file_put_contents($themeDir . '/.babelrc', '{}');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('.eslintrc', $files);
        $this->assertNotContains('.eslintrc.js', $files);
        $this->assertNotContains('.eslintignore', $files);
        $this->assertNotContains('.stylelintrc', $files);
        $this->assertNotContains('.stylelintrc.json', $files);
        $this->assertNotContains('.prettierrc', $files);
        $this->assertNotContains('.prettierrc.js', $files);
        $this->assertNotContains('.prettierignore', $files);
        $this->assertNotContains('.babelrc', $files);
    }

    public function testCollectFilesExcludesPhpDevToolingFiles(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/phpunit.xml', '');
        file_put_contents($themeDir . '/phpunit.xml.dist', '');
        file_put_contents($themeDir . '/phpcs.xml', '');
        file_put_contents($themeDir . '/phpstan-bootstrap.php', '<?php');
        file_put_contents($themeDir . '/phpstan.neon', '');
        file_put_contents($themeDir . '/phpstan.neon.dist', '');
        file_put_contents($themeDir . '/.php-cs-fixer.php', '<?php');
        mkdir($themeDir . '/phpstan-stubs');
        file_put_contents($themeDir . '/phpstan-stubs/runtime.stub', '<?php');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('phpunit.xml', $files);
        $this->assertNotContains('phpunit.xml.dist', $files);
        $this->assertNotContains('phpcs.xml', $files);
        $this->assertNotContains('phpstan-bootstrap.php', $files);
        $this->assertNotContains('phpstan.neon', $files);
        $this->assertNotContains('phpstan.neon.dist', $files);
        $this->assertNotContains('phpstan-stubs/runtime.stub', $files);
        $this->assertNotContains('.php-cs-fixer.php', $files);
    }

    public function testCollectFilesExcludesContainerAndCiFiles(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/Dockerfile', 'FROM php:8.2');
        file_put_contents($themeDir . '/docker-compose.yml', '');
        file_put_contents($themeDir . '/.dockerignore', '');
        file_put_contents($themeDir . '/.travis.yml', '');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('Dockerfile', $files);
        $this->assertNotContains('docker-compose.yml', $files);
        $this->assertNotContains('.dockerignore', $files);
        $this->assertNotContains('.travis.yml', $files);
    }

    public function testCollectFilesExcludesAiAgentFiles(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/AGENTS.md', '# Agent Guide');
        file_put_contents($themeDir . '/CLAUDE.md', '# Claude Guide');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('AGENTS.md', $files);
        $this->assertNotContains('CLAUDE.md', $files);
    }

    public function testCollectFilesExcludesLogFiles(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/npm-debug.log', '');
        file_put_contents($themeDir . '/yarn-debug.log', '');
        file_put_contents($themeDir . '/yarn-error.log', '');
        file_put_contents($themeDir . '/pnpm-debug.log', '');

        $files = $this->packager->collectFiles($themeDir);

        $this->assertNotContains('npm-debug.log', $files);
        $this->assertNotContains('yarn-debug.log', $files);
        $this->assertNotContains('yarn-error.log', $files);
        $this->assertNotContains('pnpm-debug.log', $files);
    }

    public function testCollectFilesReturnsSortedOutput(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        file_put_contents($themeDir . '/functions.php', '<?php');
        file_put_contents($themeDir . '/composer.json', '{}');
        mkdir($themeDir . '/src', 0755);
        file_put_contents($themeDir . '/src/Theme.php', '<?php');

        $files = $this->packager->collectFiles($themeDir);
        $sorted = $files;
        sort($sorted);

        $this->assertSame($sorted, $files);
    }

    // --- Packaging ---

    public function testPackageCreatesValidZip(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme Name: My Theme */');
        file_put_contents($themeDir . '/functions.php', '<?php // functions');

        $files = $this->packager->collectFiles($themeDir);
        $outputPath = $this->fixtureDir . '/my-theme.zip';

        $count = $this->packager->package($themeDir, $outputPath, $files);

        $this->assertSame(2, $count);
        $this->assertFileExists($outputPath);

        $zip = new \ZipArchive();
        $zip->open($outputPath);
        $this->assertSame(2, $zip->numFiles);
        $zip->close();
    }

    public function testPackageUsesSingleTopLevelDirectory(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');
        mkdir($themeDir . '/src', 0755);
        file_put_contents($themeDir . '/src/Theme.php', '<?php');

        $files = $this->packager->collectFiles($themeDir);
        $outputPath = $this->fixtureDir . '/my-theme.zip';

        $this->packager->package($themeDir, $outputPath, $files);

        $zip = new \ZipArchive();
        $zip->open($outputPath);

        $topLevelDirs = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $topLevelDirs[] = explode('/', $name)[0];
        }

        $zip->close();

        $this->assertSame(['my-theme'], array_unique($topLevelDirs));
    }

    public function testPackageThrowsOnBadOutputPath(): void
    {
        $themeDir = $this->fixtureDir . '/my-theme';
        file_put_contents($themeDir . '/style.css', '/* Theme */');

        $files = $this->packager->collectFiles($themeDir);

        $this->expectException(\RuntimeException::class);
        $this->packager->package($themeDir, '/nonexistent/dir/output.zip', $files);
    }

    // --- Helpers ---

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
