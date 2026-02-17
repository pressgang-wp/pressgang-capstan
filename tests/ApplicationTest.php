<?php

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

    public function testTokensIsValidSlug(): void
    {
        $this->assertTrue(Tokens::isValidSlug('my-theme'));
        $this->assertTrue(Tokens::isValidSlug('starter'));
        $this->assertTrue(Tokens::isValidSlug('theme-2'));
        $this->assertFalse(Tokens::isValidSlug('My-Theme'));
        $this->assertFalse(Tokens::isValidSlug('-leading'));
        $this->assertFalse(Tokens::isValidSlug('trailing-'));
        $this->assertFalse(Tokens::isValidSlug('double--dash'));
        $this->assertFalse(Tokens::isValidSlug('has_underscore'));
        $this->assertFalse(Tokens::isValidSlug(''));
    }

    public function testTokensIsValidNamespace(): void
    {
        $this->assertTrue(Tokens::isValidNamespace('MyTheme'));
        $this->assertTrue(Tokens::isValidNamespace('Acme\\MyTheme'));
        $this->assertTrue(Tokens::isValidNamespace('A\\B\\C'));
        $this->assertFalse(Tokens::isValidNamespace('myTheme'));
        $this->assertFalse(Tokens::isValidNamespace('My Theme'));
        $this->assertFalse(Tokens::isValidNamespace('My;Theme'));
        $this->assertFalse(Tokens::isValidNamespace(''));
        $this->assertFalse(Tokens::isValidNamespace('\\Leading'));
        $this->assertFalse(Tokens::isValidNamespace('Trailing\\'));
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
