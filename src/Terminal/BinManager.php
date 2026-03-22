<?php

namespace TwoWee\Laravel\Terminal;

use RuntimeException;

class BinManager
{
    public function binPath(): string
    {
        return storage_path('app/2wee/two_wee_terminal-' . $this->platform());
    }

    public function pidPath(): string
    {
        return storage_path('app/2wee/two_wee_terminal.pid');
    }

    public function clientBinPath(): string
    {
        return storage_path('app/2wee/two_wee_client-' . $this->platform());
    }

    public function isInstalled(): bool
    {
        return file_exists($this->binPath()) && is_executable($this->binPath());
    }

    public function install(): void
    {
        $this->downloadBinary('two_wee_terminal', $this->binPath());
        $this->downloadBinary('two_wee_client', $this->clientBinPath());
    }

    private function downloadBinary(string $name, string $dest): void
    {
        $dir = dirname($dest);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $url = $this->releaseUrl($name);
        $bytes = @file_get_contents($url);

        if ($bytes === false) {
            throw new RuntimeException("Failed to download {$name} from {$url}");
        }

        $tmp = $dest . '.tmp';
        file_put_contents($tmp, $bytes);
        chmod($tmp, 0755);
        rename($tmp, $dest);
    }

    public function isRunning(): bool
    {
        $pid = $this->readPid();

        if ($pid === null) {
            return false;
        }

        // posix_kill with signal 0 checks if process exists without killing it
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        // Fallback: check /proc on Linux
        return file_exists("/proc/{$pid}");
    }

    public function start(int $port): int
    {
        $bin = $this->binPath();
        $clientBin = $this->clientBinPath();
        $server = config('twowee.terminal.server_url', '');
        $logPath = storage_path('logs/two_wee_terminal.log');

        $env = "TWO_WEE_CLIENT_BIN=" . escapeshellarg($clientBin);

        if ($server !== '') {
            $env .= ' TWO_WEE_SERVER=' . escapeshellarg($server);
        }

        $cmd = "{$env} TWO_WEE_PORT={$port} {$bin} >> " . escapeshellarg($logPath) . ' 2>&1 & echo $!';

        $pid = (int) shell_exec($cmd);

        if ($pid === 0) {
            throw new RuntimeException('Failed to start two_wee_terminal.');
        }

        $this->writePid($pid);

        return $pid;
    }

    public function stop(): void
    {
        $pid = $this->readPid();

        if ($pid === null) {
            return;
        }

        if (function_exists('posix_kill')) {
            posix_kill($pid, SIGTERM);
        } else {
            exec("kill {$pid}");
        }

        $this->removePid();
    }

    public function readPid(): ?int
    {
        if (! file_exists($this->pidPath())) {
            return null;
        }

        $pid = (int) trim(file_get_contents($this->pidPath()));

        return $pid > 0 ? $pid : null;
    }

    private function writePid(int $pid): void
    {
        $dir = dirname($this->pidPath());

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->pidPath(), $pid);
    }

    private function removePid(): void
    {
        if (file_exists($this->pidPath())) {
            unlink($this->pidPath());
        }
    }

    private function releaseUrl(string $binary): string
    {
        $platform = $this->platform();

        $repo = $binary === 'two_wee_client' ? 'client' : 'web-terminal';

        return "https://github.com/2wee-dev/{$repo}/releases/latest/download/{$binary}-{$platform}";
    }

    private function platform(): string
    {
        $os = strtolower(PHP_OS_FAMILY);
        $arch = php_uname('m');

        if ($os === 'darwin') {
            return str_contains($arch, 'arm') ? 'macos-arm64' : 'macos-x86_64';
        }

        if ($os === 'linux') {
            return str_contains($arch, 'arm') ? 'linux-arm64' : 'linux-x86_64';
        }

        throw new RuntimeException("Unsupported platform: {$os} {$arch}");
    }
}
