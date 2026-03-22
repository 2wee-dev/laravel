<?php

namespace TwoWee\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeResourceCommand extends Command
{
    protected $signature = '2wee:resource {name} {--model= : The Eloquent model class}';

    protected $description = 'Create a new 2wee resource';

    public function handle(): int
    {
        $name = $this->argument('name');
        $modelOption = $this->option('model');

        if ($modelOption) {
            $modelClass = Str::startsWith($modelOption, 'App\\')
                ? $modelOption
                : 'App\\Models\\' . $modelOption;
        } else {
            $modelClass = 'App\\Models\\' . Str::replaceLast('Resource', '', $name);
        }

        $modelBaseName = class_basename($modelClass);
        $slug = Str::plural(Str::snake($modelBaseName));

        $stub = $this->buildStub($name, $modelClass, $modelBaseName, $slug);

        $directory = app_path('TwoWee/Resources');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . '/' . $name . '.php';

        if (file_exists($path)) {
            $this->error("Resource {$name} already exists at {$path}");

            return self::FAILURE;
        }

        file_put_contents($path, $stub);

        $this->info("Resource created: {$path}");

        return self::SUCCESS;
    }

    protected function buildStub(
        string $name,
        string $modelClass,
        string $modelBaseName,
        string $slug
    ): string {
        return <<<PHP
        <?php

        namespace App\TwoWee\Resources;

        use {$modelClass};
        use TwoWee\Laravel\Columns;
        use TwoWee\Laravel\Fields;
        use TwoWee\Laravel\Resource;
        use TwoWee\Laravel\Section;

        class {$name} extends Resource
        {
            protected static string \$model = {$modelBaseName}::class;

            protected static string \$label = '{$modelBaseName}';

            protected static ?string \$slug = '{$slug}';

            public static function form(): array
            {
                return [
                    Section::make('General')
                        ->column(0)
                        ->rowGroup(0)
                        ->fields([
                            // Fields\Text::make('name')
                            //     ->label('Name')
                            //     ->width(30)
                            //     ->required(),
                        ]),
                ];
            }

            public static function table(): array
            {
                return [
                    // Columns\TextColumn::make('name')
                    //     ->label('Name')
                    //     ->width('fill'),
                ];
            }
        }
        PHP;
    }
}
