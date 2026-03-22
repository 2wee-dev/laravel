# Resources

A Resource class maps an Eloquent model to terminal UI screens. Each resource defines two required methods: `form()` for the card screen and `table()` for the list screen.

## Basic Structure

```php
use TwoWee\Laravel\Resource;

class CustomerResource extends Resource
{
    protected static string $model = \App\Models\Customer::class;
    protected static string $label = 'Customer';     // Optional
    protected static ?string $slug = 'customers';    // Optional, auto-generated

    public static function form(): array { /* ... */ }
    public static function table(): array { /* ... */ }
}
```

## Title

Override `title()` to customize what appears in the title bar when viewing a record:

```php
// Default: "Customer Card - 1"
// Override:
public static function title(Model $model): string
{
    return 'Customer Card - ' . $model->no . ' ' . $model->name;
    // → "Customer Card - C-73065 Schiller, Romaguera and Rempel"
}
```

## Record Key

By default, records are identified in URLs by the model's primary key. Override `$recordKey` when your first table column is a natural key like a customer number:

```php
protected static ?string $recordKey = 'no';
```

This controls:
- URL structure: `/screen/customers/card/C-73065` instead of `/card/1`
- Record lookup in all controllers
- Save, delete, and action endpoint URLs

## Screen ID

Every ScreenContract response includes a `screen_id` — a stable machine identifier the client uses to key screen-specific behavior. By default it is derived from the resource class name (e.g. `CustomerResource` → `customer`). Override it when you need a stable ID that survives a class rename:

```php
protected static ?string $screenId = 'customer_card';
```

You rarely need to set this explicitly.

## Navigation

Control how resources appear in the menu:

```php
class CustomerResource extends Resource
{
    protected static string $model = Customer::class;

    // Menu visibility
    protected static bool $showInNavigation = true;      // false to hide from menu

    // Ordering — lower number = higher in list, then alphabetical
    protected static int $navigationSort = 1;

    // Grouping — resources with the same group appear in the same menu tab
    protected static ?string $navigationGroup = 'Sales';

    // Override the menu item label (defaults to $label)
    protected static ?string $navigationLabel = 'Customers';

    // Popup group — resources with the same popup name appear in a floating sub-list
    protected static ?string $navigationPopup = null;

    // Separator — adds a blank divider line above this item
    protected static bool $navigationSeparatorBefore = false;

    // Parent menu for Escape fallback (defaults to /menu/main)
    protected static ?string $navigationParent = null;
}
```

### Menu tabs

Resources are grouped into menu tabs by `$navigationGroup`. Resources without a group go into the default tab (configurable in `config/twowee.php`):

```php
// config/twowee.php
'menu' => [
    'default_tab' => 'Home',
    'tab_order' => ['Home', 'Sales', 'Purchasing', 'Finance'],
],
```

`tab_order` controls the tab sequence. Tabs not listed appear at the end alphabetically.

### Example

```php
class CustomerResource extends Resource {
    protected static ?string $navigationGroup = 'Sales';
    protected static int $navigationSort = 1;
}

class VendorResource extends Resource {
    protected static ?string $navigationGroup = 'Purchasing';
    protected static int $navigationSort = 1;
}

class ItemResource extends Resource {
    protected static ?string $navigationGroup = 'Sales';
    protected static int $navigationSort = 2;
}

class ChartOfAccountsResource extends Resource {
    protected static ?string $navigationGroup = 'Finance';
}

class CustomerLedgerEntryResource extends Resource {
    protected static bool $showInNavigation = false;  // Only via drilldown
}

// --- Popup group: multiple resources grouped into a floating sub-list ---

class PostedInvoiceResource extends Resource {
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $navigationPopup = 'Posted';  // appears inside popup
    protected static int $navigationSort = 10;
}

class PostedCreditMemoResource extends Resource {
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $navigationPopup = 'Posted';  // same popup
    protected static int $navigationSort = 11;
}

// --- Separator: visual divider before this item ---

class SalesOrderResource extends Resource {
    protected static ?string $navigationGroup = 'Sales';
    protected static int $navigationSort = 3;
    protected static bool $navigationSeparatorBefore = true;  // line above
}
```

This produces a menu with:
- **Sales**: Customers, Items, ─── (separator), Sales Orders, Posted ▸ (popup: Posted Invoices, Posted Credit Memos)
- **Purchasing**: Vendors
- **Finance**: Chart of Accounts

