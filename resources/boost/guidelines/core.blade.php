## TwoWee Laravel

TwoWee is a Laravel plugin for building terminal UI screens served to a Rust TUI client. Define screens using a Filament-style PHP API — the server outputs JSON, the client renders it in the terminal.

### Core Concepts

- **Resource**: Maps an Eloquent model to terminal screens (card, list, grid). Lives in `app/TwoWee/Resources/`.
- **Field**: Input on a card screen (Text, Decimal, Integer, Date, Email, Option, Boolean, etc.)
- **Column**: Column in a list or editable grid (TextColumn, DecimalColumn, OptionColumn, etc.)
- **Section**: Groups fields on a card with 2D layout (column + row_group).

### Creating a Resource

@verbatim
<code-snippet name="Basic Resource" lang="php">
namespace App\TwoWee\Resources;

use TwoWee\Laravel\Columns\TextColumn;
use TwoWee\Laravel\Fields\Text;
use TwoWee\Laravel\Resource;
use TwoWee\Laravel\Section;

class CustomerResource extends Resource
{
    protected static string $model = \App\Models\Customer::class;
    protected static string $label = 'Customer';
    protected static ?string $recordKey = 'no';

    public static function form(): array
    {
        return [
            Section::make('General')
                ->column(0)->rowGroup(0)
                ->fields([
                    Text::make('no')->label('No.')->disabled(),
                    Text::make('name')->label('Name')->required(),
                ]),
        ];
    }

    public static function table(): array
    {
        return [
            TextColumn::make('no')->label('No.')->width(10),
            TextColumn::make('name')->label('Name')->width('fill'),
        ];
    }
}
</code-snippet>
@endverbatim

### Key Patterns

- **Lookups**: Use `->lookup(Model::class, valueColumn: 'code')` for inline auto-wired lookups. Avoid manual string endpoints.
- **Quick Entry**: Use `->quickEntry(false)` on autofilled fields so Enter skips them. Default is true.
- **Focus**: Use `->focus()` on the first field the user should type into.
- **Validation**: Put rules on fields directly: `->required()->email()->unique('table', 'col')`. No separate `rules()` method needed.
- **Uppercase**: Use `->uppercase()` for code fields. No `Code` class exists — use `Text`.
- **Record Key**: Set `$recordKey = 'no'` when the natural key differs from the primary key.
- **Wire Format**: Numbers are plain English format (period decimal, no thousand separator). The client handles locale display.

### ActionField Typed Methods

Use typed methods instead of `->type('...')` on `ActionField`:

```php
ActionField::make('to')->label('To')->email()        // not ->type('Email')
ActionField::make('qty')->label('Qty')->integer()     // not ->type('Integer')
ActionField::make('status')->label('Status')
    ->option(['Draft', 'Open', 'Released'])            // not ->type('Option')->options([...])
```

Available: `->text()`, `->email()`, `->phone()`, `->url()`, `->decimal()`, `->integer()`, `->date()`, `->time()`, `->boolean()`, `->password()`, `->textarea()`, `->option([...])`.

### Generators

```bash
php artisan 2wee:resource CustomerResource --model=Customer
php artisan 2wee:lookup CountryLookup --model=Country
php artisan 2wee:action PostSalesInvoice
```

### What NOT to Do

- Do not use `Code::make()` or `FlowField::make()` — they do not exist. Use `Text::make()->uppercase()` and `Decimal::make()->aggregate()->drillDown()`.
- Do not format numbers with locale separators on the server. Send plain numbers; the client formats.
- Do not construct endpoint URLs manually. Use `->lookup(Model::class)`, `->relationship()`, or let the plugin auto-generate URLs.
- Do not create a separate `rules()` method for validation. Put rules on the fields.
- Do not use `ActionDefinition` — use the `Action` class pattern.
- Do not use `->type('Email')` on ActionField — use `->email()` and other typed methods.
- Do not use `->sortable()` on columns — it has been removed.
- Use `->disableOnUpdate()` / `->disableOnCreate()` instead of conditional logic in `beforeSave()`.
- Use `->resolveUsing()` / `->fillUsing()` for value transformation, not manual transforms in `beforeSave()`.
- Use `->creationRules()` / `->updateRules()` for context-specific validation.
- Use `afterCreate()` / `afterUpdate()` / `afterDelete()` for side-effects, not `afterSave()`.
