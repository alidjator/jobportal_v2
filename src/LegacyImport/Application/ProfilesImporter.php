<?php

namespace Src\LegacyImport\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\TransactionManager;
use stdClass;

/**
 * Imports the legacy `candidates` as job seeker profiles, for the job seeker accounts that were imported (the name stays on the account). Every value is
 * tidied to the rules of the new profile or emptied with a note, never the whole profile dropped for an optional field. The two visibility switches start off
 * (the legacy ones say nothing about a choice). Phone and WhatsApp: an optional `+` and 8 to 15 digits. A birth date, the date a job seeker is available from and the expected
 * salary are kept as they are, with no age, window or range check (the legacy data is migrated as it is). Gender from `gender`; marital status from the known texts; religion and experience level through the master map; availability from `status`
 * (`available_in` keeps its date); the location through BPS codes; `title` is the headline (100) and `bio` the plain-text summary
 * (2,000); the expected salary is monthly.
 */
final readonly class ProfilesImporter implements Importer
{
    public const string ENTITY = 'profiles';

    /** The id-map entity under which a legacy candidate id is remembered. */
    public const string PROFILE = 'profile';

    private const int BATCH = 100;

    private const array MARITAL = [
        'single' => 'single', 'belum menikah' => 'single', 'belum kawin' => 'single', 'lajang' => 'single',
        'married' => 'married', 'menikah' => 'married', 'kawin' => 'married', 'sudah menikah' => 'married',
        'divorced' => 'divorced', 'cerai' => 'divorced', 'cerai hidup' => 'divorced',
        'widowed' => 'widowed', 'janda' => 'widowed', 'duda' => 'widowed', 'cerai mati' => 'widowed', 'janda duda' => 'widowed',
    ];

    public function __construct(
        private LegacySource $legacy,
        private JobSeekerWriter $writer,
        private LocationLookup $locations,
        private NewMasterReader $masters,
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
        $monthly = $this->masters->idBySlug('reference_salary_periods', 'monthly');
        $seenUsers = [];

        $this->legacy->chunk('candidates', self::BATCH, function (array $rows) use ($context, $monthly, &$seenUsers): void {
            $maps = [
                'already' => $context->idMap->findMany(self::PROFILE, array_map(static fn (stdClass $row): int => (int) $row->id, $rows)),
                'users' => $context->idMap->findMany(AccountsImporter::USER, array_map(static fn (stdClass $row): int => (int) $row->user_id, $rows)),
                'religions' => $this->master($context, 'religions', $rows, 'religion_id'),
                'experienceLevels' => $this->master($context, 'experience_levels', $rows, 'experience_id'),
            ];

            $this->transactions->run(function () use ($rows, $context, $maps, $monthly, &$seenUsers): void {
                foreach ($rows as $row) {
                    $this->importRow($row, $context, $maps, $monthly, $seenUsers);
                }
            });
        });
    }

    /**
     * @param  array<string, array<int, int>>  $maps
     * @param  array<int, true>  $seenUsers
     */
    private function importRow(stdClass $row, ImportContext $context, array $maps, ?int $monthly, array &$seenUsers): void
    {
        $legacyId = (int) $row->id;
        $report = $context->report;
        $report->read(self::ENTITY);

        if (isset($maps['already'][$legacyId])) {
            $report->note(self::ENTITY, 'already-imported');

            return;
        }

        $userId = $maps['users'][(int) $row->user_id] ?? null;

        $reason = match (true) {
            $userId === null => 'user-not-imported',
            isset($seenUsers[$userId]) || $this->writer->count('profile', $userId) > 0 => 'duplicate-profile',
            default => null,
        };

        if ($reason !== null) {
            $report->skipped(self::ENTITY, $legacyId, $reason);

            return;
        }

        $seenUsers[$userId] = true;
        $location = $this->locations->resolve($this->text($row->region), $this->text($row->district), $this->text($row->subdistrict), $this->text($row->kelurahan), $this->text($row->postcode));
        [$salary, $period] = $this->salary($row->expected_salary, $monthly, $report);
        [$availability, $availableFrom] = $this->availability($row, $report);
        $createdAt = $this->time($row->created_at, $context);

        $columns = [
            'user_id' => $userId,
            'phone' => $this->phone($row->phone, $report),
            'whatsapp' => $this->phone($row->whatsapp_number, $report),
            'birth_date' => $this->birthDate($row->birth_date),
            'gender' => in_array($row->gender, ['male', 'female'], true) ? $row->gender : null,
            'marital_status' => $this->marital($row->marital_status, $report),
            'religion_id' => $row->religion_id === null ? null : ($maps['religions'][(int) $row->religion_id] ?? null),
            'nationality_id' => null,
            'expected_salary' => $salary,
            'expected_salary_period_id' => $period,
            'experience_level_id' => $row->experience_id === null ? null : ($maps['experienceLevels'][(int) $row->experience_id] ?? null),
            'availability' => $availability,
            'available_from' => $availableFrom,
            'province_id' => $location->provinceId,
            'city_id' => $location->cityId,
            'subdistrict_id' => $location->subdistrictId,
            'village_id' => $location->villageId,
            'postal_code_id' => $location->postalCodeId,
            'address_line' => LegacyText::limit(LegacyText::clean($row->address), 255),
            'headline' => LegacyText::limit(LegacyText::clean($row->title), 100),
            'summary' => LegacyText::limit(LegacyText::plain($row->bio), 2000),
            'is_discoverable' => false,
            'resume_discoverable' => false,
            'created_at' => $createdAt,
            'updated_at' => $row->updated_at === null ? $createdAt : $this->time($row->updated_at, $context),
        ];

        if ($context->write) {
            $context->idMap->remember(self::PROFILE, $legacyId, $this->writer->insert('profile', $columns), $context->clock->now());
        }

        $report->imported(self::ENTITY);
    }

    private function phone(mixed $value, ImportReport $report): ?string
    {
        $value = LegacyText::clean($value === null ? null : (string) $value);

        if ($value === null) {
            return null;
        }

        $cleaned = (string) preg_replace('/[\s().-]+/', '', $value);

        if (preg_match('/^\+?[0-9]{8,15}$/', $cleaned) === 1) {
            return $cleaned;
        }

        $report->note(self::ENTITY, 'phone-invalid');

        return null;
    }

    private function birthDate(mixed $value): ?string
    {
        return LegacyDates::day($value === null ? null : (string) $value)?->format('Y-m-d');
    }

    private function marital(mixed $value, ImportReport $report): ?string
    {
        $text = LegacyText::clean($value === null ? null : (string) $value);

        if ($text === null) {
            return null;
        }

        $mapped = self::MARITAL[NameNormalizer::normalize($text)] ?? null;

        if ($mapped === null) {
            $report->note(self::ENTITY, 'marital-status-unknown');
        }

        return $mapped;
    }

    /** @return array{0: int|null, 1: int|null} the amount and the monthly period, or nothing when there is no amount or no monthly period to go with it */
    private function salary(mixed $value, ?int $monthly, ImportReport $report): array
    {
        if ($value === null || (int) $value === 0) {
            return [null, null];
        }

        $amount = (int) $value;

        if ($monthly === null || $amount < 0) {
            $report->note(self::ENTITY, 'expected-salary-dropped');

            return [null, null];
        }

        return [$amount, $monthly];
    }

    /** @return array{0: string|null, 1: string|null} */
    private function availability(stdClass $row): array
    {
        $availability = match ($row->status) {
            'available', 'available_in' => 'open_to_work',
            'not_available' => 'not_looking',
            default => null,
        };

        if ($row->status !== 'available_in') {
            return [$availability, null];
        }

        $from = LegacyDates::day($row->available_in === null ? null : (string) $row->available_in);

        return [$availability, $from?->format('Y-m-d')];
    }

    /**
     * @param  list<stdClass>  $rows
     * @return array<int, int>
     */
    private function master(ImportContext $context, string $master, array $rows, string $column): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (stdClass $row): ?int => $row->{$column} === null ? null : (int) $row->{$column}, $rows))));

        return $context->idMap->findMany(MasterCatalog::entityFor($master), $ids);
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
