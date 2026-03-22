<?php

namespace TwoWee\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeActionCommand extends Command
{
    protected $signature = '2wee:action {name}';

    protected $description = 'Create a new 2wee save action class';

    public function handle(): int
    {
        $name = $this->argument('name');

        $directory = app_path('TwoWee/Actions');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . '/' . $name . '.php';

        if (file_exists($path)) {
            $this->error("Action {$name} already exists!");

            return self::FAILURE;
        }

        file_put_contents($path, <<<PHP
        <?php

        namespace App\TwoWee\Actions;

        use Illuminate\Database\Eloquent\Model;
        use Illuminate\Support\Facades\DB;
        use TwoWee\Laravel\Contracts\SaveAction;

        final readonly class {$name} implements SaveAction
        {
            public function handle(Model \$model, array \$changes, ?array \$lines): void
            {
                DB::transaction(function () use (\$model, \$lines) {
                    //
                });
            }
        }
        PHP);

        $this->info("Action [{$path}] created successfully.");

        return self::SUCCESS;
    }
}
