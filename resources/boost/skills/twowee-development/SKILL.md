---
name: twowee-development
description: Build and work with TwoWee terminal UI resources, including forms, lists, grids, lookups, actions, and save hooks.
---

# TwoWee Development

## When to use this skill

Use this skill when creating or modifying TwoWee resources in `app/TwoWee/Resources/`, working with terminal UI fields, columns, lookups, actions, or save logic.

## File Structure

```
app/TwoWee/Resources/
    CustomerResource.php
    SalesOrderResource.php
    JournalResource.php
```

Resources are auto-discovered. Each extends `TwoWee\Laravel\Resource` (or `TwoWee\Laravel\GridResource` for full-screen grids).

## Resource Properties

```php
class SalesOrderResource extends Resource
{
    protected static string $model = SalesOrder::class;
    protected static string $label = 'Sales Order';
    protected static ?string $slug = 'sales_orders';           // auto-generated if omitted
    protected static ?string $recordKey = 'no';                // natural key for URLs
    protected static ?string $screenId = null;                 // defaults to slug
    protected static bool $showInNavigation = true;
    protected static int $navigationSort = 2;
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = null;          // defaults to label
    protected static ?string $navigationPopup = null;          // group into popup sub-list
    protected static bool $navigationSeparatorBefore = false;  // divider line above
    protected static ?string $navigationParent = null;         // Escape fallback menu (default: /menu/main)
    protected static ?string $saveAction = null;               // Codeunit-style save delegation
    protected static ?string $deleteAction = null;             // Codeunit-style delete delegation
}
```

## Card Screen (form)

```php
public static function form(): array
{
    return [
        Section::make('General')
            ->column(0)->rowGroup(0)
            ->fields([
                Text::make('no')->label('No.')->disabled()->default('Auto'),
                Separator::make(),
                Text::make('customer_no')->label('Customer No.')
                    ->required()
                    ->focus()                                     // initial cursor
                    ->lookup(Customer::class, valueColumn: 'no')
                    ->autofill([
                        'name' => 'customer_name',
                        'address' => 'address',
                        'city' => 'city',
                    ]),
                Separator::make(),
                Text::make('customer_name')->label('Name')
                    ->quickEntry(false),                          // Enter skips (autofilled)
                Text::make('address')->label('Address')
                    ->quickEntry(false),
                Text::make('city')->label('City')
                    ->quickEntry(false),
            ]),

        Section::make('Details')
            ->column(1)->rowGroup(0)
            ->fields([
                Date::make('order_date')->label('Order Date')
                    ->required()->default(now()->format('d-m-Y')),
                Date::make('due_date')->label('Due Date'),
                Text::make('your_reference')->label('Your Reference')
                    ->nullable()->maxLength(50),
                Text::make('salesperson_code')->label('Salesperson')
                    ->uppercase()->nullable()
                    ->relationship('salesperson', 'name')
                    ->modal(),
            ]),

        Section::make('Totals')
            ->column(1)->rowGroup(1)
            ->fields([
                Decimal::make('total_amount')->label('Total')
                    ->disabled()->color(Color::Yellow)->bold(),
            ]),
    ];
}
```

## List Screen (table)

```php
public static function table(): array
{
    return [
        TextColumn::make('no')->label('No.')->width(10),
        TextColumn::make('customer_name')->label('Customer')->width('fill'),
        DecimalColumn::make('total_amount')->label('Total')
            ->decimals(2)->width(15)->align('right'),
    ];
}
```

## HeaderLines (document with editable grid)

```php
public static function layout(): string { return 'HeaderLines'; }
public static function linesRelation(): ?string { return 'lines'; }
public static function linesOverlayPct(): int { return 65; }

public static function lineColumns(): array
{
    return [
        OptionColumn::make('line_type')->label('Type')
            ->width(9)->editable()->quickEntry(false)
            ->options(['', 'Item', 'Resource', 'Text']),

        TextColumn::make('item_id')->label('No.')
            ->width(12)->editable()
            ->lookup(Item::class, valueColumn: 'no')
            ->autofill(['description', 'unit_of_measure', 'unit_price'])
            ->filterFrom('line_type'),                   // polymorphic context

        TextColumn::make('description')->label('Description')
            ->width('fill')->editable()->quickEntry(false),

        DecimalColumn::make('quantity')->label('Qty')
            ->width(10)->align('right')->editable(),     // Enter stops here

        DecimalColumn::make('unit_price')->label('Unit Price')
            ->width(12)->align('right')->editable()->quickEntry(false),

        DecimalColumn::make('line_amount')->label('Amount')
            ->width(14)->align('right')
            ->formula('quantity * unit_price * (1 - discount_pct / 100)'),
    ];
}
```

## Grid Screen (full-screen editable)

