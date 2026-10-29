<?php

namespace Src\DemoData\Application;

use LogicException;
use Src\Identity\Application\Users\ListAdminUsers;
use Src\Organization\Application\Group\CreateLegalEntity;
use Src\Organization\Application\Group\GetMyGroup;
use Src\Organization\Application\Group\LegalEntityInput;
use Src\Organization\Application\Structure\CostCentreCatalog;
use Src\Organization\Application\Structure\CreatePosition;
use Src\Organization\Application\Structure\CreateUnit;
use Src\Organization\Application\Structure\GradeCatalog;
use Src\Organization\Application\Structure\ListPositions;
use Src\Organization\Application\Structure\ListUnits;
use Src\Organization\Application\Structure\PositionInput;
use Src\Organization\Application\Structure\UnitInput;
use Src\Organization\Application\Structure\UnitTypeCatalog;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The organisation structure of the demo group (`docs/design/phase-8-demo-data.md`): the unit types and grades of the group, four more legal entities beside the two companies' own, and for each of
 * the six entities its cost centres, its unit tree and its positions ({@see DemoStructureContent}). It is written through the use cases the API runs, as the group admin of the verified company does,
 * and it skips what already exists by code, so it can run after `demo:seed` on a database that has the demo group, and again.
 */
final readonly class DemoStructure
{
    public function __construct(
        private GetMyGroup $group,
        private CreateLegalEntity $createLegalEntity,
        private UnitTypeCatalog $unitTypes,
        private GradeCatalog $grades,
        private CostCentreCatalog $costCentres,
        private ListUnits $listUnits,
        private CreateUnit $createUnit,
        private ListPositions $listPositions,
        private CreatePosition $createPosition,
        private ListAdminUsers $users,
        private DemoActors $actors,
    ) {}

    /**
     * The group admin of the verified demo company, who holds every group permission.
     *
     * @throws StateConflict `demo-structure-needs-demo-data` when the demo accounts do not exist
     */
    public function demoGroupAdmin(): Actor
    {
        $email = DemoCast::COMPANIES[DemoCast::SINAR]['admin_email'];

        foreach ($this->users->execute($email, new PageRequest(1, 5))->items as $view) {
            if (mb_strtolower($view->user->email()) === mb_strtolower($email)) {
                return $this->actors->member((int) $view->user->id);
            }
        }

        throw new StateConflict('The demo group admin does not exist. Run demo:seed first.', 'demo-structure-needs-demo-data');
    }

    /**
     * @return array{legal_entities: int, unit_types: int, grades: int, cost_centres: int, units: int, positions: int} what this run created
     *
     * @throws StateConflict `demo-structure-needs-demo-data` when the group lacks the two companies' legal entities
     */
    public function build(Actor $actor): array
    {
        $created = ['legal_entities' => 0, 'unit_types' => 0, 'grades' => 0, 'cost_centres' => 0, 'units' => 0, 'positions' => 0];
        $entityIds = $this->entities($actor, $created);
        $typeIds = $this->unitTypeIds($actor, $created);
        $gradeIds = $this->gradeIds($actor, $created);

        foreach (DemoStructureContent::entities() as $key => $content) {
            $entityId = $entityIds[$key];
            $centreIds = $this->costCentreIds($actor, $entityId, $content['cost_centres'], $created);
            $unitIds = $this->units($actor, $entityId, $content['units'], $typeIds, $centreIds, $created);
            $this->positions($actor, $entityId, $content['positions'], $unitIds, $gradeIds, $created);
        }

        return $created;
    }

    /**
     * The legal entity id per key: the two companies' entities are found by their legal names, the other four are created when missing.
     *
     * @param  array<string, int>  $created
     * @return array<string, int>
     */
    private function entities(Actor $actor, array &$created): array
    {
        $byName = [];

        foreach ($this->group->execute($actor)->legalEntities as $view) {
            $byName[$view->entity->legalName] = (int) $view->entity->id;
        }

        $ids = [];

        foreach ([DemoCast::SINAR, DemoCast::ARUNIKA] as $key) {
            $name = DemoCast::COMPANIES[$key]['legal_name'];
            $ids[$key] = $byName[$name] ?? throw new StateConflict('The legal entity :name does not exist. Run demo:seed first.', 'demo-structure-needs-demo-data', parameters: ['name' => $name]);
        }

        foreach (DemoStructureContent::EXTRA_ENTITIES as $key => $extra) {
            if (! isset($byName[$extra['legal_name']])) {
                $view = $this->createLegalEntity->execute($actor, new LegalEntityInput($extra['legal_name'], $extra['legal_form']), null, DemoCast::USER_AGENT);
                $byName[$extra['legal_name']] = (int) $view->entity->id;
                $created['legal_entities']++;
            }

            $ids[$key] = $byName[$extra['legal_name']];
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $created
     * @return array<string, int> unit type id by code
     */
    private function unitTypeIds(Actor $actor, array &$created): array
    {
        $ids = [];

        foreach ($this->unitTypes->list($actor) as $type) {
            $ids[$type->code] = (int) $type->id;
        }

        foreach (DemoStructureContent::UNIT_TYPES as [$code, $name, $order]) {
            if (! isset($ids[$code])) {
                $ids[$code] = (int) $this->unitTypes->create($actor, $code, $name, $order, null, DemoCast::USER_AGENT)->id;
                $created['unit_types']++;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $created
     * @return array<string, int> grade id by code
     */
    private function gradeIds(Actor $actor, array &$created): array
    {
        $ids = [];

        foreach ($this->grades->list($actor) as $grade) {
            $ids[$grade->code] = (int) $grade->id;
        }

        foreach (DemoStructureContent::GRADES as [$code, $name, $rank]) {
            if (! isset($ids[$code])) {
                $ids[$code] = (int) $this->grades->create($actor, $code, $name, $rank, null, DemoCast::USER_AGENT)->id;
                $created['grades']++;
            }
        }

        return $ids;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $centres
     * @param  array<string, int>  $created
     * @return array<string, int> cost centre id by code
     */
    private function costCentreIds(Actor $actor, int $entityId, array $centres, array &$created): array
    {
        $ids = [];

        foreach ($this->costCentres->list($actor, $entityId) as $centre) {
            $ids[$centre->code] = (int) $centre->id;
        }

        foreach ($centres as [$code, $name]) {
            if (! isset($ids[$code])) {
                $ids[$code] = (int) $this->costCentres->create($actor, $entityId, $code, $name, null, DemoCast::USER_AGENT)->id;
                $created['cost_centres']++;
            }
        }

        return $ids;
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string|null, 3: string, 4: string|null}>  $units
     * @param  array<string, int>  $typeIds
     * @param  array<string, int>  $centreIds
     * @param  array<string, int>  $created
     * @return array<string, int> unit id by code
     */
    private function units(Actor $actor, int $entityId, array $units, array $typeIds, array $centreIds, array &$created): array
    {
        $ids = [];

        foreach ($this->listUnits->execute($actor, $entityId, null) as $view) {
            $ids[$view->unit->code] = (int) $view->unit->id;
        }

        foreach ($units as [$code, $name, $parent, $type, $centre]) {
            if (isset($ids[$code])) {
                continue;
            }

            $input = new UnitInput(
                $name,
                $parent === null ? null : ($ids[$parent] ?? throw new LogicException("The parent unit {$parent} of {$code} comes after it in the demo content.")),
                $typeIds[$type] ?? throw new LogicException("The unit type {$type} of {$code} is not in the demo content."),
                $centre === null ? null : ($centreIds[$centre] ?? throw new LogicException("The cost centre {$centre} of {$code} is not in the demo content.")),
            );
            $ids[$code] = (int) $this->createUnit->execute($actor, $entityId, $code, $input, null, null, null, DemoCast::USER_AGENT)->unit->id;
            $created['units']++;
        }

        return $ids;
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string|null}>  $positions
     * @param  array<string, int>  $unitIds
     * @param  array<string, int>  $gradeIds
     * @param  array<string, int>  $created
     */
    private function positions(Actor $actor, int $entityId, array $positions, array $unitIds, array $gradeIds, array &$created): void
    {
        $ids = [];

        foreach ($this->listPositions->execute($actor, $entityId, null) as $view) {
            $ids[$view->position->code] = (int) $view->position->id;
        }

        foreach ($positions as [$code, $title, $unit, $lowest, $highest, $reportsTo]) {
            if (isset($ids[$code])) {
                continue;
            }

            $input = new PositionInput(
                $title,
                $unitIds[$unit] ?? throw new LogicException("The unit {$unit} of position {$code} is not in the demo content."),
                $gradeIds[$lowest] ?? throw new LogicException("The grade {$lowest} of position {$code} is not in the demo content."),
                $gradeIds[$highest] ?? throw new LogicException("The grade {$highest} of position {$code} is not in the demo content."),
                $reportsTo === null ? null : ($ids[$reportsTo] ?? throw new LogicException("The position {$reportsTo} that {$code} reports to comes after it in the demo content.")),
                null,
            );
            $ids[$code] = (int) $this->createPosition->execute($actor, $entityId, $code, $input, null, null, null, DemoCast::USER_AGENT)->position->id;
            $created['positions']++;
        }
    }
}
