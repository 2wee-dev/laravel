# Web Terminal

The web terminal lets your users run the TUI application directly in a browser — no local installation required. You add a Blade component to a view, start a small background service, and it works.

## Add the Blade component

Place the terminal component in a Blade view:

```blade
<x-2wee::terminal />
```

This renders a full-screen terminal connected to your application. No attributes are required for a standard setup.

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

## Install and start the service

Download the binaries for your platform:

```bash
php artisan 2wee:install-terminal
```

This downloads `two_wee_terminal` and `two_wee_client` into `storage/app/2wee/` and makes them executable. Run this once after installing the package, and again after upgrading.

Start the service:

```bash
php artisan 2wee:start-terminal
```

Other commands:

```bash
php artisan 2wee:stop-terminal
php artisan 2wee:check-terminal
```

The service listens on port 7681. Logs are written to `storage/logs/two_wee_terminal.log`.

## Configure

The only `.env` key you typically need is:

```dotenv
TWOWEE_QUIT_URL=https://your-app.com
```

This controls where the browser redirects when the user quits the TUI. If left empty, a "session ended" message is shown instead.

Full list of available keys in `config/twowee.php`:

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

### 1. Add to your deploy script

In Forge → Sites → your site → **Deployments**, add these two lines at the end of your existing deploy script:

```bash
php artisan 2wee:install-terminal --no-interaction
php artisan 2wee:stop-terminal
```

`2wee:install-terminal` downloads the binaries on first deploy and skips the download on subsequent deploys. `2wee:stop-terminal` signals the running process to stop so the background process (set up next) can restart it with the new release.

### 2. Add a background process

In Forge → Sites → your site → **Processes** → **Add background process**:

| Field | Value |
|-------|-------|
| Command | `php artisan 2wee:start-terminal` |
| Directory | `/home/forge/your-app.com/current` |

Forge manages this with Supervisor — it starts automatically on boot and restarts after each deploy.

### 3. Configure Nginx

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

### 4. Deploy

Trigger a deploy. The background process starts `two_wee_terminal` automatically. Visit your page with `<x-2wee::terminal />` — the terminal connects immediately.

### Optional: override the server URL

By default the terminal connects to `{APP_URL}/{prefix}`, where `prefix` is configured in `config/twowee.php` (default: `terminal`). If your 2Wee backend runs on a different server, set:

```dotenv
TWOWEE_TERMINAL_SERVER=https://api.your-app.com/terminal
```

## Latency

The web terminal renders the TUI on the server and streams it to the browser. Every keystroke makes a round trip to your server before the screen updates. On a server close to your users this is imperceptible. On a server far away — for example, a user in Europe connecting to a server in the US — the latency will be noticeable.

Deploy `two_wee_terminal` on a server in the same region as your users for the best experience. The [CLI client](/client/installation) has no such constraint — it runs locally and only the API calls travel over the network.

## Session security

Each WebSocket connection gets an isolated session:

- A unique UUID is assigned per connection.
- `two_wee_client` runs with `HOME=/tmp/2wee-sessions/{uuid}` — bearer tokens stored during one session are never visible to another.
- The session directory is deleted when the connection closes.
- Sessions idle for more than 30 minutes are terminated automatically.
