<?php

namespace Tests\Feature\Database\Seeders\Company;

use Database\Seeders\Company\CompanyDocumentTypeSeeder;
use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

final class CompanyDocumentTypeSeederTest extends FeatureTestCase
{
    public function test_it_creates_the_three_initial_types_in_order(): void
    {
        $this->seed(CompanyDocumentTypeSeeder::class);

        $rows = DB::table('company_document_types')->orderBy('sort_order')->get();

        $this->assertSame(
            ['business-registration', 'tax-id', 'deed-of-establishment'],
            $rows->pluck('slug')->all(),
        );
        $this->assertSame([1, 1, 0], $rows->pluck('is_required')->map(fn ($v): int => (int) $v)->all());
        $this->assertSame([1, 1, 1], $rows->pluck('is_active')->map(fn ($v): int => (int) $v)->all());
        $this->assertSame([1, 2, 3], $rows->pluck('sort_order')->map(fn ($v): int => (int) $v)->all());
    }

    public function test_every_type_has_both_names(): void
    {
        $this->seed(CompanyDocumentTypeSeeder::class);

        foreach (DB::table('company_document_types')->get() as $row) {
            $name = json_decode($row->name, true, flags: JSON_THROW_ON_ERROR);

            $this->assertNotSame('', $name['id'] ?? '', $row->slug);
            $this->assertNotSame('', $name['en'] ?? '', $row->slug);
        }
    }

    public function test_seeding_twice_does_not_duplicate_or_overwrite_admin_changes(): void
    {
        $this->seed(CompanyDocumentTypeSeeder::class);

        DB::table('company_document_types')
            ->where('slug', 'tax-id')
            ->update(['is_required' => false, 'is_active' => false]);

        $this->seed(CompanyDocumentTypeSeeder::class);

        $this->assertSame(3, DB::table('company_document_types')->count());

        $changed = DB::table('company_document_types')->where('slug', 'tax-id')->first();
        $this->assertSame(0, (int) $changed->is_required);
        $this->assertSame(0, (int) $changed->is_active);
    }
}
