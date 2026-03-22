# 2Wee for Laravel

A Laravel plugin that exposes your application's data to [2Wee](https://2wee.dev) TUI clients. Define resources — each one maps an Eloquent model to a set of screens — and the plugin handles routing, authentication, and the protocol.

## Installation

```bash
composer require 2wee/laravel
php artisan 2wee:install
php artisan migrate
```

## Create a resource

```bash
php artisan 2wee:resource CustomerResource --model=Customer
```

Edit the generated file in `app/TwoWee/Resources/CustomerResource.php` to define your fields and columns, then connect any 2Wee client to:

```
https://your-app.example.com/terminal
```

## Web terminal (optional)

Embed a browser-based terminal directly in your app — no client installation required:

```bash
php artisan 2wee:install-terminal
php artisan 2wee:start-terminal
```

Add to any Blade view:

```blade
<x-2wee::terminal />
```

Set `TWOWEE_QUIT_URL` in your `.env` to redirect users when they exit the terminal:

```dotenv
TWOWEE_QUIT_URL=https://your-app.example.com
```

## Artisan commands

| Command | Description |
|---------|-------------|
| `2wee:install` | Publish config and scaffold resources directory |
| `2wee:resource` | Generate a new resource class |
| `2wee:lookup` | Generate a reusable lookup class |
| `2wee:action` | Generate a screen action class |
| `2wee:install-terminal` | Download web terminal binaries |
| `2wee:start-terminal` | Start the web terminal service |
| `2wee:stop-terminal` | Stop the web terminal service |
| `2wee:check-terminal` | Show terminal service status |

## Documentation

Full documentation at [2wee.dev](https://2wee.dev/laravel/getting-started).
