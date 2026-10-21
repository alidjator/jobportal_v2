<?php

namespace Src\DemoData\Application;

use LogicException;
use Src\ReferenceData\Application\Locations\ListLocations;
use Src\ReferenceData\Application\Locations\LocationCatalog;
use Src\ReferenceData\Application\Locations\LocationLevel;
use Src\ReferenceData\Application\Masters\ListMasterItems;
use Src\ReferenceData\Application\Masters\MasterCatalog;
use Src\ReferenceData\Application\Masters\MasterItem;
use Src\ReferenceData\Application\Masters\MasterQuery;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The reference data the demo content points at, read through the reference use cases like any client: the first active items in the curated order, and the
 * first city of the first province. Whatever `db:seed` put there is used, so the run needs the reference seeders first.
 */
final readonly class DemoReferences
{
    public function __construct(
        private ListMasterItems $masters,
        private ListLocations $locations,
    ) {}

    /**
     * The ids of the first `$count` active items of a master (fewer when the master has fewer).
     *
     * @return list<int>
     *
     * @throws StateConflict `demo-references-missing` when the master has no active item
     */
    public function ids(string $master, int $count = 1): array
    {
        $definition = MasterCatalog::find($master) ?? throw new LogicException("Unknown master {$master}.");
        $result = $this->masters->execute($definition, new MasterQuery(true, 'id', page: new PageRequest(1, max($count, 1))));
        $items = $result instanceof Page ? $result->items : $result;
        $ids = array_map(static fn (MasterItem $item): int => $item->id, array_slice($items, 0, $count));

        if ($ids === []) {
            throw self::missing($master);
        }

        return $ids;
    }

    /** @throws StateConflict `demo-references-missing` */
    public function id(string $master): int
    {
        return $this->ids($master)[0];
    }

    /**
     * The first province and its first city.
     *
     * @return array{provinceId: int, cityId: int}
     *
     * @throws StateConflict `demo-references-missing`
     */
    public function place(): array
    {
        $province = $this->locations->execute($this->level('provinces'), null)[0] ?? throw self::missing('provinces');
        $city = $this->locations->execute($this->level('cities'), $province->id)[0] ?? throw self::missing('cities');

        return ['provinceId' => $province->id, 'cityId' => $city->id];
    }

    private function level(string $key): LocationLevel
    {
        return LocationCatalog::find($key) ?? throw new LogicException("Unknown location level {$key}.");
    }

    private static function missing(string $master): StateConflict
    {
        return new StateConflict('The reference data has no active :master: run db:seed first.', 'demo-references-missing', parameters: ['master' => $master]);
    }
}
