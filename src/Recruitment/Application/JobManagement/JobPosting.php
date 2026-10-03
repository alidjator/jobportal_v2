<?php

namespace Src\Recruitment\Application\JobManagement;

use Carbon\Carbon;
use Src\Recruitment\Domain\JobManagement\ApplyChannel;
use Src\Recruitment\Domain\JobManagement\PromotionBadge;
use Src\Recruitment\Domain\JobManagement\SalaryRange;

/** Data lowongan hasil validasi (input store/update). */
final class JobPosting
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $title,
        public readonly int $categoryId,
        public readonly int $roleId,
        public readonly int $experienceId,
        public readonly int $educationId,
        public readonly int $jobTypeId,
        public readonly string $vacancies,
        public readonly string $salaryMode,
        public readonly ?string $customSalary,
        public readonly SalaryRange $salary,
        public readonly int $salaryTypeId,
        public readonly string $showSalary,
        public readonly string $deadline,
        public readonly ApplyChannel $applyChannel,
        public readonly string $description,
        public readonly int $isRemote,
        public readonly int $workTimeId,
        public readonly ?int $subdistrictBps,
        public readonly int $cityBps,
        public readonly int $provinceBps,
        public readonly int $jobLevelId,
        public readonly string $hardSkill,
        public readonly string $softSkill,
        public readonly string $benefits,
        public readonly ?string $notes,
        public readonly ?string $jobOrderId,
        public readonly ?PromotionBadge $badge,
        public readonly array $tags,
    ) {}

    /** @param  array<string, mixed>  $v  input tervalidasi (lihat StoreJobRequest) */
    public static function fromValidated(array $v): self
    {
        $tags = array_values(array_filter((array) ($v['tags'] ?? []), fn ($t) => is_string($t) && $t !== ''));

        return new self(
            title: $v['title'],
            categoryId: (int) $v['category_id'],
            roleId: (int) $v['role_id'],
            experienceId: (int) $v['experience'],
            educationId: (int) $v['education'],
            jobTypeId: (int) $v['job_type'],
            vacancies: (string) $v['vacancies'],
            salaryMode: (string) $v['salary_mode'],
            customSalary: isset($v['custom_salary']) ? (string) $v['custom_salary'] : null,
            salary: SalaryRange::of($v['min_salary'], $v['max_salary']),
            salaryTypeId: (int) $v['salary_type'],
            showSalary: (string) $v['show_salary'],
            deadline: Carbon::parse($v['deadline'])->format('Y-m-d'),
            applyChannel: ApplyChannel::of($v['apply_on'], $v['apply_email'] ?? null, $v['apply_url'] ?? null),
            description: (string) $v['description'],
            isRemote: (int) filter_var($v['is_remote'] ?? 0, FILTER_VALIDATE_BOOLEAN),
            workTimeId: (int) $v['work_time'],
            subdistrictBps: isset($v['subdistrict_id']) ? (int) $v['subdistrict_id'] : null,
            cityBps: (int) $v['city_id'],
            provinceBps: (int) $v['province_id'],
            jobLevelId: (int) $v['grp_pos_id'],
            hardSkill: (string) $v['hard_skill'],
            softSkill: (string) $v['soft_skill'],
            benefits: (string) $v['benefits'],
            notes: isset($v['notes']) ? (string) $v['notes'] : null,
            jobOrderId: isset($v['job_order_id']) ? (string) $v['job_order_id'] : null,
            badge: PromotionBadge::tryFrom((string) ($v['badge'] ?? '')),
            tags: $tags,
        );
    }

    /**
     * Kolom tabel `jobs` dari input. Catatan skema: `grp_pos_id` -> `job_level_id`, `subdistrict_id` -> `subdistrict`.
     *
     * @return array<string, mixed>
     */
    public function toColumns(string $country, string $exactLocation): array
    {
        return [
            'title' => $this->title,
            'category_id' => $this->categoryId,
            'role_id' => $this->roleId,
            'education_id' => $this->educationId,
            'experience_id' => $this->experienceId,
            'salary_mode' => $this->salaryMode,
            'custom_salary' => $this->customSalary,
            'min_salary' => $this->salary->minAsInt(),
            'max_salary' => $this->salary->maxAsInt(),
            'salary_type_id' => $this->salaryTypeId,
            'deadline' => $this->deadline,
            'job_type_id' => $this->jobTypeId,
            'vacancies' => $this->vacancies,
            'apply_on' => $this->applyChannel->type,
            'apply_email' => $this->applyChannel->email,
            'apply_url' => $this->applyChannel->url,
            'description' => $this->description,
            'is_remote' => $this->isRemote,
            'country' => $country,
            'exact_location' => $exactLocation,
            'work_time' => $this->workTimeId,
            'subdistrict' => $this->subdistrictBps,
            'city_id' => $this->cityBps,
            'province_id' => $this->provinceBps,
            'job_level_id' => $this->jobLevelId,
            'hard_skill' => $this->hardSkill,
            'soft_skill' => $this->softSkill,
            'benefits' => $this->benefits,
            'notes' => $this->notes,
            'show_salary' => $this->showSalary,
            'job_order_id' => $this->jobOrderId,
        ];
    }
}
