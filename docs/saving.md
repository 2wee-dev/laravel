# Saving

## Save Flow

1. User edits fields freely (no server calls during editing)
2. Lookup blur validation happens async on field exit
3. User presses Ctrl+S → client sends a `SaveChangeset` with only changed fields
4. Server validates, persists, and returns a fresh ScreenContract (the card with updated data)
5. Client replaces the current screen with the response

## SaveChangeset Format

The client sends:

```json
{
    "changes": {
        "name": "New Name",
        "city": "Reykjavik"
    },
    "lines": [["Item", "1000", "Widget", "2", "10.00", "5", "19.00"]]
}
```

- `changes`: only the header fields that were modified (sparse)
- `lines`: full grid data as 2D arrays (column order matches `lineColumns()`)

## Save Response

The server returns a full ScreenContract with `status: "Saved."`:

```json
{
    "layout": "Card",
    "title": "Customer Card - 10000",
    "status": "Saved.",
    "sections": [...],
    "actions": { "save": "...", "delete": "...", "create": "..." },
    ...
}
```

## Create Flow

Same as save, but POST to the create URL. Response includes `status: "Created."`.

## Delete Flow

1. User presses Ctrl+D
2. Client shows confirmation dialog
3. On confirm, client POSTs to the delete URL
4. Server deletes and returns the List ScreenContract with `status: "Deleted."`

## Server-Side Validation

The `SaveController` collects validation rules from fields and validates before saving. Rules live on the fields themselves:

```php
Text::make('name')->required()->minLength(3)->maxLength(100),
Email::make('email')->nullable()->email()->unique('customers', 'email'),
```

See [validation.md](validation.md) for the full three-layer validation system.

## Error Handling

On validation failure, the card is returned with the error in `status`. The card stays open with dirty values intact.

## Save Hooks

Override `beforeSave()` and `afterSave()` on your resource to run custom logic during the save flow.

### beforeSave

Called before the model is filled and saved. Receives the changes array and the model (null on create). Return the modified changes:

```php
public static function beforeSave(array $changes, ?Model $model = null): array
{
    // Normalize or transform values before save
    if (isset($changes['email'])) {
        $changes['email'] = strtolower($changes['email']);
    }

    return $changes;
}
```

### afterSave

Called after the model and lines are saved. The model is refreshed after `afterSave()` runs, so the returned card shows the calculated values.

For simple cases, put the logic inline:

```php
public static function afterSave(Model $model): void
{
    $model->update(['slug' => Str::slug($model->name)]);
}
```

