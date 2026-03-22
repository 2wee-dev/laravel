# Fields

Fields define the inputs on Card and HeaderLines screens. All field values are serialized as strings.

## Field Types

| Type | Class | Purpose |
|------|-------|---------|
| Text | `Text` | Free text input |
| Decimal | `Decimal` | Numbers with decimal places |
| Integer | `Integer` | Whole numbers |
| Date | `Date` | Date with shortcuts (t=today) |
| Time | `Time` | 24-hour time |
| Email | `Email` | Email addresses |
| Phone | `Phone` | Phone numbers |
| URL | `Url` | Web addresses |
| Boolean | `Boolean` | Toggle (true/false) |
| Option | `Option` | Fixed choices dropdown |
| Password | `Password` | Masked input |
| TextArea | `TextArea` | Multi-line text |
| DateRange | `DateRange` | Date range filter |
| Separator | `Separator` | Visual divider between field groups |

For uppercase code fields (customer numbers, product codes), use `Text::make('no')->uppercase()`.

## Common API

All fields share these builder methods:

```php
Text::make('name')              // Create field with model attribute name
    ->label('Name')             // Display label
    ->width(30)                 // Character width hint
    ->default('Default')        // Default value for new records
    ->placeholder('Enter...')   // Dimmed hint text
    ->required()                // Mark as required (client + server)
    ->maxLength(100)            // Maximum character length (client + server)
    ->minLength(3)              // Minimum character length (client + server)
    ->inputMask('uppercase')    // Input mask: 'uppercase', 'lowercase', 'digits_only'
    ->disabled()                // Read-only (always)
    ->disableOnUpdate()         // Editable on create, read-only after first save
    ->disableOnCreate()         // Read-only on create, editable after first save
    ->hidden()                  // Exclude from JSON output
    ->quickEntry(false)         // Skip this field on Enter (fast data entry)
    ->focus()                   // Initial cursor position when card opens
    ->showZero()                // Show 0 instead of blank for zero numeric values
    ->lookup($endpoint, $displayField, $validate, $display)
    ->blurValidate()            // Validate on blur (server-side)
    ->color(Color::Yellow)               // Text color (see Colors section)
    ->bold()                        // Bold text

    // Server-side validation rules (used on blur + save)
    ->nullable()                // Allow null
    ->email()                   // Must be valid email
    ->unique('table', 'col')    // Must be unique (auto-ignores on update)
    ->exists('table', 'col')    // Must exist in table
    ->enum(MyEnum::class)       // Must be valid enum value
    ->in(['a', 'b', 'c'])      // Must be one of these values
    ->regex('/^[A-Z]+$/')       // Must match regex
    ->numeric()                 // Must be numeric
    ->integer()                 // Must be integer
    ->string()                  // Must be string
    ->confirmed()               // Must match {field}_confirmation
    ->after('start_date')       // Date must be after another field
    ->before('end_date')        // Date must be before another field
    ->same('other_field')       // Must match another field
    ->different('other_field')  // Must differ from another field
    ->rules(['alpha_num'])      // Any raw Laravel validation rules
    ->creationRules(['unique:items,code'])  // Rules only on create
    ->updateRules(['sometimes'])            // Rules only on update

    // Value transformation hooks
    ->resolveUsing(fn ($value, $model) => ...)  // DB → client (transform before display)
    ->fillUsing(fn ($value, $model) => ...)     // Client → DB (transform before save)
```

## Quick Entry

`quickEntry` and `focus` control field navigation:

| What | Behavior |
|------|----------|
| **Enter navigation** | Enter jumps only to fields where `quickEntry` is `true` |
| **Initial focus (default)** | Cursor lands on the first `quickEntry: true` + `editable: true` field |
| **Initial focus (explicit)** | `->focus()` overrides — cursor lands on this field regardless of `quickEntry` |
| **Tab navigation** | Unaffected — Tab visits ALL fields in document order |
| **Arrow keys** | Unaffected — spatial movement |

### Default: `true`

