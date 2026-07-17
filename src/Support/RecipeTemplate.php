<?php

namespace PressGang\Capstan\Support;

/**
 * Renders a Muster Recipe class from a scaffold spec — the same shape a
 * hand-written Recipe takes (extends Recipe, `define(): PostBuilder`, a
 * `$this->content(...)` chain). See pressgang-muster ADR 0009.
 */
final class RecipeTemplate
{
    /**
     * @param array $spec {
     *     namespace: string,
     *     class:     string,          // e.g. LandingPageRecipe
     *     postType:  string,          // content() argument
     *     source:    string,          // human description for the doc comment
     *     core:      list<string>,    // extra builder calls, e.g. "->slug($this->slugFor($iteration))"
     *     acf:       list<array{name: string, value: string|null, type: string}>
     *                                 // value === null → a TODO stub for that field
     * }
     */
    public static function render(array $spec): string
    {
        $chain = '$this->content(' . var_export($spec['postType'], true) . ')';

        foreach ($spec['core'] ?? [] as $call) {
            $chain .= "\n\t\t\t" . $call;
        }

        $acf = $spec['acf'] ?? [];

        if ($acf !== []) {
            $lines = [];

            foreach ($acf as $field) {
                $lines[] = $field['value'] !== null
                    ? "\t\t\t\t" . var_export($field['name'], true) . ' => ' . $field['value'] . ','
                    : "\t\t\t\t// TODO ({$field['type']}) " . var_export($field['name'], true) . ' — not yet scaffolded; fill in by hand';
            }

            $chain .= "\n\t\t\t->acf([\n" . implode("\n", $lines) . "\n\t\t\t])";
        }

        $chain .= ';';

        $ns = $spec['namespace'];
        $class = $spec['class'];
        $source = $spec['source'];

        return <<<PHP
        <?php

        namespace {$ns};

        use PressGang\\Muster\\Builders\\PostBuilder;
        use PressGang\\Muster\\Patterns\\Recipe;

        /**
         * Scaffolded by `wp capstan make recipe` from {$source}.
         *
         * Fields it couldn't map yet are marked TODO — fill them in by hand.
         * Edit freely: this file is yours now.
         */
        final class {$class} extends Recipe
        {
        \tpublic function define(int \$iteration): PostBuilder
        \t{
        \t\treturn {$chain}
        \t}
        }

        PHP;
    }
}
