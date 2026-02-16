<?php

declare(strict_types=1);

namespace PressGang\Capstan\Tests;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\CapstanCommand;
use PressGang\Capstan\Support\ContextResolver;
use PressGang\Capstan\Support\Tokens;

class ApplicationTest extends TestCase
{
    public function testVersion(): void
    {
        $this->assertSame('0.1.0', CapstanCommand::VERSION);
    }

    public function testTokensDeriveThemeName(): void
    {
        $this->assertSame('My Theme', Tokens::deriveThemeNameFromSlug('my-theme'));
        $this->assertSame('Starter', Tokens::deriveThemeNameFromSlug('starter'));
    }

    public function testTokensDeriveNamespace(): void
    {
        $this->assertSame('MyTheme', Tokens::deriveNamespaceFromSlug('my-theme'));
        $this->assertSame('Starter', Tokens::deriveNamespaceFromSlug('starter'));
    }

    public function testContextResolverReturnsNullForNonWpDir(): void
    {
        $context = ContextResolver::resolve('/tmp');

        $this->assertNull($context->wpRoot);
        $this->assertNull($context->themesDir);
        $this->assertSame('/tmp', $context->currentDir);
    }

    public function testBootstrapFileGuardsAgainstNonWpCli(): void
    {
        // capstan.php should return early when WP_CLI is not defined.
        // We can verify it is syntactically valid without side effects.
        $code = file_get_contents(__DIR__ . '/../capstan.php');

        $this->assertStringContainsString("defined('WP_CLI')", $code);
        $this->assertStringContainsString('return;', $code);
    }
}