All fields are in the Enter path by default. You only write `->quickEntry(false)` on fields you want to skip — typically autofilled fields, pre-populated references, and rarely-edited fields.

This follows the [Microsoft Business Central QuickEntry pattern](https://learn.microsoft.com/en-us/dynamics365/business-central/dev-itpro/developer/properties/devenv-quickentry-property): *"The default value is true. To specify that a control can be skipped, change this value to false."*

### Example

```php
Section::make('General')->fields([
    Text::make('no')->label('No.')->disabled(),              // focus skips (disabled)
    Text::make('customer_no')->label('Customer No.')
        ->uppercase()->relationship('customer', 'name')
        ->focus(),                                           // ← INITIAL FOCUS lands here
    Text::make('customer_name')->label('Name')
        ->disabled()->quickEntry(false),                     // Enter SKIPS (autofilled)
    Text::make('address')->label('Address')
        ->quickEntry(false),                                 // Enter SKIPS (autofilled)
    Text::make('city')->label('City')
        ->quickEntry(false),                                 // Enter SKIPS (autofilled)
]),

Section::make('Details')->fields([
    Date::make('order_date')->label('Order Date'),           // Enter stops here
    Date::make('posting_date')->label('Posting Date'),       // Enter stops here
    Text::make('your_reference')->label('Your Reference'),   // Enter stops here
    Text::make('payment_terms')->label('Payment Terms')
        ->lookup(PaymentTerm::class)->modal(),               // Enter stops here
]),
```

**When the card opens:** Cursor lands on Customer No. because it has `->focus()`. Without `->focus()`, the cursor would still land here (first `quickEntry: true` + `editable: true` field) — but `->focus()` makes it explicit and overrides the default when needed.

**Enter path:** Customer No. → Order Date → Posting Date → Your Reference → Payment Terms.

**Tab path:** Customer No. → Customer Name → Address → City → Order Date → Posting Date → Your Reference → Payment Terms (all editable fields).

The autofilled fields (Name, Address, City) are skipped by Enter and initial focus, but still reachable with Tab when the user needs to override them.

### Rule of thumb

Set `->quickEntry(false)` on:
- Fields that get their value from a lookup autofill (customer name, address, city)
- Computed or read-only fields (totals, calculated amounts)
- Fields that are rarely edited during normal data entry

Leave everything else at the default (`true`). No need to write `->quickEntry(true)` — that's the default.

## Relationship

Auto-wire a lookup from a BelongsTo relationship — no manual `LookupDefinition` needed:

```php
Text::make('country_id')
    ->uppercase()
    ->relationship('country', 'name')
```

This automatically:
- Creates a `LookupDefinition` from the `country()` BelongsTo relationship
- Sets up the lookup endpoint for Ctrl+Enter browsing
- Enables blur validation
- Autofills the title attribute to `{relationship}_{attribute}` — e.g. `relationship('country', 'name')` autofills to the `country_name` field on the card

Name your card fields to match the convention and autofill works automatically:

```php
Text::make('customer_no')
    ->relationship('customer', 'name'),       // autofills → customer_name
Text::make('customer_name')->disabled(),      // receives the autofill

Text::make('salesperson_code')
    ->relationship('salesperson', 'name'),    // autofills → salesperson_name
Text::make('salesperson_name')->disabled(),   // no conflict with customer_name
```

For custom mappings, use `->autofill()` to override:

```php
Text::make('customer_no')
    ->relationship('customer', 'name')
    ->autofill(['name' => 'sell_to_name', 'city' => 'sell_to_city'])
```

## Lookup

Connect a field to a lookup for Ctrl+Enter browsing and blur validation.

### Inline lookup (recommended)

Pass a model class — the plugin auto-wires everything:

```php
// Autofill — list the columns that should fill on selection
Text::make('item_id')
    ->lookup(Item::class, valueColumn: 'no')
    ->autofill(['description', 'unit_of_measure', 'unit_price'])

// When the lookup column name differs from the target field name, use key => value
Text::make('customer_no')
    ->lookup(Customer::class, valueColumn: 'no')
    ->autofill(['address', 'city', 'name' => 'customer_name'])

// With cross-field filtering
Text::make('post_code')
    ->lookup(PostCode::class, valueColumn: 'code')
    ->filterFrom('country_code')
    ->autofill(['name' => 'city'])

// Modal overlay — for small datasets
Text::make('currency_code')
    ->lookup(CurrencyCode::class, valueColumn: 'code')
    ->modal()
    ->autofill(['description' => 'currency_name'])
```

This automatically:
- Creates a `LookupDefinition` from the model
- Uses the registered resource's `table()` columns, or auto-generates from `$fillable`
- Sets up the lookup endpoint with prefix
- Enables blur validation
- No separate `lookups()` method needed

### Cross-field filtering

Make lookups depend on other field values using `filterFrom()`:

```php
Text::make('post_code')
    ->lookup(PostCode::class, valueColumn: 'code')
    ->filterFrom('country_code')                              // ?country_code=FO
    ->filterFrom('country_code', 'region_code')               // multiple fields
    ->filterFrom(['field' => 'account_type', 'param' => 'type'])  // aliased param
```

The client reads the referenced fields on the card and sends their values as query parameters.

### Modal vs full-screen

By default, lookups open as a full-screen list with server-side search (`?query=`). For small datasets, use `->modal()` to show an inline overlay where all rows load at once and the client filters locally:

```php
Text::make('payment_terms')->lookup(PaymentTerm::class)->modal()
Text::make('currency_code')->lookup(CurrencyCode::class, valueColumn: 'code')->modal()
```

### Manual lookup

For full control, pass a string endpoint:

```php
Text::make('customer_no')
    ->uppercase()
    ->lookup('customer_no', 'customer_name', validate: true)
    ->lookup('customer_no', 'customer_name', display: 'modal')
```

See [lookups.md](lookups.md) for the full lookup system documentation.

## Color and Bold

Set the field text color and weight:

```php
Decimal::make('balance')
    ->color(Color::Yellow)->bold()

Decimal::make('total')
    ->color(Color::Green)

Text::make('warning')
    ->bold()
```

## Colors

Use the `Color` enum for type safety and IDE autocomplete:

| Enum | Value |
|------|-------|
| `Color::Yellow` | `'yellow'` |
| `Color::Red` | `'red'` |
| `Color::Green` | `'green'` |
| `Color::Blue` | `'blue'` |
| `Color::Cyan` | `'cyan'` |
| `Color::Magenta` | `'magenta'` |
| `Color::White` | `'white'` |
| `Color::Black` | `'black'` |
| `Color::Gray` | `'gray'` |

The `->color()`, `->trueColor()`, and `->falseColor()` methods accept a `Color` enum value:

```php
use TwoWee\Laravel\Enums\Color;

->color(Color::Yellow)->bold()
->trueColor(Color::Green)
->falseColor(Color::Red)
```

## Input Masks

Convenience methods for common input masks:

```php
Text::make('customer_no')->uppercase()       // Forces uppercase input
Text::make('slug')->lowercase()              // Forces lowercase input
Text::make('phone')->digitsOnly()            // Only digits allowed
Text::make('code')->pattern('^[A-Z]{2}\d{4}$')  // Regex (client + server)
```

## Decimal

```php
Decimal::make('amount')
    ->decimals(2)      // Decimal places (default: 2)
    ->min(0)           // Minimum value
    ->max(999999.99)   // Maximum value
```

The server sends plain numbers (period decimal, no thousand separator). The client formats for display using the locale config.

## Integer

```php
Integer::make('quantity')
    ->min(1)
    ->max(10000)
```

## Date

```php
Date::make('posting_date')
    ->format('DD-MM-YY')    // Display format hint
```

## Option

```php
// Simple array (value = label)
Option::make('status')->options(['Active', 'Inactive'])

// Associative (key = value, value = label)
Option::make('type')->options([
    'item' => 'Item',
    'gl' => 'G/L Account',
    'resource' => 'Resource',
])
```

## Boolean

Two-state toggle. Renders as plain text — "Yes" or "No" by default.

```php
Boolean::make('privacy_blocked')
    ->label('Privacy Blocked')
    ->trueColor(Color::Green)        // Color when true (default: field color)
```

The user toggles with Space. Enter advances to the next field without toggling. The bottom bar shows "Space  Toggle" when focused.

### Labels

Override the default "Yes"/"No" text:

```php
Boolean::make('shipped')
    ->trueLabel('Shipped')
    ->falseLabel('Not shipped')
```

| Method | Default | Description |
|--------|---------|-------------|
| `->trueLabel(string)` | `"Yes"` | Text shown when true |
| `->falseLabel(string)` | `"No"` | Text shown when false |

### Colors

```php
Boolean::make('blocked')
    ->trueColor(Color::Red)          // Color when true
    ->falseColor(Color::Green)       // Color when false
```

If not set, both states use the normal field value color. See [Colors](#colors) for available names.

### Saving

The client sends `"true"` or `"false"` as a string. Cast it in your model:

```php
protected $casts = [
    'privacy_blocked' => 'boolean',
    'shipped' => 'boolean',
];
```

## TextArea

Multi-line text input for notes, comments, and message bodies.

```php
TextArea::make('notes')
    ->label('Notes')
    ->rows(4)           // Number of visible rows (default: 4)
    ->nullable()
```

- The value is stored and sent as a plain string with `\n` line separators.
- `rows` controls the visible height. The client enforces a maximum of `rows` lines — inserting beyond that shows an error.
- Validate line count server-side by counting `\n` occurrences + 1.
- `quickEntry(false)` is set automatically — TextArea fields are skipped by Enter. The cursor still reaches the field when there are no more quick-entry fields ahead.
- User inserts newlines with Ctrl+Enter, moves to next field with Enter.

## Separator

A visual divider between field groups within a section. Not interactive, not editable.

```php
Section::make('General')
    ->fields([
        Text::make('no')->label('No.')->uppercase(),
        Text::make('name')->label('Name'),
        Separator::make(),
        Text::make('address')->label('Address'),
        Text::make('city')->label('City'),
    ])
```

The ID is always `"separator"` — it's a visual element, not a data field.

## Context-Aware Editability

Control whether a field is editable based on create vs update context. The field always renders in the same position — only editability changes. No layout shift.

### disableOnUpdate

Editable when creating a new record, read-only after first save. Use for natural keys that shouldn't change:

```php
Text::make('no')->label('No.')->disableOnUpdate()
Text::make('customer_no')->label('Customer No.')->disableOnUpdate()
```

### disableOnCreate

Read-only when creating, editable on existing records. Use for fields that only make sense after the record exists:

```php
Option::make('status')->options(['Draft', 'Open', 'Closed'])->disableOnCreate()
```

### disabled (always)

Use `disabled()` when the field should never be editable:

```php
Decimal::make('balance')->disabled()  // always read-only
```

### Save behavior

Disabled fields are automatically stripped from the save payload. If a field is disabled (by any method), the server ignores any value the client sends for it. You don't need to guard against this in `beforeSave()`.

## Value Transformation

Two hooks for transforming values between the database and the client. Both are server-side only — the client never knows they exist.

### resolveUsing (DB → client)

Transforms the database value before sending it to the client:

```php
// Database stores cents (4500), client sees "45.00"
Decimal::make('price')
    ->resolveUsing(fn ($value, $model) => $value !== null ? $value / 100 : null)

// Presentation field — value comes from an API, not the database
Text::make('exchange_rate')
    ->disabled()
    ->resolveUsing(fn ($value, $model) => $model?->currency
        ? CurrencyService::getRate($model->currency)
        : null)

// Concatenate fields for display
Text::make('full_address')
    ->disabled()
    ->resolveUsing(fn ($value, $model) => implode(', ', array_filter([
        $model?->street, $model?->city, $model?->country,
    ])))
```

### fillUsing (client → DB)

Transforms the client value before writing to the database:

```php
// Client sends "45.00", database stores 4500
Decimal::make('price')
    ->resolveUsing(fn ($value, $model) => $value !== null ? $value / 100 : null)
    ->fillUsing(fn ($value, $model) => (int) ($value * 100))

// Auto-generate slug from value
Text::make('slug')
    ->fillUsing(fn ($value, $model) => Str::slug($value))
```

`fillUsing` runs after input mask normalization and `parseValue()`. If you previously did value transformations in `beforeSave()`, you can move them to the field definition.

### Presentation fields

Combine `disabled()` + `resolveUsing()` for fields that display computed or external data without a database column:

```php
Text::make('computed_total')
    ->label('Grand Total')
    ->disabled()
    ->resolveUsing(fn ($value, $model) => $model?->lines()->sum('line_amount'))
    ->color(Color::Yellow)->bold()
```

The field renders on the card, shows the value, the user can't edit it, and on save the server ignores it.

## Aggregate Fields

Compute a field value from a HasMany relationship. The field is automatically disabled (it's computed, not editable).

Both model class and relationship string are supported:

```php
// Preferred — IDE-friendly, no magic strings
Decimal::make('balance')
    ->label('Balance (LCY)')
    ->decimals(2)
    ->aggregate(CustomerLedgerEntry::class, 'sum', 'remaining_amount')
    ->drillDown(CustomerLedgerEntry::class)
    ->color(Color::Yellow)->bold()

// Fallback — when two relationships point to the same model
Decimal::make('balance')
    ->aggregate('ledgerEntries', 'sum', 'remaining_amount')
    ->drillDown('ledgerEntries')
    ->color(Color::Yellow)->bold()

Integer::make('order_count')
    ->label('No. of Orders')
    ->aggregate(SalesOrder::class, 'count')
    ->drillDown(SalesOrder::class)
    ->color(Color::Yellow)->bold()
```

Supported aggregate functions: `sum`, `count`, `avg`, `min`, `max`.

When you pass a model class, the plugin inspects the parent model's methods to find the HasMany/HasOne relationship that returns that class. If exactly one match is found, it's used. If zero or multiple matches exist, it throws a clear exception telling you to use the string form.

At render time: `$model->ledgerEntries()->sum('remaining_amount')`. No model accessor needed.

## Drill-Down Fields

Any field can be a drill-down — a read-only field that opens related data on Ctrl+Enter.

### Relationship drilldown (recommended)

Pass a model class or relationship name. The plugin queries the relationship directly — no URL building, no ID plumbing:

```php
// Model class (preferred)
Decimal::make('balance')
    ->aggregate(CustomerLedgerEntry::class, 'sum', 'remaining_amount')
    ->drillDown(CustomerLedgerEntry::class)
    ->color(Color::Yellow)->bold()

// Relationship string (fallback)
Decimal::make('balance')
    ->aggregate('ledgerEntries', 'sum', 'remaining_amount')
    ->drillDown('ledgerEntries')
    ->color(Color::Yellow)->bold()
```

The plugin auto-generates the drilldown URL (`/drilldown/balance/{id}`) and the controller queries `$model->ledgerEntries()->get()`. Columns are resolved from:
1. Explicit columns: `->drillDown(LedgerEntry::class, columns: [...])`
2. A registered resource for the related model (its `table()` method)
3. Auto-detected from the model's attributes

### Manual drilldown

For cases where you need full control:

```php
// {id} placeholder — replaced with the record's primary key
Decimal::make('balance')
    ->disabled()
    ->drillDown('/drilldown/balance/{id}')
    ->color(Color::Yellow)->bold()

// Closure — receives the model
Integer::make('order_count')
    ->disabled()
    ->drillDown(fn ($model) => '/drilldown/orders/' . $model->getKey())

// Explicit drilldown columns
Decimal::make('balance')
    ->aggregate('ledgerEntries', 'sum', 'remaining_amount')
    ->drillDown('ledgerEntries', columns: [
        DateColumn::make('posting_date')->label('Date')->width(12),
        TextColumn::make('description')->label('Description')->width('fill'),
        DecimalColumn::make('amount')->label('Amount')->decimals(2)->width(15),
    ])
```

