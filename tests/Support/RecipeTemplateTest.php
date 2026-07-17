<?php

namespace PressGang\Capstan\Tests\Support;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Support\RecipeTemplate;

class RecipeTemplateTest extends TestCase
{
    private function render(array $overrides = []): string
    {
        return RecipeTemplate::render($overrides + [
            'namespace' => 'App\\Muster\\Recipes',
            'class' => 'ThingRecipe',
            'postType' => 'thing',
            'source' => 'post type thing',
            'core' => ['->slug($this->slugFor($iteration))'],
            'acf' => [
                ['name' => 'headline', 'type' => 'text', 'value' => '$this->victuals()->sentence()'],
                ['name' => 'gallery', 'type' => 'gallery', 'value' => null],
            ],
        ]);
    }

    public function testGeneratesParseablePhp(): void
    {
        $file = sys_get_temp_dir() . '/RecipeTemplateTest-' . uniqid() . '.php';
        file_put_contents($file, $this->render());

        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $exit);
        unlink($file);

        $this->assertSame(0, $exit, implode("\n", $output));
    }

    public function testEmitsExpressionsAndTodoStubs(): void
    {
        $out = $this->render();

        $this->assertStringContainsString("'headline' => \$this->victuals()->sentence(),", $out);
        $this->assertStringContainsString("// TODO (gallery) 'gallery'", $out);
        $this->assertStringContainsString('final class ThingRecipe extends Recipe', $out);
        $this->assertStringContainsString('public function define(int $iteration): PostBuilder', $out);
        $this->assertStringContainsString("\$this->content('thing')", $out);
    }

    public function testWithoutAcfEndsTheChainCleanly(): void
    {
        $out = $this->render(['acf' => []]);

        $this->assertStringNotContainsString('->acf(', $out);
        $this->assertStringContainsString('->slug($this->slugFor($iteration));', $out);
    }
}
