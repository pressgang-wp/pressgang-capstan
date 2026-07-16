<?php

namespace PressGang\Capstan\Tests\Support;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Support\ApiIndexGenerator;

/** A fixture "API" the generator reflects. */
class SampleApi
{
    /**
     * Constrain by a meta key.
     *
     * Sets: meta_query
     * See: https://developer.wordpress.org/reference/classes/wp_query/#custom-field-parameters
     */
    public function whereMeta(string $key, mixed $value, string $compare = '='): self
    {
        return $this;
    }

    public function reset(): void
    {
    }

    /** Return everything. */
    public function all(): self
    {
        return $this;
    }

    protected function hidden(): void
    {
    }
}

class ApiIndexGeneratorTest extends TestCase
{
    private function manifest(): array
    {
        return [
            'package' => 'pressgang/sample',
            'version' => '1.0.0',
            'entrypoint' => SampleApi::class,
            'principles' => ['Explicit over magic'],
            'reads_globals' => ['reset' => true],
            'groups' => [
                'Filter' => [SampleApi::class, ['whereMeta', 'hidden', 'missingMethod']],
                'Lifecycle' => [SampleApi::class, ['reset', 'all']],
            ],
        ];
    }

    private function payload(): array
    {
        return (new ApiIndexGenerator())->generate($this->manifest(), '2026-01-01T00:00:00Z');
    }

    public function testEmitsStandardTopLevelSchema(): void
    {
        $p = $this->payload();

        $this->assertSame('pressgang/sample', $p['package']);
        $this->assertSame('1.0.0', $p['version']);
        $this->assertSame('2026-01-01T00:00:00Z', $p['generated_at']);
        $this->assertSame('PressGang\\Capstan\\Tests\\Support\\SampleApi', $p['entrypoint']);
        $this->assertSame(['Explicit over magic'], $p['principles']);
    }

    public function testRendersSignatureWithDefaults(): void
    {
        $where = $this->method('whereMeta');

        $this->assertSame("whereMeta(string \$key, mixed \$value, string \$compare = '='): self", $where['signature']);
        $this->assertSame('Filter', $where['group']);
    }

    public function testParsesSetsArgsNotesAndDocLinks(): void
    {
        $where = $this->method('whereMeta');

        $this->assertSame(['meta_query'], $where['sets_args']);
        $this->assertSame('Constrain by a meta key.', $where['notes']);
        $this->assertStringContainsString('developer.wordpress.org', $where['wp_docs'][0]);
    }

    public function testAppliesReadsGlobalsAndSkipsMissingAndNonPublic(): void
    {
        $names = array_column($this->payload()['methods'], 'name');

        $this->assertContains('whereMeta', $names);
        $this->assertContains('reset', $names);
        $this->assertNotContains('hidden', $names);       // protected
        $this->assertNotContains('missingMethod', $names); // not defined
        $this->assertTrue($this->method('reset')['reads_globals']);
    }

    public function testNotesStayCleanWhenArgsNotAnnotated(): void
    {
        // Default manifest has no annotate_args — an unannotated method is clean.
        $this->assertSame('Return everything.', $this->method('all')['notes']);
    }

    public function testAnnotateArgsFlagsUnannotatedMethods(): void
    {
        $manifest = ['annotate_args' => true] + $this->manifest();
        $payload = (new ApiIndexGenerator())->generate($manifest, '2026-01-01T00:00:00Z');

        $all = array_values(array_filter($payload['methods'], static fn ($m) => $m['name'] === 'all'))[0];

        $this->assertSame('Return everything. (args mapping not annotated yet)', $all['notes']);
    }

    private function method(string $name): array
    {
        foreach ($this->payload()['methods'] as $m) {
            if ($m['name'] === $name) {
                return $m;
            }
        }

        $this->fail("method {$name} not in payload");
    }
}
