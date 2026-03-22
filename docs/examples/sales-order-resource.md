# Example: Sales Order Resource (HeaderLines + Lookups + Actions)

A document-style screen with header fields, editable line items, inline lookups, quick entry, formula columns, and screen actions.

```php
<?php

namespace App\TwoWee\Resources;

use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Model;
use TwoWee\Laravel\Actions\Action;
use TwoWee\Laravel\Actions\ActionField;
use TwoWee\Laravel\Actions\ActionResult;
use TwoWee\Laravel\Columns\DecimalColumn;
use TwoWee\Laravel\Enums\Color;
use TwoWee\Laravel\Columns\OptionColumn;
use TwoWee\Laravel\Columns\TextColumn;
use TwoWee\Laravel\Fields\Date;
use TwoWee\Laravel\Fields\Decimal;
use TwoWee\Laravel\Fields\Separator;
use TwoWee\Laravel\Fields\Text;
use TwoWee\Laravel\Resource;
use TwoWee\Laravel\Section;

class SalesOrderResource extends Resource
{
    protected static string $model = SalesOrder::class;
    protected static string $label = 'Sales Order';
    protected static ?string $recordKey = 'no';
    protected static ?string $navigationGroup = 'Sales';
    protected static int $navigationSort = 2;

    public static function title(Model $model): string
    {
        return 'Sales Order - ' . $model->no;
    }

    public static function layout(): string
    {
        return 'HeaderLines';
    }

    public static function linesRelation(): ?string
    {
        return 'lines';
    }

    public static function linesOverlayPct(): int
    {
        return 65;
    }

    // --- Save hooks ---

    public static function afterSave(Model $model): void
    {
        // Recalculate line amounts after save
        foreach ($model->lines as $line) {
            $line->update([
                'line_amount' => round(
                    (float) $line->quantity * (float) $line->unit_price
                    * (1 - (float) $line->discount_pct / 100), 2
                ),
            ]);
        }

        // Update header total
        $model->update([
            'total_amount' => $model->lines()->sum('line_amount'),
        ]);
    }

    // --- Form (header fields) ---

    public static function form(): array
    {
        return [
            Section::make('General')
                ->column(0)->rowGroup(0)
                ->fields([
                    Text::make('no')->label('No.')->width(20)
                        ->disabled()->default('Auto'),
                    Separator::make(),
                    Date::make('order_date')->label('Order Date')->width(12)
                        ->default(now()->format('d-m-Y'))->required(),
                    Date::make('posting_date')->label('Posting Date')->width(12),
                    Date::make('due_date')->label('Due Date')->width(12),
                    Separator::make(),
                    Text::make('your_reference')->label('Your Reference')
                        ->width(20)->nullable()->maxLength(50),
                    Text::make('payment_terms')->label('Payment Terms')
                        ->width(15)->uppercase()->nullable(),
                ]),

            Section::make('Customer')
                ->column(1)->rowGroup(0)
                ->fields([
                    Text::make('customer_no')->label('Customer No.')
                        ->width(15)->required()
                        ->focus()                                     // ← initial cursor
                        ->lookup(Customer::class, valueColumn: 'no')
                        ->autofill(['address', 'city', 'country_code', 'name' => 'customer_name']),
                    Separator::make(),
                    Text::make('customer_name')->label('Name')
                        ->width(30)->quickEntry(false),               // ← Enter skips
                    Text::make('address')->label('Address')
                        ->width(30)->quickEntry(false),
                    Text::make('city')->label('City')
                        ->width(20)->quickEntry(false),
                    Text::make('country_code')->label('Country')
                        ->width(10)->uppercase()->quickEntry(false),
                ]),

            Section::make('Totals')
                ->column(1)->rowGroup(1)
                ->fields([
                    Decimal::make('total_amount')->label('Total')
                        ->width(15)->disabled()
                        ->color(Color::Yellow)->bold(),
                ]),
        ];
    }

    // --- Line columns (editable grid) ---

    public static function lineColumns(): array
    {
        return [
            OptionColumn::make('type')->label('Type')
                ->width(9)->editable()
                ->quickEntry(false)                                   // ← Enter skips
                ->options(['', 'Item', 'Resource', 'Text']),

            TextColumn::make('no')->label('No.')
                ->width(12)->editable()
                ->lookup(Item::class, valueColumn: 'no')              // ← inline lookup
                ->autofill(['description', 'unit_of_measure', 'unit_price'])
                ->filterFrom('type'),                                 // ← polymorphic context

            TextColumn::make('description')->label('Description')
                ->width('fill')->editable()
                ->quickEntry(false),                                  // ← autofilled

            TextColumn::make('unit_of_measure')->label('UoM')
                ->width(6)->editable()
                ->quickEntry(false)                                   // ← autofilled
                ->lookup(UnitOfMeasure::class, 'code')
                ->modal(),                                            // ← small dataset

            DecimalColumn::make('quantity')->label('Qty')
                ->width(10)->align('right')->editable(),              // ← Enter stops here

            DecimalColumn::make('unit_price')->label('Unit Price')
                ->width(12)->align('right')->editable()
                ->quickEntry(false),                                  // ← autofilled

            DecimalColumn::make('discount_pct')->label('Disc. %')
                ->width(8)->align('right')->editable()
                ->quickEntry(false),                                  // ← rarely edited

            DecimalColumn::make('line_amount')->label('Amount')
                ->width(14)->align('right')
                ->formula('quantity * unit_price * (1 - discount_pct / 100)'),  // ← live calc
        ];
    }

    // --- List columns ---

    public static function table(): array
    {
        return [
            TextColumn::make('no')->label('No.')->width(10),
            TextColumn::make('customer_name')->label('Customer')->width('fill'),
            DecimalColumn::make('total_amount')->label('Total')
                ->decimals(2)->width(15)->align('right'),
        ];
    }

    // --- Screen actions ---

    public static function screenActions(?Model $model = null): array
    {
        return [
            Action::make('send_email')
                ->label('Send as Email')
                ->visible(fn ($record) => $record?->status !== 'draft')
                ->form(fn ($record) => [
                    ActionField::make('to')->label('To')->email()
                        ->value($record?->customer_email ?? '')->required(),
                    ActionField::make('subject')->label('Subject')
                        ->value('Sales Order ' . ($record?->no ?? '')),
                    ActionField::make('message')->label('Message'),
                ])
                ->action(function ($record, $data) {
                    // Mail::to($data['to'])->send(new SalesOrderMail($record, $data));
                    return ActionResult::success('Email sent to ' . $data['to']);
                }),

            Action::make('release')
                ->label('Release')
                ->visible(fn ($record) => $record?->status === 'open')
                ->requiresConfirmation('Release this order for processing?')
                ->action(function ($record) {
                    $record->update(['status' => 'released']);
                    return ActionResult::success('Order released.');
                }),
        ];
    }
}
```

## Key Features Demonstrated

- **`$recordKey = 'no'`** — URLs use the order number, not the database ID
- **`title()`** — custom title bar: "Sales Order - SO-1001"
- **`->focus()`** on Customer No. — cursor starts here, skipping the auto-generated No.
- **`->quickEntry(false)`** on autofilled fields — Enter jumps: Customer No. → Order Date → Posting Date → Due Date → Your Reference → Payment Terms
- **`Separator::make()`** — visual dividers between field groups
- **`->lookup(Customer::class, valueColumn: 'no')`** — inline lookup with autofill, no separate `lookups()` method
- **`->filterFrom('type')`** — polymorphic grid lookup: `?type=Item` vs `?type=Resource`
- **`->modal()`** — UoM lookup as overlay (small dataset)
- **`->formula(...)`** — client-side live calculation of line amount
- **`afterSave()`** — server recalculates line amounts and total (source of truth)
- **`Action::make()`** — self-contained actions with visibility, forms, and handlers

## Enter Path (Quick Entry)

**Header:** Customer No. → Order Date → Posting Date → Due Date → Your Reference → Payment Terms

**Lines:** No. → Qty → *(next row)* → No. → Qty → *(next row)*...

Tab and arrow keys reach all fields/columns when needed.
