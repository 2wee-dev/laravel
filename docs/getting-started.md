# Getting Started

2Wee for Laravel exposes your application's data through a JSON API that TUI clients connect to. You define resources — each one maps a model to a set of screens — and the plugin handles routing, authentication, and the protocol.

## Install the package

```bash
composer require 2wee/laravel
php artisan 2wee:install
```

This publishes `config/twowee.php`, creates `app/TwoWee/Resources/`, and runs the migration that creates the `twowee_tokens` table used for authentication.

## Configure the package

Open `config/twowee.php` and set your app name and locale:

```php
'app_name' => 'My App',      // Shown in the TUI menu bar

'locale' => [
    'date_format'        => 'DD-MM-YYYY',
    'decimal_separator'  => ',',
    'thousand_separator' => '.',
],
```

Everything else in the config file has sensible defaults and can be left as-is to start.

## Create your first resource

```bash
php artisan 2wee:resource CustomerResource --model=Customer
```

This scaffolds `app/TwoWee/Resources/CustomerResource.php`. Edit it to define your form fields and table columns:

```php
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

## Connect a client

Your application is now ready to accept TUI connections. Point a client at:

```
https://your-app.com/terminal
```

- **Browser** — add `<x-2wee::terminal />` to a Blade view. See [Web Terminal](web-terminal.md).
- **Desktop** — download the CLI client and run `two_wee_client https://your-app.com`. See [Client Installation](/client/installation).

## Next steps

- [Resources](resources.md) — define screens and data shapes
- [Fields](fields.md) — all available field types
- [Web Terminal](web-terminal.md) — embed a browser-based terminal in your app
