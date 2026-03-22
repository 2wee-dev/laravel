# HeaderLines Layout

HeaderLines is for document-style screens with a header (sections with fields) and an editable grid of line items — like sales orders, purchase orders, or invoices.

## Setup

```php
class SalesOrderResource extends Resource
{
    protected static string $model = \App\Models\SalesOrder::class;
    protected static string $label = 'Sales Order';

    public static function layout(): string
    {
        return 'HeaderLines';
    }

    public static function linesRelation(): ?string
    {
        return 'lines';   // Eloquent relationship name
    }

    public static function linesOverlayPct(): int
    {
        return 65;   // Grid overlay size (0-100)
    }

    public static function form(): array
    {
        return [
            Section::make('General')
                ->column(0)->rowGroup(0)
                ->fields([
                    Text::make('no')->label('No.')->width(20)->uppercase()->disabled(),
                    Text::make('customer_no')->label('Customer No.')->width(20)
                        ->uppercase()
                        ->lookup('customer_no', 'customer_name', validate: true),
                    Text::make('customer_name')->label('Customer Name')
                        ->width(30)->disabled(),
                ]),
        ];
    }

    public static function table(): array
    {
        return [
            TextColumn::make('no')->label('No.')->width(10),
            TextColumn::make('customer_name')->label('Customer')->width('fill'),
            DecimalColumn::make('total')->label('Total')->decimals(2)->width(15)->align('right'),
        ];
    }

    public static function lineColumns(): array
    {
        return [
            OptionColumn::make('type')->label('Type')
                ->options(['Item', 'Resource', 'Text'])
                ->editable()->width(10),
            TextColumn::make('no')->label('No.')
                ->editable()->width(15)
                ->lookup('line_item', 'no')
                ->autofill(['description']),
            TextColumn::make('description')->label('Description')
                ->editable()->width('fill'),
            DecimalColumn::make('quantity')->label('Qty')
                ->editable()->decimals(2)->width(10)->align('right'),
            DecimalColumn::make('unit_price')->label('Unit Price')
                ->editable()->decimals(2)->width(12)->align('right'),
            DecimalColumn::make('discount_pct')->label('Disc. %')
                ->editable()->decimals(1)->width(8)->align('right'),
            DecimalColumn::make('amount')->label('Amount')
                ->decimals(2)->width(15)->align('right'),
        ];
    }

    public static function totals(?\Illuminate\Database\Eloquent\Model $model = null): array
    {
        $total = $model?->lines?->sum('amount') ?? 0;

        return [
            ['label' => 'Total', 'value' => number_format($total, 2, '.', ''), 'source_column' => 'amount'],
        ];
    }
}
```

## Overlay

The `linesOverlayPct()` method controls how much of the screen the grid takes. The default is `50`. Override it when you want a different split:

```php
public static function linesOverlayPct(): int
{
    return 65; // grid takes 65% of the screen
}
```

- `0` = grid hidden, full header view
- `50` = split view (default — omit the method to use this)
- `100` = grid fills entire screen

The user toggles the overlay with Ctrl+L.

By default the overlay starts closed. Override `linesOpen()` to open it automatically when the screen loads:

```php
public static function linesOpen(): bool
{
    return true;
}
```

## Empty Trailing Row

The server always includes at least one empty trailing row in the grid, allowing the user to add new line items.

## Saving

The client sends both `changes` (header fields) and `lines` (2D grid data) in the SaveChangeset. The server:

1. Updates the header model with `changes`
2. Deletes all existing line items
3. Creates new line items from `lines` (skipping empty rows)
4. Returns the refreshed ScreenContract
