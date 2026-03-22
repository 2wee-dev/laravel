# Lookups

Lookups provide browse-and-select functionality for fields. When a user presses Ctrl+Enter on a lookup field (alternates: Shift+Enter, F6, Ctrl+O), the client requests the lookup endpoint and displays a list to choose from.

## Reusable Lookup Classes

For lookups used across multiple resources (country, postal code, currency), define a lookup class once and reference it everywhere.

Scaffold one with artisan:

```bash
php artisan 2wee:lookup CountryLookup --model=Country
```

```php
// app/TwoWee/Lookups/CountryLookup.php
class CountryLookup
{
    public static function definition(): LookupDefinition
    {
        return LookupDefinition::make(Country::class)
            ->title('Countries')
            ->columns([
                TextColumn::make('code')->label('Code')->width(15),
                TextColumn::make('name')->label('Name')->width('fill'),
            ])
            ->valueColumn('code');
    }
}
```

Then reference it on any field or column:

```php
Text::make('country_code')
    ->uppercase()
    ->lookup(CountryLookup::class)
    ->modal()
```

The plugin detects the lookup class, calls `::definition()`, and registers it automatically. No `lookups()` method needed.

Field-level `->autofill()` merges with the definition's autofill — so you can add resource-specific mappings:

```php
Text::make('post_code')
    ->lookup(PostCodeLookup::class)
    ->filterFrom('country_code')
    ->autofill(['name' => 'city'])     // merged with PostCodeLookup's autofill
```

### Reusable field groups

Combine lookup classes with PHP array spreading for reusable field blocks:

```php
// app/TwoWee/FieldGroups/AddressFields.php
class AddressFields
{
    public static function make(): array
    {
        return [
            Text::make('country_code')->label('Country')->width(10)
                ->uppercase()->lookup(CountryLookup::class)->modal(),
            Text::make('post_code')->label('Post Code')->width(10)
                ->lookup(PostCodeLookup::class)->filterFrom('country_code'),
            Text::make('city')->label('City')->width(30)
                ->quickEntry(false),
        ];
    }
}

// In any resource
Section::make('Address')->fields([
    ...AddressFields::make(),
    Text::make('attention')->label('Attention'),
])
```

## Manual Lookup Definitions

For advanced cases or one-off lookups, define them in the resource's `lookups()` method:

```php
use TwoWee\Laravel\Lookup\LookupDefinition;
use TwoWee\Laravel\Columns\TextColumn;

public static function lookups(): array
{
    return [
        'customer_no' => LookupDefinition::make(\App\Models\Customer::class)
            ->columns([
                TextColumn::make('no')->label('No.')->width(10),
                TextColumn::make('name')->label('Name')->width('fill'),
                TextColumn::make('city')->label('City')->width(20),
            ])
            ->valueColumn('no')
            ->autofill(['city', 'name' => 'customer_name']),
    ];
}
```

The field_id key (`'customer_no'`) must match the field's lookup endpoint.

## LookupDefinition API

```php
LookupDefinition::make($modelClass)
    ->columns([...])           // Column definitions for the lookup list
    ->valueColumn('no')        // Which column value is returned to the field
    ->autofill([...])          // Map lookup columns to card fields
    ->query(fn($q, $context) => $q->where('active', true))  // Custom query filter
    ->display('modal')         // 'modal' or null (full-screen)
```

## Autofill

When the user selects a lookup row, the client auto-fills other fields using the autofill map.

Use a simple array when the lookup column name and the target field name are the same (the common case):

```php
->autofill(['address', 'city', 'phone'])
```

When the names differ, use a key-value pair:

```php
->autofill(['name' => 'customer_name'])
```

Mix both in one call:

```php
->autofill(['address', 'city', 'name' => 'customer_name', 'phone' => 'phone_no'])
// 'address' fills 'address', 'city' fills 'city' — names match, no mapping needed
// 'name' fills 'customer_name', 'phone' fills 'phone_no' — names differ, explicit mapping
```

The client ignores autofill keys that don't match a field on the current screen — a superset is safe.

## Modal vs Full-Screen Lookups

Lookups can display in two modes:

### Full-screen (default)

The lookup opens as a full List screen with server-side pagination. Best for large datasets (customers, items, ledger entries).

```php
// Full-screen — default, no extra config needed
Text::make('customer_no')
    ->lookup(Customer::class, valueColumn: 'no')
    ->autofill(['name' => 'customer_name'])
```

The server returns a page of results (default 50 rows) sorted by the value column. Three request modes:

| Parameter | Behavior |
|-----------|----------|
| `?selected=C-74428` | Start from the selected value's position — opens with the current record at the top |
| `?query=acme` | Search mode — first 50 matches across all columns |
| *(no params)* | First 50 rows sorted by value column |

The client sends `?selected=` with the current field value when opening the lookup, so the list opens at the right position. This handles millions of rows without loading everything.

### Page size

The default page size is 50. Change it globally in config:

```php
// config/twowee.php
'lookup' => [
    'page_size' => 100,
],
```

Or per lookup:

```php
LookupDefinition::make(Customer::class)
    ->pageSize(100)
    ->columns([...])
    ->valueColumn('no')
```

