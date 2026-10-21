<?php

namespace Src\Shared\Infrastructure\Documentation;

use Illuminate\Console\Command;

/** Writes the catalog of error codes to `docs/api/error-catalog.md`; with `--check` it only tells whether the file is up to date. */
final class ErrorCatalogCommand extends Command
{
    public const string TRANSLATIONS = 'lang/id.json';

    protected $signature = 'api:error-catalog
        {--check : Fail when the file is out of date or a message has no Indonesian translation, instead of writing the file}
        {--untranslated : List the messages that have no Indonesian translation and the message expressions that cannot be read}';

    protected $description = 'Write the catalog of API error codes (docs/api/error-catalog.md) and check the translations of the messages (lang/id.json)';

    public function handle(): int
    {
        $file = base_path('docs/api/error-catalog.md');
        $catalog = new ErrorCatalog(base_path('src'));

        if ($this->option('untranslated')) {
            foreach ($catalog->untranslated($this->translations()) as $template) {
                $this->line($template);
            }

            foreach ($catalog->templates()['unresolved'] as $site) {
                $this->warn("Unresolved message: {$site}");
            }

            return self::SUCCESS;
        }

        $content = ErrorCatalogDocument::render($catalog);

        if ($this->option('check')) {
            if (! is_file($file) || file_get_contents($file) !== $content) {
                $this->error('docs/api/error-catalog.md is out of date. Run `php artisan api:error-catalog`.');

                return self::FAILURE;
            }

            $missing = $catalog->untranslated($this->translations());

            if ($missing !== []) {
                $this->error(count($missing).' messages have no translation in '.self::TRANSLATIONS.' (`php artisan api:error-catalog --untranslated` lists them), for example: '.$missing[0]);

                return self::FAILURE;
            }

            $this->info('The error catalog is up to date and every message is translated.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, $content);
        $this->info('Written docs/api/error-catalog.md.');

        return self::SUCCESS;
    }

    /** @return array<string, string> */
    private function translations(): array
    {
        $path = base_path(self::TRANSLATIONS);
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : [];

        return is_array($decoded) ? $decoded : [];
    }
}
