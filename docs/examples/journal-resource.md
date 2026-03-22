# Example: General Journal (Grid + Totals)

A full-screen editable grid with live totals and screen actions.

```php
<?php

namespace App\TwoWee\Resources;

use TwoWee\Laravel\Actions\Action;
use TwoWee\Laravel\Actions\ActionResult;
use TwoWee\Laravel\Columns\DateColumn;
use TwoWee\Laravel\Columns\DecimalColumn;
use TwoWee\Laravel\Columns\OptionColumn;
use TwoWee\Laravel\Columns\TextColumn;
use TwoWee\Laravel\GridResource;
use TwoWee\Laravel\Lookup\LookupDefinition;

class JournalResource extends GridResource
{
    protected static string $model = \App\Models\JournalLine::class;
    protected static string $label = 'General Journal';

    public static function lineColumns(): array
    {
        return [
            DateColumn::make('posting_date')->label('Posting Date')
                ->editable()->width(12),
            OptionColumn::make('document_type')->label('Document Type')
                ->options(['Invoice', 'Credit Memo', 'Payment'])
                ->editable()->width(14),
            TextColumn::make('document_no')->label('Document No.')
                ->editable()->width(15),
            OptionColumn::make('account_type')->label('Account Type')
                ->options(['G/L Account', 'Customer', 'Vendor', 'Bank Account'])
                ->editable()->width(14),
            TextColumn::make('account_no')->label('Account No.')
                ->editable()->width(15)
                ->lookup('gl_accounts', 'no')
                ->autofill(['name' => 'description']),
            TextColumn::make('description')->label('Description')
                ->editable()->width('fill'),
            DecimalColumn::make('amount')->label('Amount')
                ->editable()->decimals(2)->width(15)->align('right'),
        ];
    }

    public static function totals(?\Illuminate\Database\Eloquent\Model $model = null): array
    {
        return [
            [
                'label' => 'Balance',
                'value' => '0.00',
                'source_column' => 'amount',
                'aggregate' => 'sum',
            ],
        ];
    }

    public static function lookups(): array
    {
        return [
            'gl_accounts' => LookupDefinition::make(\App\Models\GlAccount::class)
                ->columns([
                    TextColumn::make('no')->label('No.')->width(10),
                    TextColumn::make('name')->label('Name')->width('fill'),
                ])
                ->valueColumn('no')
                ->autofill(['name' => 'description']),
        ];
    }

    public static function screenActions(?\Illuminate\Database\Eloquent\Model $model = null): array
    {
        return [
            Action::make('post')
                ->label('Post Journal')
                ->requiresConfirmation('Post all journal lines? This cannot be undone.')
                ->action(function ($record, $data) {
                    // JournalLine::query()->each(fn ($line) => $line->post());
                    // JournalLine::query()->delete();
                    return ActionResult::success(
                        'Journal posted successfully.',
                        static::toGridJson('Journal posted.')
                    );
                }),

            Action::make('export_csv')
                ->label('Export to CSV')
                ->action(fn () => ActionResult::success('CSV exported.')),
        ];
    }
}
```

## Key Points

- `GridResource` sets `layout()` to `'Grid'` and returns empty `form()`
- `totals()` with `source_column` enables live client-side aggregation
- The `post` action uses `requiresConfirmation()` — user must confirm before execution
- After posting, the handler returns a fresh empty grid via `toGridJson()`
- The `Balance` total updates in real-time as the user enters amounts
