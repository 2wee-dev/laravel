<?php

namespace TwoWee\Laravel\View\Components;

use Illuminate\View\Component;

class Terminal extends Component
{
    public string $wsUrl;
    public string $server;
    public string $scriptUrl;
    public string $width;
    public string $height;
    public string $onexit;

    public function __construct(
        string $server = '',
        string $width = '100%',
        string $height = '100vh',
        string $onexit = '',
    ) {
        $port = config('twowee.terminal.port', 7681);
        $prefix = config('twowee.prefix', 'terminal');
        $appUrl = rtrim(config('app.url'), '/');

        // The 2Wee server URL that two_wee_client connects to
        $configuredServer = config('twowee.terminal.server_url', '');
        $this->server = $server ?: $configuredServer ?: "{$appUrl}/{$prefix}";

        // WebSocket URL of the two_wee_terminal service
        // Local dev: direct to the service port
        // Production: proxied via Nginx at the same host
        $this->wsUrl = $this->resolveWsUrl($appUrl, $port);

        // terminal.js served by two_wee_terminal
        $this->scriptUrl = $this->resolveScriptUrl($appUrl, $port);

        $this->width  = $width;
        $this->height = $height;
        $this->onexit = $onexit ?: config('twowee.terminal.quit_url', '');
    }

    public function render()
    {
        return view('2wee::components.terminal');
    }

    private function resolveWsUrl(string $appUrl, int $port): string
    {
        if (app()->environment('local')) {
            $host = parse_url($appUrl, PHP_URL_HOST) ?? 'localhost';
            return "http://{$host}:{$port}";
        }

        // In production, Nginx proxies /ws → two_wee_terminal
        return $appUrl;
    }

    private function resolveScriptUrl(string $appUrl, int $port): string
    {
        if (app()->environment('local')) {
            $host = parse_url($appUrl, PHP_URL_HOST) ?? 'localhost';
            return "http://{$host}:{$port}/terminal.js";
        }

        return "{$appUrl}/terminal.js";
    }
}