### Popup groups

Resources with the same `$navigationPopup` value (within the same `$navigationGroup`) are collected into a floating sub-list. The popup label is the shared name. Use this for small clusters of related destinations (2–6 items):

```php
class PostedInvoiceResource extends Resource {
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $navigationPopup = 'Posted';
    protected static int $navigationSort = 10;
}

class PostedCreditMemoResource extends Resource {
    protected static ?string $navigationGroup = 'Sales';
    protected static ?string $navigationPopup = 'Posted';
    protected static int $navigationSort = 11;
}
```

The user sees "Posted" in the Sales tab. Pressing Enter opens a floating list: Posted Invoices, Posted Credit Memos. Up/Down to select, Enter to open, Escape to cancel.

### Menu separators

Add a visual divider above an item to break a long tab into logical groups:

```php
class SalesOrderResource extends Resource {
    protected static ?string $navigationGroup = 'Sales';
    protected static int $navigationSort = 3;
    protected static bool $navigationSeparatorBefore = true;
}
```

The separator is a blank line — not selectable, navigation skips it.

### Navigation parent (Escape fallback)

Every screen has a `parent_url` — the screen the client navigates to when the user presses Escape and there's no navigation history (e.g. after an action redirect).

The plugin sets this automatically:
- **Card / HeaderLines** → the resource's list screen
- **List / Grid** → `/menu/main`

For resources that live under a sub-menu, set `$navigationParent`:

```php
// These resources live under the "Sales" sub-menu
class PostedInvoiceResource extends Resource {
    protected static ?string $navigationParent = 'menu/sales';
}

class PostedCreditMemoResource extends Resource {
    protected static ?string $navigationParent = 'menu/sales';
}
```

The full Escape chain builds automatically:

```
Posted Invoice Card → Escape → Posted Invoices List → Escape → Sales Sub-Menu → Escape → Main Menu
     (parent_url = list)        (parent_url = /menu/sales)      (parent_url = /menu/main)
```

Most resources don't need `$navigationParent` — the default `/menu/main` is correct when all resources live directly under the main menu.

### Custom menu items

Add placeholders, sub-menus, or other non-resource items via config:

```php
// config/twowee.php
'menu' => [
    'items' => [
        // Placeholder — shows a message when selected
        [
            'label' => 'Reports',
            'group' => 'Finance',
            'sort' => 99,
            'action' => ['type' => 'message', 'text' => 'Coming soon.'],
        ],

        // Separator — visual divider between items
        [
            'label' => '',
            'group' => 'Sales',
            'sort' => 10,
            'action' => ['type' => 'separator'],
        ],

        // Popup menu — inline floating list of destinations
        [
            'label' => 'Posted',
            'group' => 'Sales',
            'sort' => 11,
            'action' => [
                'type' => 'popup',
                'items' => [
                    ['label' => 'Posted Invoices', 'action' => ['type' => 'open_screen', 'url' => '/terminal/screen/posted_invoices/list']],
                    ['label' => 'Posted Credit Memos', 'action' => ['type' => 'open_screen', 'url' => '/terminal/screen/posted_credits/list']],
                ],
            ],
        ],

        // Sub-menu — opens a nested menu screen
        [
            'label' => 'Setup',
            'group' => 'Admin',
            'sort' => 1,
            'action' => ['type' => 'open_menu', 'url' => '/terminal/menu/setup'],
        ],
    ],
],
```

Action types:

| Type | Behavior |
|------|----------|
| `open_screen` | Navigate to a screen URL |
| `open_menu` | Open a sub-menu (nested navigation) |
| `popup` | Inline floating list of destinations (2–6 items) |
| `message` | Show text in the status bar (placeholder) |
| `separator` | Visual divider (blank line, not selectable) |
| `open_url` | Open URL in system browser |

## Authorization

The plugin auto-discovers Laravel Policies for your resource models. If no policy exists, everything is allowed (opt-in security).

### How it works

If a Policy is registered for the model, the plugin checks these abilities:

| Action | Policy Method |
|--------|--------------|
| View list | `viewAny` |
| View card | `view` |
| Create | `create` |
| Update | `update` |
| Delete | `delete` |

### Example

```php
// app/Policies/CustomerPolicy.php
class CustomerPolicy
{
    public function delete(User $user, Customer $customer): bool
    {
        return $user->is_admin;
    }

    // No viewAny/view/create/update methods → those are allowed
}
```

