<?php

namespace TwoWee\Laravel\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = '2wee:install';

    protected $description = 'Install the 2wee package';

    public function handle(): int
    {
        $this->info('Installing 2wee...');

        $this->call('vendor:publish', [
            '--tag' => 'twowee-config',
        ]);

        $resourceDir = app_path('TwoWee/Resources');
        if (! is_dir($resourceDir)) {
            mkdir($resourceDir, 0755, true);
            $this->info('Created ' . $resourceDir);
        }

        $this->info('2wee installed successfully.');

        return self::SUCCESS;
    }
}
