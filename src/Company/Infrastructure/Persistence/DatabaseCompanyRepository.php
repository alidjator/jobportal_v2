<?php

namespace Src\Company\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Company\Domain\Company;
use Src\Company\Domain\CompanyProfile;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\CompanyStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\StateConflict;
use stdClass;

final readonly class DatabaseCompanyRepository implements CompanyRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function findById(int $id): ?Company
    {
        $row = $this->connection->table('company_companies')->where('id', $id)->first();

        return $row === null ? null : $this->toCompany($row);
    }

    public function findBySlug(string $slug): ?Company
    {
        $row = $this->connection->table('company_companies')->where('slug', $slug)->first();

        return $row === null ? null : $this->toCompany($row);
    }

    public function count(): int
    {
        return $this->connection->table('company_companies')->count();
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return $this->connection->table('company_companies')
            ->where('slug', $slug)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public function lock(int $companyId): void
    {
        $this->connection->table('company_companies')->where('id', $companyId)->lockForUpdate()->first(['id']);
    }

    public function invitationEmailDomains(int $companyId): ?array
    {
        $json = $this->connection->table('company_companies')->where('id', $companyId)->value('invitation_email_domains');

        return $json === null ? null : array_values(array_map('strval', (array) json_decode((string) $json, true)));
    }

    public function setInvitationEmailDomains(int $companyId, ?array $domains): void
    {
        $this->connection->table('company_companies')->where('id', $companyId)->update([
            'invitation_email_domains' => $domains === null ? null : json_encode($domains),
            'updated_at' => $this->clock->now()->format(self::TIME_FORMAT),
        ]);
    }

    public function save(Company $company): Company
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $profile = $company->profile();
        $columns = [
            'name' => $profile->name,
            'slug' => $company->slug(),
            'status' => $company->status()->value,
            'suspended_at' => $company->suspendedAt()?->format(self::TIME_FORMAT),
            'suspension_reason' => $company->suspensionReason(),
            'logo_path' => $company->logoPath(),
            'banner_path' => $company->bannerPath(),
            'website' => $profile->website,
            'established_on' => $profile->establishedOn?->format('Y-m-d'),
            'bio' => $profile->bio,
            'vision' => $profile->vision,
            'industry_id' => $profile->industryId,
            'organization_type_id' => $profile->organizationTypeId,
            'team_size_id' => $profile->teamSizeId,
            'contact_email' => $profile->contactEmail,
            'contact_phone' => $profile->contactPhone,
            'is_visible' => $profile->isVisible,
            // `verified_at` is deliberately not written here: only CompanyVerificationBadge changes it, and a company loaded earlier in the request
            // would otherwise overwrite a badge that was granted or withdrawn in the meantime.
            'updated_at' => $now,
        ];

        try {
            if ($company->id !== null) {
                $this->connection->table('company_companies')->where('id', $company->id)->update($columns);

                return $company;
            }

            $id = $this->connection->table('company_companies')->insertGetId([
                ...$columns,
                'created_by_admin_id' => $company->createdByAdminId(),
                'created_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two requests took the same slug at the same moment; the unique index decided which one wins.
            throw new StateConflict('This slug is already taken.', 'slug-taken');
        }

        return new Company(
            (int) $id, $company->slug(), $company->status(), $company->verifiedAt(), $company->createdByAdminId(), $profile,
            $company->logoPath(), $company->bannerPath(), $company->suspendedAt(), $company->suspensionReason(),
        );
    }

    private function toCompany(stdClass $row): Company
    {
        return new Company(
            (int) $row->id,
            (string) $row->slug,
            CompanyStatus::from($row->status),
            $row->verified_at === null ? null : new DateTimeImmutable((string) $row->verified_at, new DateTimeZone('UTC')),
            $row->created_by_admin_id === null ? null : (int) $row->created_by_admin_id,
            new CompanyProfile(
                (string) $row->name,
                $row->website,
                $row->established_on === null ? null : new DateTimeImmutable((string) $row->established_on, new DateTimeZone('UTC')),
                $row->bio,
                $row->vision,
                (int) $row->industry_id,
                (int) $row->organization_type_id,
                $this->nullableInt($row->team_size_id),
                $row->contact_email,
                $row->contact_phone,
                (bool) $row->is_visible,
            ),
            $row->logo_path,
            $row->banner_path,
            $row->suspended_at === null ? null : new DateTimeImmutable((string) $row->suspended_at, new DateTimeZone('UTC')),
            $row->suspension_reason,
            $row->created_at === null ? null : new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
