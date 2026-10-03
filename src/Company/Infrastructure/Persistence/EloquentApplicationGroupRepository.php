<?php

namespace Src\Company\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Company\Domain\ApplicationGroupRepository;
use Src\Company\Infrastructure\Persistence\Eloquent\ApplicationGroupRecord;

class EloquentApplicationGroupRepository implements ApplicationGroupRepository
{
    public function forCompany(int $companyId): array
    {
        return ApplicationGroupRecord::query()->where('company_id', $companyId)->orderBy('id')->get()->toArray();
    }

    public function find(int $companyId, int $groupId): ?array
    {
        $group = ApplicationGroupRecord::query()->where('company_id', $companyId)->whereKey($groupId)->first();

        return $group === null ? null : ['is_deleteable' => (bool) $group->is_deleteable] + $group->toArray();
    }

    public function create(int $companyId, string $name): array
    {
        return ApplicationGroupRecord::query()->create(['company_id' => $companyId, 'name' => $name])->toArray();
    }

    public function rename(int $groupId, string $name): array
    {
        $group = ApplicationGroupRecord::query()->findOrFail($groupId);
        $group->update(['name' => $name]);

        return $group->toArray();
    }

    public function deleteMovingApplications(int $companyId, int $groupId): void
    {
        DB::transaction(function () use ($companyId, $groupId) {
            $fallback = ApplicationGroupRecord::query()
                ->where('company_id', $companyId)->where('id', '!=', $groupId)->where('is_deleteable', false)->value('id');

            if ($fallback !== null) {
                // Tabel `applied_jobs` (Recruitment): satu-satunya tulis lintas-context, memindahkan lamaran
                // dari grup yang dihapus ke grup default (perilaku aplikasi lama; tanpa ini FK mengosongkan grupnya).
                DB::table('applied_jobs')->where('application_group_id', $groupId)->update(['application_group_id' => $fallback]);
            }

            ApplicationGroupRecord::query()->whereKey($groupId)->delete();
        });
    }
}
