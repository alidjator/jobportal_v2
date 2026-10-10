<?php

namespace Src\Shared\Infrastructure\Documentation;

use Illuminate\Console\Command;

/** Writes the catalog of error codes to `docs/api/error-catalog.md`; with `--check` it only tells whether the file is up to date. */
final class ErrorCatalogCommand extends Command
{
    protected $signature = 'api:error-catalog {--check : Fail when the file is out of date instead of writing it}';

    protected $description = 'Write the catalog of API error codes (docs/api/error-catalog.md)';

    public function handle(): int
    {
        $file = base_path('docs/api/error-catalog.md');
        $content = ErrorCatalogDocument::render(new ErrorCatalog(base_path('src')));

        if ($this->option('check')) {
            if (! is_file($file) || file_get_contents($file) !== $content) {
                $this->error('docs/api/error-catalog.md is out of date. Run `php artisan api:error-catalog`.');

                return self::FAILURE;
            }

            $this->info('The error catalog is up to date.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, $content);
        $this->info('Written docs/api/error-catalog.md.');

        return self::SUCCESS;
    }
}
