<?php

namespace PressGang\Capstan\Tests\Support;

use PHPUnit\Framework\TestCase;
use PressGang\Capstan\Support\AcfFieldMapper;

class AcfFieldMapperTest extends TestCase
{
    private AcfFieldMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AcfFieldMapper();
    }

    public function testScalarSchemaExpressions(): void
    {
        $this->assertSame('$this->victuals()->sentence()', $this->mapper->schemaExpr(['type' => 'text']));
        $this->assertSame('$this->victuals()->paragraphs()', $this->mapper->schemaExpr(['type' => 'textarea']));
        $this->assertSame('$this->victuals()->email()', $this->mapper->schemaExpr(['type' => 'email']));
        $this->assertSame('$this->victuals()->url()', $this->mapper->schemaExpr(['type' => 'url']));
        $this->assertSame('true', $this->mapper->schemaExpr(['type' => 'true_false']));
    }

    public function testNumberHonoursFieldMinMax(): void
    {
        $this->assertSame('$this->victuals()->raw()->numberBetween(5, 10)', $this->mapper->schemaExpr(['type' => 'number', 'min' => 5, 'max' => 10]));
        $this->assertSame('$this->victuals()->raw()->numberBetween(1, 100)', $this->mapper->schemaExpr(['type' => 'number']));
    }

    public function testChoiceFieldsUseTheFirstChoice(): void
    {
        $field = ['type' => 'select', 'choices' => ['draft' => 'Draft', 'live' => 'Live']];

        $this->assertSame("'draft'", $this->mapper->schemaExpr($field));
        $this->assertSame("['draft']", $this->mapper->schemaExpr($field + ['multiple' => 1]));
    }

    public function testUnsupportedSchemaTypesAreNull(): void
    {
        $this->assertNull($this->mapper->schemaExpr(['type' => 'image']));
        $this->assertNull($this->mapper->schemaExpr(['type' => 'relationship']));
        $this->assertNull($this->mapper->schemaExpr(['type' => 'repeater']));
    }

    public function testCaptureLiteralsAndBooleans(): void
    {
        $this->assertSame("'Hello'", $this->mapper->captureExpr(['type' => 'text'], 'Hello'));
        $this->assertSame('true', $this->mapper->captureExpr(['type' => 'true_false'], 1));
        $this->assertSame('false', $this->mapper->captureExpr(['type' => 'true_false'], 0));
        $this->assertSame('42', $this->mapper->captureExpr(['type' => 'number'], '42'));
        $this->assertNull($this->mapper->captureExpr(['type' => 'gallery'], [1, 2]));
    }

    public function testLayoutFieldsCarryNoValue(): void
    {
        $this->assertFalse($this->mapper->isValueField('tab'));
        $this->assertFalse($this->mapper->isValueField('message'));
        $this->assertTrue($this->mapper->isValueField('text'));
    }
}
