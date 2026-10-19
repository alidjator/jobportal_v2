<?php

namespace Src\Company\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Company\Domain\CompanyLocation;
use Src\Company\Domain\CompanyLocationRepository;
use Src\Company\Domain\LocationStatus;
use Src\Company\Domain\LocationType;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Address;
use Src\Shared\Domain\Coordinates;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use stdClass;

final readonly class DatabaseCompanyLocationRepository implements CompanyLocationRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function find(int $companyId, int $locationId): ?CompanyLocation
    {
        $row = $this->connection->table('company_locations')->where('company_id', $companyId)->where('id', $locationId)->first();

        return $row === null ? null : $this->toLocation($row);
    }

    public function main(int $companyId): ?CompanyLocation
    {
        $row = $this->connection->table('company_locations')->where('company_id', $companyId)->where('is_main', true)->first();

        return $row === null ? null : $this->toLocation($row);
    }

    public function forCompany(int $companyId, ?LocationStatus $status = null): array
    {
        return $this->connection->table('company_locations')
            ->where('company_id', $companyId)
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            // Active first (the status words sort that way), the main location first among them, then by name.
            ->orderBy('status')
            ->orderByDesc('is_main')
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $row): CompanyLocation => $this->toLocation($row))
            ->all();
    }

    public function count(int $companyId): int
    {
        return $this->connection->table('company_locations')->where('company_id', $companyId)->count();
    }

    public function nameTaken(int $companyId, string $name, ?int $exceptId = null): bool
    {
        return $this->connection->table('company_locations')
            ->where('company_id', $companyId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public function add(CompanyLocation $location): CompanyLocation
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        try {
            $id = $this->connection->table('company_locations')->insertGetId([...$this->columns($location), 'company_id' => $location->companyId, 'created_at' => $now, 'updated_at' => $now]);
        } catch (UniqueConstraintViolationException) {
            // Two requests took the same name at the same moment; the unique index decided which one wins.
            throw new BusinessRuleViolation('Another location of this company already has this name.', 'location-name-taken');
        }

        return $location->with(['id' => (int) $id, 'createdAt' => new DateTimeImmutable($now, new DateTimeZone('UTC'))]);
    }

    public function save(CompanyLocation $location): void
    {
        try {
            $this->connection->table('company_locations')->where('company_id', $location->companyId)->where('id', $location->id)->update([
                ...$this->columns($location),
                'updated_at' => $this->clock->now()->format(self::TIME_FORMAT),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleViolation('Another location of this company already has this name.', 'location-name-taken');
        }
    }

    public function delete(int $companyId, int $locationId): void
    {
        $this->connection->table('company_locations')->where('company_id', $companyId)->where('id', $locationId)->delete();
    }

    public function moveMain(int $companyId, int $locationId): void
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        // The old mark goes first: the partial unique index allows one main location at a time.
        $this->connection->table('company_locations')->where('company_id', $companyId)->where('is_main', true)->update(['is_main' => false, 'updated_at' => $now]);
        $this->connection->table('company_locations')->where('company_id', $companyId)->where('id', $locationId)->update(['is_main' => true, 'updated_at' => $now]);
    }

    /** @return array<string, mixed> */
    private function columns(CompanyLocation $location): array
    {
        return [
            'name' => $location->name,
            'type' => $location->type->value,
            'is_main' => $location->isMain,
            'status' => $location->status->value,
            'address_line' => $location->address->line,
            'province_id' => $location->address->provinceId,
            'city_id' => $location->address->cityId,
            'subdistrict_id' => $location->address->subdistrictId,
            'village_id' => $location->address->villageId,
            'postal_code_id' => $location->address->postalCodeId,
            'phone' => $location->phone,
            'latitude' => $location->coordinates?->latitude,
            'longitude' => $location->coordinates?->longitude,
            'deactivated_at' => $location->deactivatedAt?->format(self::TIME_FORMAT),
        ];
    }

    private function toLocation(stdClass $row): CompanyLocation
    {
        return new CompanyLocation(
            (int) $row->id,
            (int) $row->company_id,
            (string) $row->name,
            LocationType::from((string) $row->type),
            (bool) $row->is_main,
            LocationStatus::from((string) $row->status),
            new Address(
                $row->address_line === null ? null : (string) $row->address_line,
                $this->nullableInt($row->province_id),
                $this->nullableInt($row->city_id),
                $this->nullableInt($row->subdistrict_id),
                $this->nullableInt($row->village_id),
                $this->nullableInt($row->postal_code_id),
            ),
            $row->phone === null ? null : (string) $row->phone,
            $row->latitude === null || $row->longitude === null ? null : new Coordinates((float) $row->latitude, (float) $row->longitude),
            $row->deactivated_at === null ? null : new DateTimeImmutable((string) $row->deactivated_at, new DateTimeZone('UTC')),
            $row->created_at === null ? null : new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
