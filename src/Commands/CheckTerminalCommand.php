<?php

namespace TwoWee\Laravel\Commands;

use Illuminate\Console\Command;
use TwoWee\Laravel\Terminal\BinManager;

class CheckTerminalCommand extends Command
{
    protected $signature = '2wee:check-terminal';

    protected $description = 'Check the status of the 2Wee web terminal service';

    public function handle(BinManager $bin): int
    {
        $installed = $bin->isInstalled();
        $running   = $bin->isRunning();
        $pid       = $bin->readPid();

        $this->line('Binary:  ' . ($installed ? '<info>installed</info> (' . $bin->binPath() . ')' : '<comment>not installed</comment>'));
        $this->line('Status:  ' . ($running   ? "<info>running</info> (PID {$pid})" : '<comment>stopped</comment>'));

        if ($running) {
            $port = config('twowee.terminal.port', 7681);
            $this->line("Port:    {$port}");
        }

        return $running ? self::SUCCESS : self::FAILURE;
    }
}
