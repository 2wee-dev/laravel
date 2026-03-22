# Validation

TwoWee uses three layers of validation. No server calls happen during editing — the client validates locally, then checks with the server only on blur (for lookup fields) and on save.

## Layer 1: Client-Side Rules

Rules declared on fields are sent to the client in the `validation` object. The client enforces them immediately as the user types — no round-trip needed.

```php
Text::make('name')
    ->required()                    // validation.required = true
    ->minLength(3)                  // validation.min_length = 3
    ->maxLength(100)                // validation.max_length = 100
    ->inputMask('uppercase')        // validation.input_mask = "uppercase"

Decimal::make('amount')
    ->decimals(2)                   // validation.decimals = 2
    ->min(0)                        // validation.min = 0
    ->max(999999.99)                // validation.max = 999999.99

Text::make('no')
    ->uppercase()
    ->pattern('^[A-Z]{2}\d{4}$')   // validation.pattern = regex
```

Available rules:

| Rule | Applies to | Effect |
|------|-----------|--------|
| `required` | All fields | Cannot be empty |
| `max_length` | Text fields | Character limit |
| `min_length` | Text fields | Minimum characters |
| `input_mask` | Text fields | `"uppercase"`, `"lowercase"`, `"digits_only"` |
| `pattern` | Text fields | Regex the value must match |
| `decimals` | Decimal fields | Maximum decimal places |
| `min` / `max` | Numeric fields | Value bounds |

### Input mask normalization

Fields with `->uppercase()` or `->lowercase()` are automatically normalized before validation and save. The plugin transforms the value before any rules run:

```php
Text::make('country_code')
    ->uppercase()
    ->exists('countries', 'code')
```

User types `dk` → plugin normalizes to `DK` → `exists` rule checks `WHERE code = 'DK'` → passes → model filled with `DK`.

No custom closure rules or model mutators needed for case normalization.

## Layer 2: Blur Validation

When a field has blur validation enabled, the client calls the server when the user tabs away. There are two flavours: **lookup blur** (checks a related model and returns autofill data) and **rules blur** (validates against Laravel rules from `Resource::rules()`).

### Lookup blur (with autofill)

For fields tied to a related model. The server checks the value exists and returns related data.

**Step 1:** Mark the field with `validate: true` on the lookup:

```php
Text::make('customer_no')
    ->uppercase()
    ->lookup('customer_no', 'customer_name', validate: true)
```

**Step 2:** Define the lookup in your Resource:

```php
public static function lookups(): array
{
    return [
        'customer_no' => LookupDefinition::make(\App\Models\Customer::class)
            ->columns([
                TextColumn::make('no')->label('No.')->width(10),
                TextColumn::make('name')->label('Name')->width('fill'),
            ])
            ->valueColumn('no')
            ->autofill(['city', 'name' => 'customer_name', 'phone' => 'phone_no']),
    ];
}
```

### What happens at runtime

1. User types `10000` in the Customer No. field and presses Tab
2. Client sends `GET /validate/customer_no/10000`
3. `ValidateController` finds the `LookupDefinition` for `customer_no`
4. `LookupDefinition::validateValue()` queries `Customer::where('no', '10000')`
5. If found, returns the autofill data:

```json
{
    "valid": true,
    "autofill": {
        "customer_name": "ACME Corp",
        "city": "Reykjavik",
        "phone_no": "555-1234"
    },
    "error": null
}
```

6. Client fills in the Customer Name, City, and Phone fields automatically

If the value doesn't exist:

```json
{
    "valid": false,
    "autofill": null,
    "error": "Value not found."
}
```

The client keeps focus on the field and shows the error — the user must fix or clear it before moving on.

### Polymorphic validation

For grid columns where the lookup model depends on a type column (e.g., Item vs Resource vs G/L Account), the client appends `?type=` to the validation call:

```
GET /validate/line_item/1000?type=Item
```

Handle this in your `LookupDefinition` query closure:

```php
'line_item' => LookupDefinition::make(\App\Models\Item::class)
    ->query(function ($query, array $context) {
        if (($context['type'] ?? null) === 'Resource') {
            $query->from('resources');
        }
    })
    ->valueColumn('no')
    ->autofill(['description']),
```

### Rules blur (without autofill)

For plain fields that need server-side validation on blur but aren't tied to a lookup. Rules live on the field itself — one source of truth for both blur and save.

Mark the field with `blurValidate()` and add rules directly on the field:

```php
Text::make('name')
    ->label('Name')
    ->required()
    ->minLength(3)
    ->maxLength(100)
    ->blurValidate()
```

When the user tabs out of the `name` field, the client calls `GET /validate/name/Ab` and the server runs the field's rules:

```json
{
    "valid": false,
    "autofill": null,
    "error": "The name field must be at least 3 characters."
}
```

