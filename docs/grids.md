# Grid Layout

Grid is for full-screen editable tables with no header — journals, batch entry, time sheets.

## Setup

Extend `GridResource` for convenience:

```php
use TwoWee\Laravel\GridResource;

class JournalResource extends GridResource
{
    protected static string $model = \App\Models\JournalLine::class;
    protected static string $label = 'General Journal';

    public static function lineColumns(): array
    {
        return [
            DateColumn::make('posting_date')->label('Posting Date')
                ->editable()->width(12),
            OptionColumn::make('document_type')->label('Doc. Type')
                ->options(['Invoice', 'Credit Memo', 'Payment'])
                ->editable()->width(12),
            TextColumn::make('document_no')->label('Doc. No.')
                ->editable()->width(15),
            OptionColumn::make('account_type')->label('Acc. Type')
                ->options(['G/L Account', 'Customer', 'Vendor', 'Bank'])
                ->editable()->width(12),
            TextColumn::make('account_no')->label('Account No.')
                ->editable()->width(15)
                ->lookup('accounts', 'no')
                ->autofill(['name' => 'description']),   // names differ
            TextColumn::make('description')->label('Description')
                ->editable()->width('fill'),
            DecimalColumn::make('amount')->label('Amount')
                ->editable()->decimals(2)->width(15)->align('right'),
        ];
    }

    public static function totals(?\Illuminate\Database\Eloquent\Model $model = null): array
    {
        return [
            ['label' => 'Balance', 'value' => '0.00', 'source_column' => 'amount', 'aggregate' => 'sum'],
        ];
    }
}
```

## GridResource

`GridResource` is a convenience class that:
- Sets `layout()` to `'Grid'`
- Returns empty `form()` (no header)
- Maps `table()` to `lineColumns()` for list screen compatibility

## Totals

Footer totals with optional live client-side computation:

```php
public static function totals(?\Illuminate\Database\Eloquent\Model $model = null): array
{
    return [
        [
            'label' => 'Starting Balance',
            'value' => '10000.00',
            // No source_column = static value
        ],
        [
            'label' => 'Balance',
            'value' => '10000.00',
            'source_column' => 'amount',   // Live aggregation
            'aggregate' => 'sum',           // sum (default), count, avg, min, max
        ],
    ];
}
```

## Row Operations

The client handles:
- `Ctrl+N` / `F3` = Insert new row
- `Ctrl+D` / `F4` = Delete current row

## Saving

Grid saves send only `lines` (no `changes`). The `lines` array is 2D — column order matches `lineColumns()`. The server ignores empty trailing rows.
