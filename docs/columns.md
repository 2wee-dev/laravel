# Columns

Columns define the table view on List screens and the grid portion of HeaderLines/Grid screens.

## Column Types

| Type | Class | Default Align |
|------|-------|---------------|
| Text | `TextColumn` | left |
| Decimal | `DecimalColumn` | right |
| Integer | `IntegerColumn` | right |
| Boolean | `BooleanColumn` | left |
| Date | `DateColumn` | left |
| Option | `OptionColumn` | left |
| Time | `TimeColumn` | left |

## Common API

```php
TextColumn::make('name')        // Create column with model attribute name
    ->label('Name')             // Display label
    ->width(30)                 // Fixed character width
    ->width('fill')             // Fill remaining space (one per table)
    ->align('right')            // 'left', 'center', 'right'
    ->editable()                // Editable in grid layouts
    ->options([...])            // Options for editable Option columns
    ->lookup($endpoint, $valueColumn, $autofill)  // Lookup for editable columns
    ->validation(['required' => true])             // Validation for editable columns
    ->formatUsing(fn ($value) => ucfirst($value))  // Custom display formatting
    ->quickEntry(false)                            // Enter skips this column
    ->formula('quantity * unit_price')             // Live client-side calculation
    ->showZero()                                   // Show 0 instead of blank for zero numeric values
```

## Quick Entry on Grid Columns

Same as card fields — Enter skips columns with `quickEntry(false)`, Tab visits all columns:

```php
public static function lineColumns(): array
{
    return [
        OptionColumn::make('line_type')->label('Type')
            ->editable()->quickEntry(false),                    // Enter skips (set once)
        TextColumn::make('item_id')->label('No.')
            ->editable()->lookup(Item::class, valueColumn: 'no')
            ->autofill(['description', 'unit_of_measure', 'unit_price']),
        TextColumn::make('description')->label('Description')
            ->editable()->quickEntry(false),                    // Enter skips (autofilled)
        TextColumn::make('unit_of_measure')->label('UoM')
            ->editable()->quickEntry(false),                    // Enter skips (autofilled)
        DecimalColumn::make('quantity')->label('Qty')
            ->editable()->decimals(2)->align('right'),          // Enter stops here
        DecimalColumn::make('unit_price')->label('Unit Price')
            ->editable()->quickEntry(false)->decimals(2),       // Enter skips (autofilled)
        DecimalColumn::make('line_discount_pct')->label('Disc. %')
            ->editable()->quickEntry(false)->decimals(1),       // Enter skips (rarely edited)
        DecimalColumn::make('line_amount')->label('Amount')
            ->decimals(2)->align('right'),                      // not editable (calculated)
    ];
}
```

**Enter path:** No. → Qty → *(next row)* → No. → Qty → *(next row)*...

The user enters item numbers and quantities at speed. Type is set once per batch. Description, UoM, and Unit Price autofill from the item lookup. Discount is rarely changed. Amount is calculated on save.

## Formula (Calculated Columns)

Add a client-side formula for live calculation. The client evaluates it instantly after every cell edit — no server call needed.

```php
DecimalColumn::make('line_amount')
    ->label('Amount')
    ->decimals(2)
    ->align('right')
    ->formula('quantity * unit_price * (1 - line_discount_pct / 100)')
```

The formula references other column IDs by name. Supported operators: `+`, `-`, `*`, `/`, parentheses, and numbers.

Formula columns should typically be non-editable (omit `->editable()`) since their value is computed.

### Examples

```php
// Simple total
->formula('quantity * unit_price')

// With discount
->formula('quantity * unit_price * (1 - line_discount_pct / 100)')

// Tax calculation
->formula('line_amount * tax_rate / 100')

// Margin
->formula('unit_price - unit_cost')
```

### Important

- **The formula does NOT run on load.** When the screen opens, the client displays whatever value the server sent. The server must pre-calculate and send the correct value for formula columns.
- **The formula only runs during editing.** After the user edits a cell that the formula references, the client recalculates instantly.
- **The server is the source of truth.** Always calculate in `afterSave()`. The formula and `afterSave()` must use the same math — if they diverge, the user sees one number during editing and a different number after save.

