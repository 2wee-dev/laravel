<?php

namespace TwoWee\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeLookupCommand extends Command
{
    protected $signature = '2wee:lookup {name} {--model= : The Eloquent model class}';

    protected $description = 'Create a new 2wee lookup class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $modelOption = $this->option('model');

        $modelClass = $modelOption
            ? (Str::startsWith($modelOption, 'App\\') ? $modelOption : 'App\\Models\\' . $modelOption)
            : 'App\\Models\\' . Str::replaceLast('Lookup', '', $name);

        $directory = app_path('TwoWee/Lookups');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . '/' . $name . '.php';

        if (file_exists($path)) {
            $this->error("Lookup {$name} already exists!");

            return self::FAILURE;
        }

        $modelBaseName = class_basename($modelClass);
        $title = Str::plural(Str::headline($modelBaseName));

        file_put_contents($path, <<<PHP
        <?php

        namespace App\TwoWee\Lookups;

        use {$modelClass};
        use TwoWee\Laravel\Columns\TextColumn;
        use TwoWee\Laravel\Lookup\LookupDefinition;

        class {$name}
        {
            public static function definition(): LookupDefinition
            {
                return LookupDefinition::make({$modelBaseName}::class)
                    ->title('{$title}')
                    ->columns([
                        TextColumn::make('id')->label('ID')->width(10),
                        TextColumn::make('name')->label('Name')->width('fill'),
                    ])
                    ->valueColumn('id');
            }
        }
        PHP);

        $this->info("Lookup [{$path}] created successfully.");

        return self::SUCCESS;
    }
}
