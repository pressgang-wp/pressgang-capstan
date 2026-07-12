<?php

namespace PressGang\Capstan\Tests;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Support\SiteMusterTemplate;

class SiteMusterTemplateTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function blueprint(): array
    {
        return [
            'namespace' => 'BristolBrc',
            'theme' => 'bristol-brc',
            'postTypes' => [
                ['name' => 'post', 'label' => 'Post', 'taxonomy' => null],
                ['name' => 'event', 'label' => 'Event', 'taxonomy' => 'event_type'],
            ],
            'taxonomies' => [
                ['name' => 'event_type', 'label' => 'Event Type'],
            ],
            'pageTemplates' => ['page-templates/contact.php'],
            'menus' => ['main-menu' => 'Main Menu'],
        ];
    }

    public function testRendersAllSectionsWiredIntoRun(): void
    {
        $source = SiteMusterTemplate::render($this->blueprint());

        $this->assertStringContainsString('namespace BristolBrc\Muster;', $source);
        $this->assertStringContainsString('final class SiteMuster extends Muster', $source);

        foreach (['terms', 'content', 'pages', 'menus'] as $method) {
            $this->assertStringContainsString("\$this->{$method}();", $source);
            $this->assertStringContainsString("private function {$method}(): void", $source);
        }

        $this->assertStringContainsString("->terms('event_type', ['event_type-' . (1 + \$i % 3)])", $source);
        $this->assertStringContainsString("->acf(\$this->acfFor('event'))", $source);
        $this->assertStringContainsString("->template('page-templates/contact.php')", $source);
        $this->assertStringContainsString("->location('main-menu')", $source);
    }

    public function testFreshTruncatesExactlyWhatIsSeeded(): void
    {
        $source = SiteMusterTemplate::render($this->blueprint());

        $this->assertStringContainsString("->posts('post')", $source);
        $this->assertStringContainsString("->posts('event')", $source);
        $this->assertStringContainsString("->terms('event_type')", $source);
        $this->assertStringContainsString('WARNING: truncation removes ALL content', $source);
    }

    public function testEmptySectionsAreOmittedEntirely(): void
    {
        $source = SiteMusterTemplate::render([
            'namespace' => 'Bare',
            'theme' => 'bare',
            'postTypes' => [],
            'taxonomies' => [],
            'pageTemplates' => [],
            'menus' => [],
        ]);

        foreach (['terms', 'content', 'pages', 'menus'] as $method) {
            $this->assertStringNotContainsString("private function {$method}(", $source);
        }

        $this->assertStringContainsString('Nothing to truncate yet', $source);
    }

    public function testGeneratedSourceIsValidPhp(): void
    {
        $source = SiteMusterTemplate::render($this->blueprint());

        $check = shell_exec('echo ' . escapeshellarg($source) . ' | php -l 2>&1');

        $this->assertStringContainsString('No syntax errors', (string) $check);
    }
}
