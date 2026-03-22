<?php

namespace TwoWee\Laravel\Commands;

use Illuminate\Console\Command;
use TwoWee\Laravel\Terminal\BinManager;

class InstallTerminalCommand extends Command
{
    protected $signature = '2wee:install-terminal';

    protected $description = 'Download the two_wee_terminal and two_wee_client binaries';

    public function handle(BinManager $bin): int
    {
        if ($bin->isInstalled()) {
            $this->info('two_wee_terminal is already installed at:');
            $this->line('  ' . $bin->binPath());

            if (! $this->confirm('Re-download?', false)) {
                return self::SUCCESS;
            }
        }

        $this->info('Downloading binaries for ' . php_uname('s') . ' ' . php_uname('m') . '...');

        try {
            $bin->install();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Installed:');
        $this->line('  ' . $bin->binPath());
        $this->line('  ' . $bin->clientBinPath());
        $this->newLine();
        $this->info('Run <comment>php artisan 2wee:start-terminal</comment> to start the web terminal.');

        return self::SUCCESS;
    }
}
