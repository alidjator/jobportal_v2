<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Company\Domain\CompanySlug;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use stdClass;

/**
 * Imports the legacy `companies`. The name comes from the owner's account (`users.name`), the owner becomes the company's one company admin, and the slug is made
 * from the name under the rules of the new schema. The industry and the organization type are required and come through the master map (a company without a
 * counterpart is skipped); the team size, website, establishment date and location are kept when valid and emptied (with a note) when not. A company with
 * `document_verified_at` set or `is_profile_verified = 1` keeps the verified badge. No verification document exists in legacy, so none is migrated. Like a company made through the application, each one gets its seven default stages.
 */
final readonly class CompaniesImporter implements Importer
{
    public const string ENTITY = 'companies';

    /** The id-map entity under which a legacy company id is remembered. */
    public const string COMPANY = 'company';

    private const int BATCH = 100;

    private const int TEXT_MAX = 5000;

    public function __construct(
        private LegacySource $legacy,
        private CompanyWriter $companies,
        private LocationLookup $locations,
        private DefaultStageProvisioner $stages,
        private TransactionManager $transactions,
    ) {}

    public function entity(): string
    {
        return self::ENTITY;
    }

    public function dependsOn(): array
    {
        return [MastersImporter::ENTITY, AccountsImporter::ENTITY];
    }

    public function run(ImportContext $context): void
    {
        $usedSlugs = [];

        $this->legacy->chunk('companies', self::BATCH, function (array $rows) use ($context, &$usedSlugs): void {
            $owners = array_values(array_unique(array_map(static fn (stdClass $row): int => (int) $row->user_id, $rows)));
            $maps = [
                'already' => $context->idMap->findMany(self::COMPANY, array_map(static fn (stdClass $row): int => (int) $row->id, $rows)),
                'owners' => $context->idMap->findMany(AccountsImporter::USER, $owners),
                'industries' => $this->masterMap($context, 'industries', $rows, 'industry_type_id'),
                'organizationTypes' => $this->masterMap($context, 'organization_types', $rows, 'organization_type_id'),
                'teamSizes' => $this->masterMap($context, 'team_sizes', $rows, 'team_size_id'),
            ];
            $names = [];

            foreach ($this->legacy->whereIn('users', 'id', $owners, ['id', 'name']) as $user) {
                $names[(int) $user->id] = LegacyText::clean((string) $user->name);
            }

            $this->transactions->run(function () use ($rows, $context, $maps, $names, &$usedSlugs): void {
                foreach ($rows as $row) {
                    $this->importRow($row, $context, $maps, $names, $usedSlugs);
                }
            });
        });
    }

    /**
     * @param  array<string, array<int, int>>  $maps
     * @param  array<int, string|null>  $names  the owners' names by legacy user id
     * @param  array<string, true>  $usedSlugs
     */
    private function importRow(stdClass $row, ImportContext $context, array $maps, array $names, array &$usedSlugs): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $report->read(self::ENTITY);

        if (isset($maps['already'][$legacyId])) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        $owner = $maps['owners'][(int) $row->user_id] ?? null;
        $name = $names[(int) $row->user_id] ?? null;
        $industry = $maps['industries'][(int) $row->industry_type_id] ?? null;
        $organizationType = $maps['organizationTypes'][(int) $row->organization_type_id] ?? null;

        $reason = match (true) {
            $owner === null => 'owner-not-imported',
            $name === null || mb_strlen($name) < 2 || mb_strlen($name) > 255 => 'name-missing',
            $industry === null => 'industry-unmatched',
            $organizationType === null => 'organization-type-unmatched',
            default => null,
        };

        if ($reason !== null) {
            $report->skipped(self::ENTITY, $legacyId, $reason);

            return;
        }

        $teamSize = $row->team_size_id === null ? null : ($maps['teamSizes'][(int) $row->team_size_id] ?? null);

        if ($row->team_size_id !== null && $teamSize === null) {
            $report->note(self::ENTITY, 'team-size-unmatched');
        }

        $slug = $this->slug($name, $legacyId, $usedSlugs);
        $usedSlugs[$slug] = true;
        $createdAt = $this->time($row->created_at, $context);

        $company = new NewCompany(
            $name,
            $slug,
            $owner,
            $this->website($row->website, $context),
            $this->date($row->establishment_date, $context),
            LegacyText::limit(LegacyText::plain($row->bio), self::TEXT_MAX),
            LegacyText::limit(LegacyText::plain($row->vision), self::TEXT_MAX),
            $industry,
            $organizationType,
            $teamSize,
            LegacyText::limit(LegacyText::clean($row->address), 255),
            $this->locations->resolve($this->text($row->region), $this->text($row->district), $this->text($row->subdistrict), $this->text($row->kelurahan), $this->text($row->postcode)),
            (int) $row->visibility === 1,
            $this->verifiedAt($row, $context),
            $createdAt,
            $row->updated_at === null ? $createdAt : $this->time($row->updated_at, $context),
        );

        if ($context->write) {
            $companyId = $this->companies->insert($company);
            $this->stages->provision($companyId);
            $context->idMap->remember(self::COMPANY, $legacyId, $companyId, $context->clock->now());
        }

        $report->imported(self::ENTITY);
    }

    /**
     * @param  list<stdClass>  $rows
     * @return array<int, int>
     */
    private function masterMap(ImportContext $context, string $master, array $rows, string $column): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (stdClass $row): ?int => $row->{$column} === null ? null : (int) $row->{$column}, $rows))));

        return $context->idMap->findMany(MasterCatalog::entityFor($master), $ids);
    }

    /** @param  array<string, true>  $used */
    private function slug(string $name, int $legacyId, array $used): string
    {
        $base = SlugMaker::from($name, CompanySlug::MAX_LENGTH);

        foreach ([$base, 'company-'.$legacyId] as $candidate) {
            for ($suffix = 1; $suffix <= 50; $suffix++) {
                $attempt = $suffix === 1 ? $candidate : substr($candidate, 0, CompanySlug::MAX_LENGTH - strlen((string) $suffix) - 1).'-'.$suffix;

                try {
                    $slug = CompanySlug::fromInput($attempt)->value;
                } catch (BusinessRuleViolation) {
                    break;
                }

                if (! isset($used[$slug]) && ! $this->companies->slugTaken($slug)) {
                    return $slug;
                }
            }
        }

        return 'company-'.$legacyId.'-'.bin2hex(random_bytes(3));
    }

    private function website(?string $value, ImportContext $context): ?string
    {
        $value = LegacyText::clean($value);

        if ($value === null) {
            return null;
        }

        if (mb_strlen($value) <= 255 && filter_var($value, FILTER_VALIDATE_URL) !== false && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return $value;
        }

        $context->report->note(self::ENTITY, 'website-invalid');

        return null;
    }

    private function date(mixed $value, ImportContext $context): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $value, 0, 10), new DateTimeZone('UTC'));

        if ($date !== false && $date <= $context->clock->now() && (int) $date->format('Y') >= 1800) {
            return $date->format('Y-m-d');
        }

        $context->report->note(self::ENTITY, 'establishment-date-invalid');

        return null;
    }

    /** The badge: the legacy verification time, or the run's time when only the flag is set. */
    private function verifiedAt(stdClass $row, ImportContext $context): ?string
    {
        if ($row->document_verified_at !== null && $row->document_verified_at !== '') {
            return $this->time($row->document_verified_at, $context);
        }

        return (int) $row->is_profile_verified === 1 ? $this->time(null, $context) : null;
    }

    private function text(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function time(mixed $value, ImportContext $context): string
    {
        $time = $value === null || $value === '' ? $context->clock->now() : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