For business logic that should run from **any entry point** (TUI, API, queue jobs, webhooks), use an Action class and call it from `afterSave()`. See [Organising business logic with Actions](#organising-business-logic-with-actions) below.

### Lifecycle Hooks

Three hooks fire after create, update, and delete — regardless of whether `$saveAction` is used:

```php
public static function afterCreate(Model $model): void
{
    // Fires after a new record is created
    Mail::to($model->email)->send(new WelcomeCustomer($model));
}

public static function afterUpdate(Model $model): void
{
    // Fires after an existing record is updated
    Cache::forget("customer.{$model->id}");
}

public static function afterDelete(Model $model): void
{
    // Fires after a record is deleted
    AuditLog::record('customer_deleted', $model->id);
}
```

**When do these fire vs `afterSave()`?**

| Hook | Fires on create | Fires on update | Fires with `$saveAction` |
|------|:-:|:-:|:-:|
| `afterSave()` | Yes | Yes | No |
| `afterCreate()` | Yes | No | Yes |
| `afterUpdate()` | No | Yes | Yes |
| `afterDelete()` | No | No | Yes |

Use `afterSave()` for data sync (recalculating totals, updating related records). Use `afterCreate()` / `afterUpdate()` / `afterDelete()` for side-effects (notifications, events, audit logging).

### Save flow

1. Strip disabled fields from changes (including `disableOnUpdate` / `disableOnCreate`)
2. Normalize changes by input mask (uppercase, lowercase)
3. Apply `fillUsing()` callbacks on fields
4. `beforeSave($changes, $model)` — your hook
5. Validate against field rules (including `creationRules` / `updateRules`)
6. Fill and save model
7. Save lines (HeaderLines/Grid)
8. `afterSave($model)` — your hook (only when `$saveAction` is not set)
9. `afterCreate($model)` or `afterUpdate($model)` — always fires
10. Refresh model
11. Return fresh ScreenContract with status

## Grid Saves

For HeaderLines and Grid layouts:
- `changes` contains header field changes (empty for Grid-only screens)
- `lines` is a 2D array where column order matches `lineColumns()`
- Empty trailing rows are included by the client; the server ignores them
- The server deletes existing lines and recreates from the `lines` array

## Save and Delete Actions (Codeunit Pattern)

Scaffold an action class with artisan:

```bash
php artisan 2wee:action PostSalesInvoice
```

For simple resources (customers, items), the default save works fine — no extra code needed. For complex documents (sales orders, purchase orders), you have two options:

| Pattern | When to use |
|---------|-------------|
| `$saveAction` | Replaces the entire save — you own line sync, validation, and everything. Use when the default save logic doesn't fit (e.g. you need full control over line processing). |
| `afterSave()` | Runs after the default save. Use when the default save works but you need post-save calculations (recalculate totals, trigger side effects). |

This follows the Navision Codeunit pattern. The Resource handles presentation (form layout, columns). The Action handles business logic (the complete save operation). They're separate concerns.

### Declaring an action on a resource

```php
class SalesQuoteResource extends Resource
{
    protected static string $model = SalesHeader::class;
    protected static ?string $saveAction = SaveSalesDocument::class;
    protected static ?string $deleteAction = DeleteSalesDocument::class;
    // ... form(), lineColumns() — presentation only
}
```

When `$saveAction` is declared, the plugin delegates to it instead of doing the default save. When it's not declared, everything works exactly as before.

### The SaveAction interface

```php
use TwoWee\Laravel\Contracts\SaveAction;

final readonly class SaveSalesDocument implements SaveAction
{
    public function handle(Model $model, array $changes, ?array $lines): void
    {
        DB::transaction(function () use ($model, $lines) {
            // Sync lines from client data
            // ... your line sync logic

            // Clean trailing empty lines
            // ... cleanup

            // Recalculate everything
            // ... line amounts, costs, totals
        });
    }
}
```

The action receives the model (already filled and saved), the header changes, and the raw line data. It owns everything from there.

### The DeleteAction interface

```php
use TwoWee\Laravel\Contracts\DeleteAction;

final readonly class DeleteSalesDocument implements DeleteAction
{
    public function handle(Model $model): void
    {
        DB::transaction(function () use ($model) {
            // Check if delete is allowed
            // Archive if needed
            // Delete lines
            // Delete header
        });
    }
}
```

### Simple vs complex resources

```php
// Simple — no action needed, default save works
class CustomerResource extends Resource
{
    protected static string $model = Customer::class;
    // form(), table() — that's it
}

// Complex — delegate to action
class SalesQuoteResource extends Resource
{
    protected static string $model = SalesHeader::class;
    protected static ?string $saveAction = SaveSalesDocument::class;
    // form(), lineColumns() — presentation only
}
```

### Reusing actions across resources

Multiple resources can share the same action:

```php
// All sales documents use the same save logic
class SalesQuoteResource extends Resource {
    protected static ?string $saveAction = SaveSalesDocument::class;
}

class SalesOrderResource extends Resource {
    protected static ?string $saveAction = SaveSalesDocument::class;
}

class SalesInvoiceResource extends Resource {
    protected static ?string $saveAction = SaveSalesDocument::class;
}
```

### Calling the action from outside the TUI

The action is a standalone class — call it from anywhere:

```php
// API controller
resolve(SaveSalesDocument::class)->handle($order, $changes, $lines);

// Queue job
resolve(SaveSalesDocument::class)->handle($this->order, [], null);
```

This follows the Navision Codeunit pattern. In Navision, the Page (our Resource) handles presentation. The Codeunit handles business logic — the complete operation, not one tiny calculation. `Sales-Post` doesn't just recalculate line amounts — it handles everything that happens when you save a sales document.

### The Action pattern (post-save hook)

Use `afterSave()` when the default save works fine but you need post-save calculations — the plugin handles line sync, and your action runs after. This is simpler than `$saveAction` and sufficient for most cases.

An Action class represents a **complete business operation**. Not "recalculate line costs" — that's too granular. Instead: "recalculate all totals after a sales document is saved."

```php
// app/Actions/RecalculateSalesDocument.php

final readonly class RecalculateSalesDocument
{
    public function handle(SalesHeader $document): void
    {
        DB::transaction(function () use ($document) {
            // Clean trailing empty lines
            $lastLine = $document->lines()->orderByDesc('line_no')->first();
            if ($lastLine && empty($lastLine->item_id) && empty($lastLine->description)) {
                $lastLine->delete();
            }

            // Recalculate line amounts and costs
            foreach ($document->lines()->get() as $line) {
                $item = Item::where('no', $line->item_id)->first();
                $unitCost = $item ? (float) $item->unit_cost : 0;

                $line->update([
                    'line_amount' => round(
                        (float) $line->quantity * (float) $line->unit_price
                        * (1 - (float) $line->discount_pct / 100), 2
                    ),
                    'unit_cost' => $unitCost,
                    'cost_amount' => round((float) $line->quantity * $unitCost, 2),
                ]);
            }

            // Update header totals
            $document->update([
                'total_amount' => $document->lines()->sum('line_amount'),
                'total_cost' => $document->lines()->sum('cost_amount'),
                'total_profit' => $document->lines()->sum('line_amount')
                    - $document->lines()->sum('cost_amount'),
            ]);
        });
    }
}
```

One class. One operation. Everything that a sales document needs on save.

### Call it from the resource

```php
// SalesQuoteResource, SalesOrderResource, SalesInvoiceResource — all the same:
public static function afterSave(Model $model): void
{
    resolve(RecalculateSalesDocument::class)->handle($model);
}
```

### Call it from anywhere else

```php
// API controller
resolve(SaveSalesDocument::class)->handle($order);

// Queue job
resolve(SaveSalesDocument::class)->handle($this->order);

// Artisan command
resolve(SaveSalesDocument::class)->handle($order);
```

The same business logic runs regardless of entry point. The TUI, the API, and a background job all produce the same result.

### Why this pattern

- **Complete** — one action handles the full operation, not fragments
- **Reusable** — quotes, orders, invoices, credit memos all call the same action
- **Transactional** — `DB::transaction()` ensures all-or-nothing
- **Testable** — `(new SaveSalesDocument())->handle($order)` in a test
- **Explicit** — you read the class and see everything that happens on save

### When to use what

| Logic type | Where to put it |
|------------|----------------|
| Simple field transform (e.g. slugify) | `->fillUsing()` on the field |
| Post-save calculations (totals, computed fields) | Inline in `afterSave()` |
| Complete business operation (e.g. save a document) | Action class via `$saveAction` |
| Side-effects (notifications, events, audit log) | `afterCreate()` / `afterUpdate()` / `afterDelete()` |
| TUI-specific post-save | Inline in `afterSave()` before the action call |
