<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;

/**
 * Tests that insert rows pointing to reference data or to a company need real parent rows now that the database holds foreign keys to
 * `reference_*`, `company_companies`, `identity_users` and `identity_admins`. Every helper finds the row by its slug or creates it, so calling it again in the same test returns
 * the same id.
 */
trait CreatesReferenceRows
{
    /** @param  array<string, mixed>  $extra  columns a table needs besides the name, slug and order (for example `kind` of a skill) */
    protected function referenceId(string $table, string $slug, array $extra = []): int
    {
        $existing = DB::table($table)->where('slug', $slug)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table($table)->insertGetId([
            'name' => json_encode(['id' => $slug, 'en' => $slug]), 'slug' => $slug, 'sort_order' => 0, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(), ...$extra,
        ]);
    }

    protected function industryId(): int
    {
        return $this->referenceId('reference_industries', 'technology');
    }

    protected function organizationTypeId(): int
    {
        return $this->referenceId('reference_organization_types', 'private-company');
    }

    protected function skillId(string $slug = 'php'): int
    {
        return $this->referenceId('reference_skills', $slug, ['kind' => 'hard']);
    }

    protected function tagId(string $slug = 'remote-friendly'): int
    {
        return $this->referenceId('reference_tags', $slug);
    }

    protected function benefitId(string $slug = 'health-insurance'): int
    {
        return $this->referenceId('reference_benefits', $slug);
    }

    /** One valid chain of the location masters. @return array{province_id: int, city_id: int, subdistrict_id: int, village_id: int, postal_code_id: int} */
    protected function locationChain(): array
    {
        $province = DB::table('reference_provinces')->where('bps_code', '32')->value('id')
            ?? DB::table('reference_provinces')->insertGetId(['bps_code' => '32', 'name' => 'JAWA BARAT']);
        $city = DB::table('reference_cities')->where('bps_code', '3273')->value('id')
            ?? DB::table('reference_cities')->insertGetId(['province_id' => $province, 'bps_code' => '3273', 'name' => 'BANDUNG', 'type' => 'city']);
        $subdistrict = DB::table('reference_subdistricts')->where('bps_code', '327301')->value('id')
            ?? DB::table('reference_subdistricts')->insertGetId(['city_id' => $city, 'bps_code' => '327301', 'name' => 'SUKASARI']);
        $village = DB::table('reference_villages')->where('bps_code', '3273011001')->value('id')
            ?? DB::table('reference_villages')->insertGetId(['subdistrict_id' => $subdistrict, 'bps_code' => '3273011001', 'name' => 'GEGERKALONG']);
        $postalCode = DB::table('reference_postal_codes')->where('code', '40153')->value('id')
            ?? DB::table('reference_postal_codes')->insertGetId(['village_id' => $village, 'code' => '40153']);

        return [
            'province_id' => (int) $province, 'city_id' => (int) $city, 'subdistrict_id' => (int) $subdistrict,
            'village_id' => (int) $village, 'postal_code_id' => (int) $postalCode,
        ];
    }

    protected function companyRowId(string $slug = 'pt-maju'): int
    {
        $existing = DB::table('company_companies')->where('slug', $slug)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $id = (int) DB::table('company_companies')->insertGetId([
            'name' => strtoupper($slug), 'slug' => $slug, 'status' => 'active', 'industry_id' => $this->industryId(),
            'organization_type_id' => $this->organizationTypeId(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        // Every company has a main work location.
        DB::table('company_locations')->insert([
            'company_id' => $id, 'name' => 'Kantor Pusat', 'type' => 'head_office', 'is_main' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    protected function userRowId(string $email = 'someone@example.test'): int
    {
        return (int) (UserModel::query()->where('email', $email)->value('id')
            ?? UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Someone', 'email' => $email, 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00'])->id);
    }

    protected function adminRowId(string $email = 'admin@example.test'): int
    {
        return (int) (AdminModel::query()->where('email', $email)->value('id')
            ?? AdminModel::query()->create(['name' => 'Admin', 'email' => $email, 'password' => 'hash', 'status' => 'active'])->id);
    }

    /**
     * A platform campaign (no company) created by an admin; with no dates it is active on any day.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function campaignRowId(string $code, array $overrides = []): int
    {
        return (int) DB::table('campaign_campaigns')->insertGetId([
            'company_id' => null, 'code' => $code, 'name' => 'Campaign '.$code, 'type' => 'job_fair', 'starts_on' => null, 'ends_on' => null, 'is_active' => true,
            'created_by_user_id' => null, 'created_by_admin_id' => $this->adminRowId(), 'created_at' => now(), 'updated_at' => now(), ...$overrides,
        ]);
    }
}
