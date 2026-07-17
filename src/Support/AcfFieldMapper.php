<?php

namespace PressGang\Capstan\Support;

/**
 * Maps an ACF field definition to the PHP a scaffolded Muster Recipe should
 * emit for it — either a Victuals expression (schema mode: generate fresh
 * fakes) or a literal reproducing a captured value (capture mode).
 *
 * This first pass handles the scalar field types only. Anything else returns
 * null, and the command emits a `TODO` stub — the scaffold does the tedious
 * part without guessing at media, relations, or nested structures. See
 * pressgang-muster ADR 0009.
 */
final class AcfFieldMapper
{
    /** Field types this version scaffolds; everything else becomes a TODO. */
    public const SUPPORTED = ['text', 'textarea', 'number', 'email', 'url', 'select', 'radio', 'true_false'];

    /** Layout-only field types that carry no value and are skipped entirely. */
    public const NON_VALUE = ['tab', 'message', 'accordion'];

    public function supports(string $type): bool
    {
        return in_array($type, self::SUPPORTED, true);
    }

    public function isValueField(string $type): bool
    {
        return ! in_array($type, self::NON_VALUE, true);
    }

    /**
     * A Victuals expression for schema mode, or null when the type is not yet
     * scaffolded.
     *
     * @param array $field An ACF field definition (`type`, and per-type config).
     */
    public function schemaExpr(array $field): ?string
    {
        return match ((string) ($field['type'] ?? '')) {
            'text' => '$this->victuals()->sentence()',
            'textarea' => '$this->victuals()->paragraphs()',
            'email' => '$this->victuals()->email()',
            'url' => '$this->victuals()->url()',
            'number' => $this->numberExpr($field),
            'select', 'radio' => $this->choiceExpr($field),
            'true_false' => 'true',
            default => null,
        };
    }

    /**
     * A literal expression reproducing a captured value, or null when the
     * field type is not yet scaffolded.
     */
    public function captureExpr(array $field, mixed $value): ?string
    {
        $type = (string) ($field['type'] ?? '');

        if (! $this->supports($type)) {
            return null;
        }

        return match ($type) {
            'true_false' => $value ? 'true' : 'false',
            'number' => is_numeric($value) ? (string) (0 + $value) : $this->literal($value),
            default => $this->literal($value),
        };
    }

    private function numberExpr(array $field): string
    {
        $min = isset($field['min']) && is_numeric($field['min']) ? (int) $field['min'] : 1;
        $max = isset($field['max']) && is_numeric($field['max']) ? (int) $field['max'] : 100;

        if ($max < $min) {
            $max = $min;
        }

        return "\$this->victuals()->raw()->numberBetween({$min}, {$max})";
    }

    private function choiceExpr(array $field): string
    {
        $choices = is_array($field['choices'] ?? null) ? array_keys($field['choices']) : [];
        $expr = $this->literal($choices[0] ?? '');

        return ! empty($field['multiple']) ? "[{$expr}]" : $expr;
    }

    /** A safe PHP literal for a captured scalar or array. */
    private function literal(mixed $value): string
    {
        return var_export($value, true);
    }
}