Both are required:

```php
// In lineColumns() — client-side live preview during editing
DecimalColumn::make('line_amount')
    ->formula('quantity * unit_price * (1 - discount_pct / 100)')

// In afterSave() — server-side calculation, persisted to database
public static function afterSave(Model $model): void
{
    foreach ($model->lines as $line) {
        $line->update([
            'line_amount' => round(
                (float) $line->quantity * (float) $line->unit_price
                * (1 - (float) $line->discount_pct / 100), 2
            ),
        ]);
    }
}
```

## DecimalColumn

```php
DecimalColumn::make('balance')
    ->decimals(2)               // Decimal places (default: 2)
    ->align('right')
```

The server sends plain numbers (period decimal, no thousand separator). The client formats for display using the locale config.

## OptionColumn

For grid columns with fixed choices:

```php
OptionColumn::make('type')
    ->label('Type')
    ->options(['Item', 'G/L Account', 'Resource'])
    ->editable()
```

## Column Lookup

Editable grid columns can have lookups, just like card fields. Pass a model class for auto-wiring:

```php
// Auto-wired from model class
TextColumn::make('item_id')
    ->label('No.')
    ->editable()
    ->lookup(Item::class, valueColumn: 'no')
    ->autofill(['description', 'unit_of_measure', 'unit_price'])

// With modal display (small datasets)
TextColumn::make('unit_of_measure')
    ->editable()
    ->lookup(UnitOfMeasure::class, 'code')
    ->modal()

// Manual endpoint (string)
TextColumn::make('account_no')
    ->editable()
    ->lookup('accounts', 'no')
    ->autofill(['name' => 'description'])
```

The autofill map maps lookup column IDs to other column IDs in the same grid row.

### Shared autofill maps

When multiple resources use the same lookup with the same autofill (e.g. item lookup on sales quotes, orders, and invoices), define the map once as a constant:

```php
class SalesLine
{
    public const ITEM_AUTOFILL = ['description', 'unit_of_measure', 'unit_price', 'unit_cost'];
}
```

Then reference it in every resource:

```php
TextColumn::make('item_id')
    ->lookup(Item::class, valueColumn: 'no')
    ->autofill(SalesLine::ITEM_AUTOFILL)
```

Update one place, all resources pick it up. The client ignores autofill keys that don't match a column on the current screen — a superset is safe.

### Context (polymorphic lookups)

Filter the lookup by another column's value in the same row:

```php
TextColumn::make('item_id')
    ->label('No.')
    ->editable()
    ->lookup(Item::class, valueColumn: 'no')
    ->autofill(['description'])
    ->filterFrom('line_type')
```

When the user opens the lookup, the client reads `line_type` from the current row and sends:
```
GET /lookup/item_id?line_type=Item
GET /validate/item_id/I-00001?line_type=Item
```

For polymorphic lookups that switch models based on the type, use a custom query modifier in `lookups()`:

```php
public static function lookups(): array
{
    return [
        'item_id' => LookupDefinition::make(Item::class)
            ->columns([...])
            ->valueColumn('no')
            ->query(function ($query, array $context) {
                match ($context['line_type'] ?? '') {
                    'Resource' => $query->from('resources'),
                    'Text' => $query->from('standard_texts'),
                    default => null, // Items table (default)
                };
            }),
    ];
}
```

## Custom Formatting

Use `formatUsing()` to transform display values with a closure:

```php
TextColumn::make('status')
    ->formatUsing(fn ($value) => ucfirst($value ?? ''))

TextColumn::make('is_blocked')
    ->formatUsing(fn ($value) => $value === 'all' ? 'BLOCKED' : $value)

TextColumn::make('is_active')
    ->formatUsing(fn ($value) => $value ? 'Yes' : 'No')
```

The closure receives the raw model value and must return a string. It overrides the column type's default formatting.

## Column Validation

For editable grid columns:

```php
TextColumn::make('description')
    ->editable()
    ->validation([
        'required' => true,
        'max_length' => 100,
    ])
```
