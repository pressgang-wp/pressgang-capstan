<?php

namespace PressGang\Capstan\Tests\Support;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Support\DocsIndex;

class DocsIndexTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/capstan-docs-' . uniqid();
        $this->writeIndex('pressgang-wp/quartermaster', [
            'package' => 'pressgang/quartermaster',
            'version' => '0.1.0',
            'methods' => [
                ['name' => 'whereMeta', 'signature' => 'whereMeta(string $key, $value): self', 'group' => 'Filter', 'sets_args' => ['meta_query'], 'wp_docs' => ['https://example/meta'], 'notes' => 'Adds a meta clause.'],
                ['name' => 'posts', 'signature' => 'posts($type): self', 'group' => 'Bootstrap', 'sets_args' => ['post_type'], 'wp_docs' => [], 'notes' => 'Entry point.'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $node) {
            $node->isDir() ? rmdir($node->getPathname()) : unlink($node->getPathname());
        }
        rmdir($this->root);
    }

    private function writeIndex(string $package, array $index): void
    {
        $dir = "{$this->root}/vendor/{$package}/docs";
        mkdir($dir, 0777, true);
        file_put_contents("{$dir}/api-index.json", json_encode($index));
    }

    private function index(): DocsIndex
    {
        return new DocsIndex([$this->root]);
    }

    public function testDiscoversInstalledIndexesFromVendor(): void
    {
        $this->assertArrayHasKey('pressgang/quartermaster', $this->index()->indexes());
    }

    public function testExactNameOutranksIncidentalMatches(): void
    {
        $results = $this->index()->search('whereMeta')['results'];

        $this->assertSame('whereMeta', $results[0]['name']);
        $this->assertSame('0.1.0', $results[0]['version']);
        $this->assertSame(['https://example/meta'], $results[0]['wp_docs']);
    }

    public function testMatchesArgsAndNotes(): void
    {
        $names = array_column($this->index()->search('post_type')['results'], 'name');

        $this->assertContains('posts', $names);
    }

    public function testPackageFilterNarrowsResults(): void
    {
        $this->assertNotEmpty($this->index()->search('posts', 'quartermaster')['results']);
        $this->assertEmpty($this->index()->search('posts', 'nonesuch')['results']);
    }

    public function testMalformedIndexIsSkipped(): void
    {
        $bad = "{$this->root}/vendor/acme/broken/docs";
        mkdir($bad, 0777, true);
        file_put_contents("{$bad}/api-index.json", '{ not json');

        // The good index still resolves; the broken one is silently ignored.
        $this->assertCount(1, $this->index()->indexes());
    }
}