Register the policy as usual in `AuthServiceProvider` or use Laravel's auto-discovery. The TwoWee guard user (`Auth::guard('twowee')->user()`) is passed to the policy.

When denied, the client receives a `403` JSON response: `{"error": "Unauthorized"}`.

### No policy = no restrictions

If no Policy is registered for the model class, all operations are allowed. This is intentional — authorization is opt-in for backend tools. Add a Policy when you need it.

## Auto-Discovery

Resources in `app/TwoWee/Resources/` are auto-discovered. You can also register them manually in `config/twowee.php`:

```php
'resources' => [
    \App\TwoWee\Resources\CustomerResource::class,
],
```

## Layout Types

Override `layout()` to change the screen type:

```php
public static function layout(): string
{
    return 'Card';        // Default: single-record form
    // return 'HeaderLines'; // Document: header + editable grid
    // return 'Grid';        // Full-screen editable grid
}
```

## Search

List screens and lookups support `?query=` for server-side filtering. The plugin provides two search strategies and picks the best one automatically.

### Default: LIKE queries

By default, the plugin searches all Text columns using SQL `LIKE '%term%'`. Override `searchColumns()` to customize which columns are searched:

```php
public static function searchColumns(): ?array
{
    return ['no', 'name', 'city'];
}
```

This works well for small to medium datasets. No extra setup needed.

### Laravel Scout (auto-detected)

If your model uses Laravel Scout's `Searchable` trait, the plugin automatically uses Scout for search instead of LIKE queries. **No plugin configuration needed** — it's detected at runtime.

#### How it works

1. The plugin checks if the model uses the `Searchable` trait
2. If yes: `Customer::search('acme')->get()` via Scout
3. If no: LIKE queries as before
4. If Scout isn't even installed: LIKE queries (no error)

#### Setup (on the app side, not the plugin)

```bash
# 1. Install Scout
composer require laravel/scout

# 2. Choose a driver (database, Meilisearch, Algolia, Typesense)
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

```php
// 3. Add Searchable to models you want full-text search on
use Laravel\Scout\Searchable;

class Customer extends Model
{
    use Searchable;

    // Define which fields are searchable
    public function toSearchableArray(): array
    {
        return [
            'no' => $this->no,
            'name' => $this->name,
            'city' => $this->city,
            'email' => $this->email,
        ];
    }
}
```

That's it. The plugin detects the trait and uses Scout automatically — on both list screens and lookup searches.

#### Why use Scout?

| | LIKE queries | Scout |
|---|---|---|
| Setup | None | Install Scout + configure driver |
| Speed on small data | Fast | Similar |
| Speed on large data | Slow (full table scan) | Fast (indexed) |
| Fuzzy matching | No (`LIKE` is exact substring) | Yes (typo-tolerant with Meilisearch/Algolia) |
| Relevance ranking | No | Yes |
| Works without Scout | Yes | — |

#### Per-model control

Scout is per-model. You can have some models using Scout and others using LIKE — the plugin checks each model independently:

```php
// This model uses Scout (fast, fuzzy, ranked)
class Customer extends Model
{
    use Searchable;
}

// This model uses LIKE (no Scout needed for small tables)
class PaymentTerm extends Model
{
    // No Searchable trait
}
```

#### Scout with lookups

Scout works on lookup searches too. When a user types in a full-screen lookup, the plugin uses Scout if the lookup model has the `Searchable` trait. Context filters (`->filterFrom()`) still apply — they're added via Scout's `query()` callback.

Modal lookups are not affected — they load all rows at once and the client filters locally.

## Row Limiting

For large tables, set a maximum row count:

```php
public static function maxRows(): ?int
{
    return 5000;
}
```

## Lookups

Most lookups are defined inline on fields with `->lookup(Model::class)`. For advanced cases (custom queries, polymorphic lookups), override the `lookups()` method. See [lookups.md](lookups.md) for the full reference.

## Screen Actions

Define custom operations (send email, post, export) via `screenActions()` and `handleAction()`. See [screen-actions.md](screen-actions.md) for the full reference.

## HeaderLines Layout

For document-style screens with a header and editable line items (sales orders, purchase orders). See [header-lines.md](header-lines.md) for the full reference.

## Grid Layout

For full-screen editable grids (journals, batch entry). Extend `GridResource` instead of `Resource`. See [grids.md](grids.md) for the full reference.
