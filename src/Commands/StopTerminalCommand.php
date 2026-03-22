<?php

namespace TwoWee\Laravel\Commands;

use Illuminate\Console\Command;
use TwoWee\Laravel\Terminal\BinManager;

class StopTerminalCommand extends Command
{
    protected $signature = '2wee:stop-terminal';

    protected $description = 'Stop the 2Wee web terminal service';

    public function handle(BinManager $bin): int
    {
        if (! $bin->isRunning()) {
            $this->info('2Wee web terminal is not running.');
            return self::SUCCESS;
        }

        $pid = $bin->readPid();
        $bin->stop();
        $this->info("2Wee web terminal stopped (PID {$pid}).");

        return self::SUCCESS;
    }
}
