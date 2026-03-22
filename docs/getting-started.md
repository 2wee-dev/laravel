# Getting Started

2Wee for Laravel exposes your application's data through a JSON API that TUI clients connect to. You define resources — each one maps a model to a set of screens — and the plugin handles routing, authentication, and the protocol.

## Install the package

```bash
composer require 2wee/laravel
```

Run the install command:

```bash
php artisan 2wee:install
```

This publishes `config/twowee.php` and creates `app/TwoWee/Resources/`.

Run the migration:

```bash
php artisan migrate
```

This creates the `twowee_tokens` table used for bearer token authentication.

## Configure the package

Edit `config/twowee.php`:

```php
return [
    'prefix'   => env('TWOWEE_PREFIX', 'terminal'), // URL prefix for all routes
    'app_name' => 'My App',                          // Shown in the TUI menu bar

    'auth' => [
        'username_field' => 'email', // 'email' or 'username'
    ],

    'locale' => [
        'date_format'        => 'DD-MM-YYYY',
        'decimal_separator'  => ',',
        'thousand_separator' => '.',
    ],

    'work_date'  => null, // null = today
    'ui_strings' => [],   // Override client UI text
];
```

## Create your first resource

```bash
php artisan 2wee:resource CustomerResource --model=Customer
```

This scaffolds `app/TwoWee/Resources/CustomerResource.php`. Edit it to define your form fields and table columns:

```php
namespace App\TwoWee\Resources;

use TwoWee\Laravel\Columns\TextColumn;
use TwoWee\Laravel\Fields\Email;
use TwoWee\Laravel\Fields\Phone;
use TwoWee\Laravel\Fields\Text;
use TwoWee\Laravel\Resource;
use TwoWee\Laravel\Section;

class CustomerResource extends Resource
{
    protected static string $model = \App\Models\Customer::class;
    protected static string $label = 'Customer';

    public static function form(): array
    {
        return [
            Section::make('General')
                ->column(0)->rowGroup(0)
                ->fields([
                    Text::make('no')->label('No.')->width(20)->required()->uppercase(),
                    Text::make('name')->label('Name')->width(30)->required(),
                ]),

            Section::make('Contact')
                ->column(1)->rowGroup(0)
                ->fields([
                    Email::make('email')->label('E-Mail')->width(30),
                    Phone::make('phone')->label('Phone No.')->width(20),
                ]),
        ];
    }

    public static function table(): array
    {
        return [
            TextColumn::make('no')->label('No.')->width(10),
            TextColumn::make('name')->label('Name')->width('fill'),
            TextColumn::make('city')->label('City')->width(20),
        ];
    }
}
```

Other generators:

```bash
php artisan 2wee:lookup CountryLookup --model=Country  # Reusable lookup
php artisan 2wee:action PostSalesInvoice               # Screen action class
```

## Connect a TUI client

Point any TUI client at your server URL:

```
http://your-app.test/terminal/menu/main
```

The plugin redirects to the login screen if authentication is required. See [Authentication](authentication.md) for setup.

## Routes

The plugin registers these routes automatically under the configured prefix (`/terminal` by default):

| Method | URL | Purpose |
|--------|-----|---------|
| GET | `/terminal/auth/login` | Login form |
| POST | `/terminal/auth/login` | Submit login |
| GET | `/terminal/menu/main` | Main menu |
| GET | `/terminal/screen/{resource}/list` | List screen |
| GET | `/terminal/screen/{resource}/card/new` | New record form |
| GET | `/terminal/screen/{resource}/card/{id}` | Existing record |
| POST | `/terminal/screen/{resource}/card/new` | Create record |
| POST | `/terminal/screen/{resource}/card/{id}/save` | Save changes |
| POST | `/terminal/screen/{resource}/card/{id}/delete` | Delete record |
| GET | `/terminal/lookup/{field_id}` | Lookup list |
| GET | `/terminal/validate/{field_id}/{value}` | Blur validation |
| GET | `/terminal/drilldown/{field_id}/{key}` | Drill-down data |
| POST | `/terminal/action/{resource}/{id}/{action_id}` | Screen action |

## Next steps

- [Resources](resources.md) — define screens and data shapes
- [Fields](fields.md) — all available field types
- [Web Terminal](web-terminal.md) — embed a browser-based terminal in your app