```php
use TwoWee\Laravel\GridResource;

class JournalResource extends GridResource
{
    protected static string $model = JournalLine::class;

    public static function lineColumns(): array
    {
        return [
            DateColumn::make('posting_date')->label('Date')->editable()->width(12),
            TextColumn::make('account_no')->label('Account')
                ->editable()->width(15)
                ->lookup(GlAccount::class, valueColumn: 'no')
                ->autofill(['name' => 'description']),
            TextColumn::make('description')->label('Description')
                ->editable()->width('fill'),
            DecimalColumn::make('amount')->label('Amount')
                ->editable()->decimals(2)->width(15)->align('right'),
        ];
    }
}
```

## Lookups

```php
// Inline (preferred) — auto-generates LookupDefinition
Text::make('customer_no')
    ->lookup(Customer::class, valueColumn: 'no')
    ->autofill(['city', 'name' => 'customer_name'])

// With cross-field filtering
Text::make('post_code')
    ->lookup(PostCode::class, valueColumn: 'code')
    ->filterFrom('country_code')

// Modal (small datasets)
Text::make('currency_code')
    ->lookup(Currency::class, valueColumn: 'code')
    ->modal()

// BelongsTo relationship
Text::make('salesperson_code')
    ->relationship('salesperson', 'name')
    ->modal()
```

## Aggregate + Drill-Down

```php
Decimal::make('balance')
    ->aggregate('ledgerEntries', 'sum', 'remaining_amount')
    ->drillDown('ledgerEntries')
    ->color(Color::Yellow)->bold()
```

## Validation

Rules live on fields. They serve both blur validation and save validation:

```php
Text::make('name')->required()->minLength(3)->maxLength(100)->blurValidate()
Email::make('email')->nullable()->email()->unique('customers', 'email')->blurValidate()
Text::make('country_code')->uppercase()->exists('countries', 'code')->blurValidate()
Decimal::make('credit_limit')->nullable()->numeric()->min(0)

// Context-specific rules
Text::make('code')
    ->rules(['alpha_num'])
    ->creationRules(['required', 'unique:items,code'])
    ->updateRules(['sometimes'])
```

## Actions

```php
use TwoWee\Laravel\Actions\Action;
use TwoWee\Laravel\Actions\ActionField;
use TwoWee\Laravel\Actions\ActionResult;

public static function screenActions(?Model $model = null): array
{
    return [
        Action::make('send_email')
            ->label('Send as Email')
            ->visible(fn ($record) => $record?->status !== 'draft')
            ->form(fn ($record) => [
                ActionField::make('to')->label('To')->email()
                    ->value($record?->email ?? '')->required(),
            ])
            ->action(fn ($record, $data) => ActionResult::success('Sent to ' . $data['to'])),

        Action::make('post')
            ->label('Post')
            ->requiresConfirmation('Post this document? This cannot be undone.')
            ->action(fn ($record) => ActionResult::success('Posted.')),
    ];
}
```

## Save Hooks

```php
// Data sync (totals, computed fields) — only when $saveAction is not set
public static function afterSave(Model $model): void
{
    $model->update([
        'total_amount' => $model->lines()->sum('line_amount'),
    ]);
}

// Side-effects — always fires, even with $saveAction
public static function afterCreate(Model $model): void
{
    Mail::to($model->email)->send(new WelcomeCustomer($model));
}

public static function afterUpdate(Model $model): void
{
    Cache::forget("customer.{$model->id}");
}

public static function afterDelete(Model $model): void
{
    AuditLog::record('deleted', $model->id);
}
```
```

## Custom Title

```php
// Default: "{label} - {recordKeyValue}"
// Override for custom:
public static function title(Model $model): string
{
    return 'Sales Order - ' . $model->no;
}
```

## Important Rules

- Wire format is plain English numbers (period decimal). Never use number_format with locale separators.
- Use `->uppercase()` not `Code::make()`. Use `Decimal::make()->aggregate()` not `FlowField::make()`.
- Use `->lookup(Model::class)` not `->lookup('string_endpoint')`.
- Use `Action::make()` not `ActionDefinition::make()`.
- Set `$recordKey` when the first table column is not the primary key.
- Use `->quickEntry(false)` on autofilled fields. Use `->focus()` on the first editable field.
- Put validation rules on the field. Use `->blurValidate()` for server-side blur checking.
- Use `afterSave()` for calculated fields. The server is the source of truth; `->formula()` is client-side preview only.
- Use `->disableOnUpdate()` for fields that should be locked after first save (e.g. customer number).
- Use `->resolveUsing()` / `->fillUsing()` for value transformation instead of doing it in `beforeSave()`.
- Use `->creationRules()` / `->updateRules()` for context-specific validation.
- Use `afterCreate()` / `afterUpdate()` / `afterDelete()` for side-effects (notifications, events).
- Do not use `->sortable()` on columns — it has been removed. The server decides the order.
