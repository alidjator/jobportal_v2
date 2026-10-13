<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\LegacySchema;

/**
 * Gives a test the synthetic legacy tables (empty) behind the `legacy` connection, and helpers to fill them. The local disk is faked as well, so the
 * reports of the runs in a test never land in the real `storage/app/private/legacy-import/`.
 */
trait UsesLegacyDatabase
{
    protected function setUpLegacyDatabase(): void
    {
        Storage::fake('local');
        LegacySchema::prepare();
    }

    /**
     * Adds an item to a translated legacy master.
     *
     * @param  array<string, string>  $names  the name by locale
     */
    protected function legacyMasterItem(string $master, int $id, array $names): void
    {
        [, $translations, $foreignKey] = collect(LegacySchema::TRANSLATED_MASTERS)->first(fn (array $row): bool => $row[0] === $master);

        DB::connection('legacy')->table($master)->insert(['id' => $id, 'created_at' => now(), 'updated_at' => now()]);

        foreach ($names as $locale => $name) {
            DB::connection('legacy')->table($translations)->insert([$foreignKey => $id, 'name' => $name, 'locale' => $locale, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    protected function legacyLanguage(int $id, string $name): void
    {
        DB::connection('legacy')->table('candidate_languages')->insert(['id' => $id, 'name' => $name, 'slug' => strtolower($name), 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Adds a legacy user and returns its id. `$candidate` also creates the candidate row (any column of it can be given).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $candidate
     */
    protected function legacyUser(array $attributes = [], ?array $candidate = null): int
    {
        static $counter = 0;
        $counter++;

        $id = (int) DB::connection('legacy')->table('users')->insertGetId([
            'name' => 'Siti Rahma', 'email' => "legacy{$counter}@example.test", 'role' => 'candidate', 'status' => 1, 'is_demo_field' => 0,
            'created_at' => '2024-03-01 08:00:00', 'updated_at' => '2024-03-02 08:00:00', ...$attributes,
        ]);

        if ($candidate !== null || ($attributes['role'] ?? 'candidate') === 'candidate') {
            DB::connection('legacy')->table('candidates')->insert(['user_id' => $id, ...($candidate ?? [])]);
        }

        return $id;
    }

    /**
     * Adds a legacy company owned by the user and returns its id.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function legacyCompany(int $userId, int $industry, int $organizationType, array $attributes = []): int
    {
        return (int) DB::connection('legacy')->table('companies')->insertGetId([
            'user_id' => $userId, 'industry_type_id' => $industry, 'organization_type_id' => $organizationType,
            'created_at' => '2024-03-01 08:00:00', 'updated_at' => '2024-03-02 08:00:00', ...$attributes,
        ]);
    }

    /**
     * Adds a row to any synthetic legacy table and returns its id.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function legacyRow(string $table, array $attributes): int
    {
        return (int) DB::connection('legacy')->table($table)->insertGetId($attributes);
    }

    /** The id of the candidate row of a legacy user. */
    protected function legacyCandidateId(int $userId): int
    {
        return (int) DB::connection('legacy')->table('candidates')->where('user_id', $userId)->value('id');
    }
}
