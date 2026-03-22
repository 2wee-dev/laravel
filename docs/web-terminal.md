# Web Terminal

The web terminal lets users run the TUI application directly in a browser — no local installation required. It is an optional addition to the core plugin and is independent of how the TUI client is distributed.

Under the hood, a small bridge process called `two_wee_terminal` runs on your server. It spawns a `two_wee_client` process per WebSocket connection, streams the PTY over the socket, and serves the terminal JavaScript. Your Laravel app hosts the page; `two_wee_terminal` handles the connection.

## Install the terminal binaries

Download the binaries for your platform:

```bash
php artisan 2wee:install-terminal
```

This downloads `two_wee_terminal` and `two_wee_client` into `storage/app/2wee/` and makes them executable. Run this once after installing the package, and again after upgrading.

## Start and stop the service

```bash
php artisan 2wee:start-terminal
php artisan 2wee:stop-terminal
php artisan 2wee:check-terminal
```

`2wee:start-terminal` starts `two_wee_terminal` as a background process on port 7681. The PID is stored in `storage/app/2wee/two_wee_terminal.pid` and logs are written to `storage/logs/two_wee_terminal.log`.

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

The only `.env` key you typically need is:

```dotenv
TWOWEE_QUIT_URL=https://your-app.com
```

This controls where the browser redirects when the user quits the TUI. If left empty, a "session ended" message is shown instead.

Full list of available keys:

```php
'terminal' => [
    // Port the two_wee_terminal service listens on.
    'port' => env('TWOWEE_TERMINAL_PORT', 7681),

    // Lock the terminal to a specific server URL.
    // Defaults to {APP_URL}/{prefix} — only set this if you need to override.
    'server_url' => env('TWOWEE_TERMINAL_SERVER'),

    // Redirect here when the user quits the TUI application.
    // If empty, a "session ended" message is shown.
    'quit_url' => env('TWOWEE_QUIT_URL', ''),
],
```

## Deploy on Laravel Forge

This is a complete, working setup on Laravel Forge.

### 1. Environment variables

In Forge → Sites → your site → **Environment**, set:

```dotenv
APP_URL=https://your-app.com
APP_ENV=staging
TWOWEE_QUIT_URL=https://your-app.com
```

`APP_ENV=staging` is required because Laravel blocks `migrate:fresh` in `production` environments. If you are not using `migrate:fresh` in your deploy script, you can set `APP_ENV=production`.

### 2. Deploy script

In Forge → Sites → your site → **Deploy Script**:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan config:clear
php artisan migrate:fresh --force --seed
php artisan 2wee:install-terminal --no-interaction
php artisan 2wee:stop-terminal
```

`2wee:stop-terminal` signals the running process to stop. The supervisor daemon (set up in the next step) restarts it automatically after each deploy.

### 3. Background process

In Forge → Sites → your site → **Processes** → **Add background process**:

| Field | Value |
|-------|-------|
| Command | `php artisan 2wee:start-terminal` |
| Directory | `/home/forge/your-app.com/current` |

Forge manages this with Supervisor — it starts automatically on boot and restarts if the process exits.

### 4. Nginx configuration

In Forge → Sites → your site → **Nginx Configuration**, add these two blocks inside the `server {}` block, before the `location /` block:

```nginx
location /ws {
    proxy_pass         http://127.0.0.1:7681;
    proxy_http_version 1.1;
    proxy_set_header   Upgrade $http_upgrade;
    proxy_set_header   Connection "upgrade";
    proxy_set_header   Host $host;
    proxy_read_timeout 3600s;
}

location /terminal.js {
    proxy_pass http://127.0.0.1:7681;
}
```

This proxies WebSocket connections and the terminal JavaScript through your HTTPS site to the `two_wee_terminal` service running on port 7681.

### 5. First deploy

Push your code and trigger a deploy in Forge. After the deploy completes, the supervisor daemon starts `two_wee_terminal` automatically. Visit your site — the terminal should connect immediately.

## Configure Nginx manually

If you are not using Forge, add the same two blocks to your site's Nginx config:

```nginx
location /ws {
    proxy_pass         http://127.0.0.1:7681;
    proxy_http_version 1.1;
    proxy_set_header   Upgrade $http_upgrade;
    proxy_set_header   Connection "upgrade";
    proxy_set_header   Host $host;
    proxy_read_timeout 3600s;
}

location /terminal.js {
    proxy_pass http://127.0.0.1:7681;
}
```

Then reload Nginx:

```bash
sudo nginx -s reload
```

## Run as a systemd service

If you manage your own server without Forge:

```ini
[Unit]
Description=2Wee Web Terminal
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/your-app
ExecStart=/usr/bin/php artisan 2wee:start-terminal
Restart=always

[Install]
WantedBy=multi-user.target
```

Enable and start it:

```bash
sudo systemctl enable two-wee-terminal
sudo systemctl start two-wee-terminal
```

## Session security

Each WebSocket connection gets an isolated session:

- A unique UUID is assigned per connection.
- `two_wee_client` runs with `HOME=/tmp/2wee-sessions/{uuid}` — bearer tokens stored during one session are never visible to another.
- The session directory is deleted when the connection closes.
- Sessions idle for more than 30 minutes are terminated automatically.
