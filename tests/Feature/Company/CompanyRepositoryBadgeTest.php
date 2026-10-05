<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Facades\DB;
use Src\Company\Domain\CompanyRepository;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class CompanyRepositoryBadgeTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private function company(?string $verifiedAt = null): int
    {
        return (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Maju', 'slug' => 'pt-maju', 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'verified_at' => $verifiedAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function verifiedAt(int $id): ?string
    {
        $value = DB::table('company_companies')->where('id', $id)->value('verified_at');

        return $value === null ? null : (string) $value;
    }

    public function test_saving_a_company_loaded_earlier_does_not_erase_a_badge_granted_meanwhile(): void
    {
        $id = $this->company();
        $repository = $this->app->make(CompanyRepository::class);

        $stale = $repository->findById($id);
        DB::table('company_companies')->where('id', $id)->update(['verified_at' => '2026-10-05 10:00:00']);

        $repository->save($stale);

        $this->assertSame('2026-10-05 10:00:00', $this->verifiedAt($id));
    }

    public function test_saving_a_company_loaded_earlier_does_not_bring_back_a_withdrawn_badge(): void
    {
        $id = $this->company('2026-01-01 10:00:00');
        $repository = $this->app->make(CompanyRepository::class);

        $stale = $repository->findById($id);
        DB::table('company_companies')->where('id', $id)->update(['verified_at' => null]);

        $repository->save($stale);

        $this->assertNull($this->verifiedAt($id));
    }
}
