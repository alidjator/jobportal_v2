<?php

namespace Database\Seeders\Reference;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Base class for the initial values of a multilingual master table.
 *
 * Rows are inserted by slug and existing rows are left untouched, so running a seeder again
 * never overwrites changes that an admin made through the API.
 */
abstract class MasterSeeder extends Seeder
{
    abstract protected function table(): string;

    /**
     * Rows in display order. The slug is generated from the English name; `extra` holds the
     * additional columns of the table (for example `kind` or `min_years`).
     *
     * @return list<array{name: array{id: string, en: string}, extra?: array<string, mixed>}>
     */
    abstract protected function rows(): array;

    public function run(): void
    {
        $now = Carbon::now();
        $records = [];

        foreach (array_values($this->rows()) as $position => $row) {
            $records[] = [
                'name' => json_encode($row['name'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'slug' => Str::slug($row['name']['en']),
                'sort_order' => $position + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
                ...($row['extra'] ?? []),
            ];
        }

        DB::table($this->table())->insertOrIgnore($records);
    }
}
