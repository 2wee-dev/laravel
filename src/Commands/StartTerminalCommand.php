<?php

namespace TwoWee\Laravel\Commands;

use Illuminate\Console\Command;
use TwoWee\Laravel\Terminal\BinManager;

class StartTerminalCommand extends Command
{
    protected $signature = '2wee:start-terminal {--port=7681 : Port to listen on} {--force : Restart if already running}';

    protected $description = 'Start the 2Wee web terminal service';

    public function handle(BinManager $bin): int
    {
        $port = (int) $this->option('port');

        if ($bin->isRunning()) {
            if (! $this->option('force')) {
                $this->info('2Wee web terminal is already running (PID ' . $bin->readPid() . ').');
                return self::SUCCESS;
            }

            $this->info('Stopping existing process...');
            $bin->stop();
            sleep(1);
        }

        if (! $bin->isInstalled()) {
            $this->info('Downloading two_wee_terminal...');

            try {
                $bin->install();
                $this->info('Downloaded successfully.');
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
        }

        $process = null;

        try {
            $process = $bin->open($port);
            $this->info("2Wee web terminal running on port {$port} (PID {$bin->readPid()}).");
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        // Block until the binary exits — Supervisor manages this process.
        $bin->wait($process);

        return self::SUCCESS;
    }
}