### How the controller resolves validation

The `ValidateController` checks in order:

1. **Lookup** — if a `LookupDefinition` exists for the field_id, validate against the model and return autofill
2. **Field rules** — if the field is registered as blur-validated, pull its rules from the field definition and run `Validator::make()`
3. **404** — if neither exists, return an error

This means lookup fields and rules fields share the same `GET /validate/{field_id}/{value}` endpoint. A field with both a lookup and `blurValidate()` uses the lookup (it's more specific).

## Layer 3: Save Validation

When the user presses Ctrl+S, the client sends a `SaveChangeset` with all changed fields. The `SaveController` collects rules from all form fields and validates before persisting.

### Rules on fields

Rules live on the fields themselves — the same rules used for blur validation are also used on save:

```php
public static function form(): array
{
    return [
        Section::make('General')->fields([
            Text::make('no')->label('No.')->required()
                ->unique('customers', 'no')
                ->uppercase(),
            Text::make('name')->label('Name')->required()
                ->minLength(3)->maxLength(100)
                ->blurValidate(),
            Email::make('email')->label('E-Mail')
                ->nullable()->email()
                ->unique('customers', 'email')
                ->blurValidate(),
            Decimal::make('credit_limit')->label('Credit Limit')
                ->nullable()->rules(['numeric', 'min:0']),
        ]),
    ];
}
```

Available rule builders on Field:

| Method | Laravel rule |
|--------|-------------|
| `->required()` | `required` |
| `->nullable()` | `nullable` |
| `->minLength(3)` | `min:3` |
| `->maxLength(100)` | `max:100` |
| `->email()` | `email` |
| `->unique('table', 'column')` | `Rule::unique(...)` with auto-ignore on update |
| `->exists('table', 'column')` | `Rule::exists(...)` |
| `->enum(StatusEnum::class)` | `Rule::enum(...)` |
| `->in(['a', 'b', 'c'])` | `Rule::in(...)` |
| `->regex('/pattern/')` | `regex:/pattern/` |
| `->rules(['alpha_num', ...])` | Any raw Laravel rules |
| `->creationRules([...])` | Rules only applied when creating |
| `->updateRules([...])` | Rules only applied when updating |

The `unique()` builder automatically calls `->ignore($model->getKey())` on updates, so you don't need to handle that yourself.

### Context-specific rules

Use `creationRules()` and `updateRules()` when the validation differs between create and update:

```php
Text::make('code')
    ->rules(['alpha_num'])                              // always
    ->creationRules(['required', 'unique:items,code'])  // only on create
    ->updateRules(['sometimes'])                        // only on update

Text::make('email')
    ->rules(['email'])
    ->creationRules(['required'])
    ->updateRules(['sometimes'])
```

The `rules()` method applies in both contexts. `creationRules()` are merged on create, `updateRules()` on update.

### Resource::rules() override

For cross-field rules or rules that can't be expressed on a single field, you can still override `rules()` on the Resource. These are merged with field-level rules (field rules take precedence for the same key):

```php
public static function rules(?Model $model = null): array
{
    return [
        // Cross-field rule: end_date must be after start_date
        'end_date' => 'after:start_date',
    ];
}
```

### Sparse changesets

On **store** (new record): all rules run.
On **update**: only rules for fields present in the changeset run. This means `required` on an untouched field won't fire — no need for the `sometimes` prefix.

### Line validation

For HeaderLines/Grid layouts, define `lineRules()` for per-row validation. Keys are column names from `lineColumns()`:

```php
public static function lineRules(): array
{
    return [
        'no' => 'required|string',
        'quantity' => 'required|numeric|min:0.01',
        'unit_price' => 'required|numeric|min:0',
    ];
}
```

Each non-empty row is validated individually. Errors include the line number: `"Error: Line 3: The quantity field is required."`

### Error handling

On validation failure, the `SaveController` returns the card with the first error in the `status` field. The card stays open with the user's values intact — no data is lost:

```json
{
    "layout": "Card",
    "title": "Customer Card - 10000",
    "status": "Error: The name field is required.",
    "sections": [...],
    ...
}
```

### No rules = no validation

If `rules()` returns an empty array (the default), the `SaveController` skips validation and saves directly. This is backwards-compatible — existing resources work unchanged.

## Why not Laravel Precognition?

Precognition is designed for HTML forms — it sends partial form data to form requests and returns 422 error bags. TwoWee needs something different:

- **Per-field endpoints** (`/validate/customer_no/10000`) not per-form
- **Autofill responses** (return related data, not just valid/invalid)
- **Lookup-driven** (validation is "does this value exist in a related model?")
- **Custom response shape** (`{ valid, autofill, error }`)

The plugin's `LookupDefinition::validateValue()` + `ValidateController` maps directly to the protocol in ~30 lines.