The user types a search term, the client sends `GET /lookup/customer_no?query=acme`, and the server returns matching rows.

### Modal

The lookup opens as an inline overlay on the current screen. All rows load at once — the client filters locally without server round-trips. Best for small datasets (postal codes, currencies, payment terms, country codes).

```php
// Modal — add ->modal()
Text::make('post_code')
    ->lookup(PostCode::class, valueColumn: 'code')
    ->modal()
    ->filterFrom('country_code')
    ->autofill(['name' => 'city'])    // 'name' column fills the 'city' field

Text::make('currency_code')
    ->lookup(CurrencyCode::class, valueColumn: 'code')
    ->modal()
    ->autofill(['description' => 'currency_name'])   // names differ

Text::make('payment_terms_code')
    ->lookup(PaymentTerm::class, valueColumn: 'code')
    ->modal()
    ->autofill(['description' => 'payment_terms'])   // names differ
```

### How modal works

1. User presses Ctrl+Enter on the field (alternates: Shift+Enter, F6, Ctrl+O)
2. Client sends `GET /lookup/post_code` (no `?query=` parameter)
3. Server returns all rows
4. Client renders them in an overlay on top of the current card
5. User types to filter — client filters locally (fuzzy matching)
6. User selects a row — value and autofill applied, overlay closes

### When to use which

| | Modal | Full-screen |
|---|---|---|
| Dataset size | Small (< ~100 rows) | Large |
| Search | Client-side filtering | Server-side `?query=` |
| Loading | All rows at once | Paginated/filtered |
| UX | Stays on the card | Navigates to a list screen |
| Use for | Postal codes, currencies, payment terms | Customers, items, vendors |

### Manual endpoint form

```php
// Using display parameter directly
Text::make('terms')
    ->lookup('payment_terms', 'description', validate: true, display: 'modal')
```

## Drill-Down from Lookups

When a lookup model has a registered TwoWee resource, the lookup response automatically includes a drill-down URL. This lets users inspect a record before selecting it:

1. User opens the customer lookup (Ctrl+Enter or F6)
2. Sees the list of customers
3. Presses Ctrl+Enter on a row to open the full customer card
4. Reviews the card, presses Esc to return to the lookup
5. Presses Enter to select the customer

This is automatic — no configuration needed. The plugin detects that the lookup model (e.g. `Customer`) has a registered resource (`CustomerResource`) and adds the card URL to the response.

For manual `LookupDefinition`, you can set it explicitly:

```php
LookupDefinition::make(Customer::class)
    ->columns([...])
    ->valueColumn('no')
    ->onDrill('/terminal/screen/customers/card/{0}')
```

`{0}` is replaced with the first column value (the record ID).

## Blur Validation

Fields with `validate: true` on their lookup trigger async validation when the user leaves the field:

```php
Text::make('customer_no')
    ->uppercase()
    ->lookup('customer_no', 'customer_name', validate: true)
```

The client calls `GET /validate/customer_no/{value}` and receives:

```json
{ "valid": true, "autofill": { "customer_name": "ACME Corp" }, "error": null }
```

or on failure:

```json
{ "valid": false, "autofill": null, "error": "Customer not found." }
```

## Context-Dependent Lookups

A lookup can depend on other field values. Add `->filterFrom()` to send those values as query parameters:

```php
// Post code filtered by country
Text::make('post_code')
    ->lookup('post_code', null, validate: true)
    ->filterFrom('country_code')
```

When the user opens the lookup or tabs away, the client reads `country_code` from the card and sends:
```
GET /lookup/post_code?country_code=FO
GET /validate/post_code/100?country_code=FO
```

### Multiple context fields

```php
Text::make('post_code')
    ->lookup('post_code', null, validate: true)
    ->filterFrom('country_code', 'region_code')
```
→ `GET /lookup/post_code?country_code=FO&region_code=ST`

### Aliased parameter names

When the query parameter name differs from the field ID:

```php
Text::make('account_no')
    ->lookup('account_no')
    ->filterFrom(['field' => 'account_type', 'param' => 'type'])
```
→ `GET /lookup/account_no?type=Customer`

### Server-side handling

The query modifier receives all context as an associative array:

```php
LookupDefinition::make(\App\Models\PostCode::class)
    ->query(function ($query, array $context) {
        if (! empty($context['country_code'])) {
            $query->where('country_code', $context['country_code']);
        }
    })
```

For polymorphic lookups (grid line items):

```php
LookupDefinition::make(\App\Models\Item::class)
    ->query(function ($query, array $context) {
        $type = $context['type'] ?? null;
        if ($type === 'Resource') {
            $query->from('resources');
        }
    })
```

If the context field is empty, the client omits the parameter. Your query modifier receives an empty or missing key — handle accordingly.

## Drill-Down

Drill-down uses the same lookup infrastructure. Any field with `->disabled()->drillDown()`:

```php
Decimal::make('balance')
    ->disabled()
    ->drillDown('ledger_entries')
    ->color(Color::Yellow)->bold()
```

The `GET /drilldown/ledger_entries/{key}` endpoint returns a read-only List screen.
