<?php

namespace TwoWee\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TwoWee\Laravel\Columns\DecimalColumn;
use TwoWee\Laravel\Columns\IntegerColumn;
use TwoWee\Laravel\Columns\OptionColumn;
use TwoWee\Laravel\Columns\TextColumn;
use TwoWee\Laravel\Columns\TimeColumn;

class ColumnTest extends TestCase
{
    public function test_text_column_to_array(): void
    {
        $result = TextColumn::make('name')
            ->label('Name')
            ->width('fill')
            ->toArray();

        $this->assertSame('name', $result['id']);
        $this->assertSame('Name', $result['label']);
        $this->assertSame('Text', $result['type']);
        $this->assertSame('fill', $result['width']);
        $this->assertSame('left', $result['align']);
    }

    public function test_text_column_with_fixed_width(): void
    {
        $result = TextColumn::make('no')
            ->label('No.')
            ->width(10)
            ->toArray();

        $this->assertSame('Text', $result['type']);
        $this->assertSame(10, $result['width']);
    }

    public function test_decimal_column_formats_value(): void
    {
        $column = DecimalColumn::make('balance')
            ->label('Balance')
            ->decimals(2)
            ->align('right');

        // Default locale: , decimal, . thousands
        $this->assertSame('45000.00', $column->formatValue(45000));
        $this->assertSame('', $column->formatValue(null));
    }

    public function test_integer_column_formats_value(): void
    {
        $column = IntegerColumn::make('qty');

        $this->assertSame('42', $column->formatValue(42));
        $this->assertSame('', $column->formatValue(null));
    }

    public function test_editable_column(): void
    {
        $result = TextColumn::make('name')
            ->editable()
            ->toArray();

        $this->assertTrue($result['editable']);
    }

    public function test_option_column(): void
    {
        $result = OptionColumn::make('type')
            ->label('Type')
            ->options(['Item', 'G/L Account', 'Resource'])
            ->editable()
            ->toArray();

        $this->assertSame('Option', $result['type']);
        $this->assertTrue($result['editable']);
        $this->assertSame([
            ['value' => 'Item', 'label' => 'Item'],
            ['value' => 'G/L Account', 'label' => 'G/L Account'],
            ['value' => 'Resource', 'label' => 'Resource'],
        ], $result['options']);
    }

    public function test_time_column(): void
    {
        $result = TimeColumn::make('time')
            ->label('Time')
            ->width(10)
            ->toArray();

        $this->assertSame('Time', $result['type']);
    }

    public function test_column_with_lookup(): void
    {
        $result = TextColumn::make('account_no')
            ->label('Account No.')
            ->editable()
            ->lookup('accounts', 'no', ['name' => 'description'])
            ->toArray();

        $this->assertStringContainsString('/lookup/accounts', $result['lookup']['endpoint']);
        $this->assertStringContainsString('/validate/accounts', $result['lookup']['validate']);
    }

    public function test_column_with_validation(): void
    {
        $result = TextColumn::make('name')
            ->editable()
            ->validation(['required' => true, 'max_length' => 50])
            ->toArray();

        $this->assertSame(['required' => true, 'max_length' => 50], $result['validation']);
    }

    public function test_format_value_with_backed_enum(): void
    {
        $column = TextColumn::make('status');

        $this->assertSame('active', $column->formatValue(TestColumnEnum::Active));
        $this->assertSame('', $column->formatValue(null));
    }

    public function test_format_using_closure(): void
    {
        $column = TextColumn::make('status')
            ->formatUsing(fn ($value) => strtoupper($value ?? ''));

        $this->assertSame('ACTIVE', $column->formatValue('active'));
        $this->assertSame('', $column->formatValue(null));
    }

    public function test_format_using_overrides_default(): void
    {
        $column = TextColumn::make('name')
            ->formatUsing(fn ($value) => $value === null ? 'N/A' : "[$value]");

        $this->assertSame('[John]', $column->formatValue('John'));
        $this->assertSame('N/A', $column->formatValue(null));
    }

    public function test_column_lookup_with_modal(): void
    {
        $result = TextColumn::make('currency_code')
            ->editable()
            ->lookup('currencies', 'code')
            ->modal()
            ->toArray();

        $this->assertSame('modal', $result['lookup']['display']);
    }

    public function test_column_lookup_with_context(): void
    {
        $result = TextColumn::make('item_id')
            ->editable()
            ->lookup('items', 'no')
            ->filterFrom('line_type')
            ->toArray();

        $this->assertSame([['field' => 'line_type']], $result['lookup']['context']);
    }

    public function test_column_no_lookup_by_default(): void
    {
        $result = TextColumn::make('name')->toArray();

        $this->assertArrayNotHasKey('lookup', $result);
    }

    public function test_column_quick_entry_default_true(): void
    {
        $result = TextColumn::make('name')->editable()->toArray();

        $this->assertArrayNotHasKey('quick_entry', $result);
    }

    public function test_column_quick_entry_false(): void
    {
        $result = TextColumn::make('description')
            ->editable()
            ->quickEntry(false)
            ->toArray();

        $this->assertFalse($result['quick_entry']);
    }

    public function test_column_formula(): void
    {
        $result = DecimalColumn::make('line_amount')
            ->label('Amount')
            ->decimals(2)
            ->formula('quantity * unit_price * (1 - line_discount_pct / 100)')
            ->toArray();

        $this->assertSame('quantity * unit_price * (1 - line_discount_pct / 100)', $result['formula']);
    }

    public function test_column_no_formula_by_default(): void
    {
        $result = DecimalColumn::make('amount')->toArray();

        $this->assertArrayNotHasKey('formula', $result);
    }
}

enum TestColumnEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
