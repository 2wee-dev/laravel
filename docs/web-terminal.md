# Web Terminal

The web terminal lets users run the TUI application directly in a browser — no local installation required. It is an optional addition to the core plugin and is independent of how the TUI client is distributed.

Under the hood, a small bridge process called `two_wee_terminal` runs on your server. It spawns a `two_wee_client` process per WebSocket connection, streams the PTY over the socket, and serves the terminal JavaScript. Your Laravel app hosts the page; `two_wee_terminal` handles the connection.

## Install the terminal binaries

Download the binaries for your platform:

```bash
php artisan 2wee:install-terminal
```

This downloads `two_wee_terminal` and `two_wee_client` into the package's `bin/` directory and makes them executable. Run this once after installing the package, and again after upgrading.

## Start and stop the service

```bash
php artisan 2wee:start-terminal
php artisan 2wee:stop-terminal
php artisan 2wee:check-terminal
```

`2wee:start-terminal` starts `two_wee_terminal` as a background process on port 7681. The PID is stored in `storage/app/2wee/two_wee_terminal.pid` and logs are written to `storage/logs/two_wee_terminal.log`.

Options for `2wee:start-terminal`:

| Option | Default | Description |
|--------|---------|-------------|
| `--port` | `7681` | Port to listen on |
| `--force` | — | Restart if already running |

## Add the Blade component

Place the terminal component anywhere in a Blade view:

```blade
<x-2wee::terminal />
```

This renders a full-screen terminal connected to your application. The component reads its configuration from `config/twowee.php` — no attributes required for a standard setup.

### Available attributes

| Attribute | Description |
|-----------|-------------|
| `server` | Override the server URL (defaults to `{APP_URL}/{prefix}`) |
| `width` | CSS width (default: `100%`) |
| `height` | CSS height (default: `100vh`) |
| `onexit` | URL to redirect to when the user quits the TUI application |

Example — embed the terminal at a fixed height inside a layout:

```blade
<x-2wee::terminal height="600px" />
```

## Configure the terminal

Add these keys to `config/twowee.php` (or set them via `.env`):

```php
'terminal' => [
    // Port the two_wee_terminal service listens on.
    'port' => env('TWOWEE_TERMINAL_PORT', 7681),

    // Lock the terminal to a specific server URL.
    // If empty, the component uses {APP_URL}/{prefix} automatically.
    'server_url' => env('TWOWEE_TERMINAL_SERVER', ''),

    // Redirect here when the user quits the TUI application.
    // If empty, a "session ended" message is shown.
    'quit_url' => env('TWOWEE_QUIT_URL', ''),
],
```

Typical `.env` entries:

```dotenv
TWOWEE_TERMINAL_PORT=7681
TWOWEE_QUIT_URL=https://your-app.com
```

## Configure Nginx for production

In local development, the browser connects directly to `two_wee_terminal` on its port. In production, proxy the WebSocket and the terminal script through Nginx so everything runs on the standard HTTPS port.

Add this to your site's Nginx config:

```nginx
# WebSocket connections
location /ws {
    proxy_pass         http://127.0.0.1:7681;
    proxy_http_version 1.1;
    proxy_set_header   Upgrade $http_upgrade;
    proxy_set_header   Connection "upgrade";
    proxy_set_header   Host $host;
    proxy_read_timeout 3600s;
}

# Terminal JavaScript
location /terminal.js {
    proxy_pass http://127.0.0.1:7681;
}
```

## Run as a daemon

For production, run `two_wee_terminal` as a persistent service. With Laravel Forge or any supervisor:

```ini
[program:two_wee_terminal]
command=php artisan 2wee:start-terminal --force
directory=/var/www/your-app
autostart=true
autorestart=true
```

Or with a systemd unit:

```ini
[Unit]
Description=2Wee Web Terminal
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/your-app
ExecStart=/usr/bin/php artisan 2wee:start-terminal --force
Restart=always

[Install]
WantedBy=multi-user.target
```

## Session security

Each WebSocket connection gets an isolated session:

- A unique UUID is assigned per connection.
- `two_wee_client` runs with `HOME=/tmp/2wee-sessions/{uuid}` — bearer tokens stored during one session are never visible to another.
- The session directory is deleted when the connection closes.
- Sessions idle for more than 30 minutes are terminated automatically.
