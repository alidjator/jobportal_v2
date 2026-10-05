<?php

namespace Tests\Feature\Database\Schema;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class CompanyForeignKeysTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    /** @param  array<string, mixed>  $attributes */
    private function company(array $attributes = []): int
    {
        return (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Maju', 'slug' => 'pt-maju', 'status' => 'active', 'industry_id' => $this->industryId(),
            'organization_type_id' => $this->organizationTypeId(), 'created_at' => now(), 'updated_at' => now(), ...$attributes,
        ]);
    }

    /** @return list<string> */
    private function foreignKeyColumns(string $table): array
    {
        return collect(Schema::getForeignKeys($table))->flatMap(fn (array $key): array => $key['columns'])->sort()->values()->all();
    }

    public function test_a_company_points_to_the_reference_data_with_foreign_keys(): void
    {
        $this->assertSame(
            ['city_id', 'industry_id', 'organization_type_id', 'postal_code_id', 'province_id', 'subdistrict_id', 'team_size_id', 'village_id'],
            $this->foreignKeyColumns('company_companies'),
        );
        $this->assertNotContains('created_by_admin_id', $this->foreignKeyColumns('company_companies'));
    }

    public function test_a_company_with_a_full_valid_profile_is_accepted(): void
    {
        $teamSize = $this->referenceId('reference_team_sizes', 'small', ['min_size' => 1, 'max_size' => 10]);

        $id = $this->company([...$this->locationChain(), 'team_size_id' => $teamSize]);

        $this->assertNotNull(DB::table('company_companies')->find($id)->postal_code_id);
    }

    public function test_the_database_refuses_a_reference_that_does_not_exist(): void
    {
        foreach (['industry_id', 'organization_type_id', 'team_size_id', 'province_id', 'city_id', 'subdistrict_id', 'village_id', 'postal_code_id'] as $column) {
            try {
                $this->company([$column => 999999]);
                $this->fail("{$column} accepted an id that does not exist");
            } catch (QueryException) {
                $this->assertSame(0, DB::table('company_companies')->count(), $column);
            }
        }
    }

    public function test_a_master_row_cannot_be_deleted_while_a_company_uses_it(): void
    {
        $this->company();

        foreach (['reference_industries', 'reference_organization_types'] as $table) {
            try {
                DB::table($table)->delete();
                $this->fail("{$table} row was deleted while a company uses it");
            } catch (QueryException) {
                $this->assertGreaterThan(0, DB::table($table)->count(), $table);
            }
        }
    }

    public function test_a_proposed_benefit_points_to_a_company_or_to_none(): void
    {
        $this->assertContains('proposed_by_company_id', $this->foreignKeyColumns('reference_benefits'));

        $company = $this->company();
        $withCompany = $this->referenceId('reference_benefits', 'gym', ['proposed_by_company_id' => $company]);
        $withoutCompany = $this->referenceId('reference_benefits', 'meals');

        $this->assertSame($company, (int) DB::table('reference_benefits')->find($withCompany)->proposed_by_company_id);
        $this->assertNull(DB::table('reference_benefits')->find($withoutCompany)->proposed_by_company_id);

        $this->expectException(QueryException::class);

        $this->referenceId('reference_benefits', 'invented', ['proposed_by_company_id' => 999999]);
    }

    public function test_a_company_cannot_be_deleted_while_it_has_jobs_or_proposals(): void
    {
        $company = $this->company();
        DB::table('job_jobs')->insert(['company_id' => $company, 'title' => 'Job', 'slug' => 'job', 'created_by_user_id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);

        DB::table('company_companies')->where('id', $company)->delete();
    }
}
