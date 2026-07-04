<?php

namespace PressGang\Capstan\Tests;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Support\ManifestWriter;

class ManifestWriterTest extends TestCase
{
    private function controller(string $body): string
    {
        return "<?php\n\nnamespace App\\Controllers;\n\nuse PressGang\\Controllers\\PageController;\n\nclass FrontPageController extends PageController {\n{$body}}\n";
    }

    public function testReadsPlainAndMappedEntries(): void
    {
        $source = $this->controller("\tprotected array \$context_getters = [ 'news', 'hero' => 'get_hero_banner' ];\n");

        $this->assertSame(['news', 'hero' => 'get_hero_banner'], ManifestWriter::keys($source));
    }

    public function testAddAppendsWithoutDuplicating(): void
    {
        $source = $this->controller("\tprotected array \$context_getters = [ 'news' ];\n");

        $updated = ManifestWriter::add($source, ['news', 'events']);

        $this->assertNotNull($updated);
        $this->assertStringContainsString("[ 'news', 'events' ]", $updated);
        $this->assertSame(1, substr_count($updated, "'news'"));
    }

    public function testAddPreservesMappedOverrides(): void
    {
        $source = $this->controller("\tprotected array \$context_getters = [ 'hero' => 'get_hero_banner' ];\n");

        $updated = ManifestWriter::add($source, ['news']);

        $this->assertStringContainsString("'hero' => 'get_hero_banner'", (string) $updated);
        $this->assertStringContainsString("'news'", (string) $updated);
    }

    public function testAddOfPresentKeysLeavesSourceUntouched(): void
    {
        // Multi-line author formatting must survive a no-op add.
        $source = $this->controller("\tprotected array \$context_getters = [\n\t\t'news',\n\t\t'events',\n\t];\n");

        $this->assertSame($source, ManifestWriter::add($source, ['news']));
    }

    public function testAddRefusesLiteralsItCannotParse(): void
    {
        $source = $this->controller("\tprotected array \$context_getters = [ self::KEY, 'news' ];\n");

        $this->assertNull(ManifestWriter::add($source, ['events']));
    }

    public function testAddRefusesCommentsInsideTheLiteral(): void
    {
        $source = $this->controller("\tprotected array \$context_getters = [\n\t\t'news', // headline feed\n\t];\n");

        $this->assertNull(ManifestWriter::add($source, ['events']));
    }

    public function testLongManifestsRenderMultiline(): void
    {
        $source = $this->controller("\tprotected array \$context_getters = [ 'news' ];\n");

        $updated = (string) ManifestWriter::add($source, ['events', 'hero_featured', 'video_source', 'related_articles']);

        $this->assertStringContainsString("[\n\t\t'news',\n\t\t'events',", $updated);
        $this->assertStringContainsString("'related_articles',\n\t]", $updated);
    }

    public function testInsertGoesAfterTheTraitUseBlock(): void
    {
        $source = $this->controller("\n\tuse HasNews;\n\tuse HasEvents;\n\n\tpublic function get_news(): array {\n\t\treturn [];\n\t}\n");

        $updated = (string) ManifestWriter::add($source, ['news']);

        $this->assertMatchesRegularExpression(
            '/use HasEvents;\n\n\t\/\*\*.*?\$context_getters = \[ \'news\' \];/s',
            $updated
        );
        $this->assertStringContainsString('Context keys published to the template', $updated);
    }

    public function testInsertFallsBackToTheClassBrace(): void
    {
        $source = $this->controller("\n\tpublic function get_news(): array {\n\t\treturn [];\n\t}\n");

        $updated = (string) ManifestWriter::add($source, ['news']);

        $this->assertMatchesRegularExpression(
            '/class FrontPageController extends PageController \{\n\n\t\/\*\*/',
            $updated
        );
    }

    public function testInsertIgnoresFileLevelImports(): void
    {
        // The only `use` lines are unindented imports — insertion must not
        // land in the import block.
        $source = "<?php\n\nnamespace App\\Controllers;\n\nuse PressGang\\Controllers\\PageController;\nuse Timber\\Post;\n\nclass X extends PageController {\n}\n";

        $updated = (string) ManifestWriter::add($source, ['news']);

        $this->assertMatchesRegularExpression('/class X extends PageController \{\n\n\t\/\*\*/', $updated);
        $this->assertStringNotContainsString("use Timber\\Post;\n\n\t/**", $updated);
    }

    public function testUpdatedSourceStaysValidPhp(): void
    {
        $source = $this->controller("\n\tuse HasNews;\n\n\tprotected array \$context_getters = [ 'news' ];\n");

        $updated = (string) ManifestWriter::add($source, ['events', 'hero']);

        $file = sys_get_temp_dir() . '/capstan-manifest-' . uniqid() . '.php';
        file_put_contents($file, $updated);
        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $exit);
        unlink($file);

        $this->assertSame(0, $exit, implode("\n", $output));
    }
}
