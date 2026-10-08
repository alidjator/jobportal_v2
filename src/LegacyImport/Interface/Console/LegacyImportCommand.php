<?php

namespace Src\LegacyImport\Interface\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Src\LegacyImport\Application\ApplicationsImporter;
use Src\LegacyImport\Application\ImportRunner;
use Src\LegacyImport\Application\StageGroupMapping;

/**
 * Imports legacy rows into the new schema. **By default it only reads and reports** (a dry run); nothing is written unless `--write` is given, and `--write`
 * against real data needs the user's explicit approval. Mapping files are `--map=<name>=<path to a JSON file>` (for example `masters` or `stages`).
 */
final class LegacyImportCommand extends Command
{
    protected $signature = 'legacy:import
        {entity? : The entity to import (accounts, companies, ...); leave it out with --all}
        {--all : Import every entity in dependency order}
        {--write : Really write to the new schema (without it the run only reads and reports)}
        {--map=* : A mapping file as name=path (a JSON file)}';

    protected $description = 'Import legacy data (karir.pkp) into the new schema; a dry run unless --write is given';

    public function handle(ImportRunner $runner): int
    {
        $entity = $this->argument('entity');
        $all = (bool) $this->option('all');

        if (($entity === null) === ! $all) {
            $this->components->error('Name one entity, or use --all (not both).');

            return self::FAILURE;
        }

        try {
            $result = $runner->run($all ? null : (string) $entity, (bool) $this->option('write'), $this->mappings());
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['report']->summary() as $line) {
            $this->line($line);
        }

        $this->components->info('Report: '.$result['path']);

        if (! $this->option('write')) {
            $this->components->warn('Dry run: nothing was written. Add --write to import.');
            $this->components->info('An entity that depends on others (companies, images, ...) looks its parents up in the id map, so a dry run before they were written reports those rows as skipped.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<string, mixed>>
     *
     * @throws RuntimeException when a mapping file cannot be read
     */
    private function mappings(): array
    {
        $mappings = [];

        foreach ((array) $this->option('map') as $option) {
            [$name, $path] = array_pad(explode('=', (string) $option, 2), 2, '');

            if ($name === '' || $path === '' || ! is_file($path)) {
                throw new RuntimeException("The mapping file \"{$option}\" must be name=path to an existing file.");
            }

            $json = (string) file_get_contents($path);
            $decoded = json_decode($json, true);

            if (! is_array($decoded)) {
                throw new RuntimeException("The mapping file \"{$path}\" is not a JSON object.");
            }

            // A decoded array cannot show a key written twice (the last one wins), so the pipeline group file is checked on its text.
            if ($name === ApplicationsImporter::MAPPING && ($duplicates = StageGroupMapping::duplicateKeys($json)) !== []) {
                throw new RuntimeException('The mapping file "'.$path.'" has the group "'.$duplicates[0].'" more than once.');
            }

            $mappings[$name] = $decoded;
        }

        return $mappings;
    }
}
