<?php

namespace Src\Organization\Application\Structure;

use Src\Organization\Domain\Events\StructureItemChanged;
use Src\Organization\Domain\Grade;
use Src\Organization\Domain\GradeRepository;
use Src\Organization\Domain\ItemStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The grades of a group (code, name, rank): any group member reads them, `group.structure.manage` changes them. A grade a position still uses is not deactivated. */
final readonly class GradeCatalog
{
    public function __construct(
        private StructureAccess $access,
        private GradeRepository $grades,
        private ChangeDating $dating,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @return list<Grade>
     *
     * @throws AccessDenied `not-a-group-member` or `group-suspended`
     */
    public function list(Actor $actor): array
    {
        return $this->grades->ofGroup((int) $this->access->read($actor)->group->id);
    }

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws StateConflict `grade-code-taken`
     */
    public function create(Actor $actor, string $code, string $name, int $rank, ?string $ipAddress, ?string $userAgent): Grade
    {
        $group = (int) $this->access->manage($actor)->group->id;

        if ($this->grades->codeTaken($group, $code)) {
            throw new StateConflict('The group already has a grade with this code.', 'grade-code-taken');
        }

        $grade = $this->grades->create(new Grade(null, $group, $code, $name, $rank));
        $this->record($group, (int) $grade->id, 'created', $actor, $ipAddress, $userAgent);

        return $grade;
    }

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the grade is missing or belongs to another group
     */
    public function update(Actor $actor, int $id, ?string $name, ?int $rank, ?string $ipAddress, ?string $userAgent): Grade
    {
        $group = (int) $this->access->manage($actor)->group->id;
        $current = $this->own($group, $id);
        $grade = $current->rename($name ?? $current->name, $rank ?? $current->rank);

        return $this->store($grade, $group, 'updated', $actor, $ipAddress, $userAgent);
    }

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the grade is missing or belongs to another group
     * @throws StateConflict `grade-in-use`, `already-inactive` or `already-active`
     */
    public function changeStatus(Actor $actor, int $id, bool $activate, ?string $ipAddress, ?string $userAgent): Grade
    {
        $group = (int) $this->access->manage($actor)->group->id;
        $grade = $this->own($group, $id);

        if ($grade->isActive() === $activate) {
            throw new StateConflict($activate ? 'The grade is already active.' : 'The grade is already inactive.', $activate ? 'already-active' : 'already-inactive');
        }

        if (! $activate && $this->grades->inUse($id, $this->dating->today())) {
            throw new StateConflict('An active position still uses this grade.', 'grade-in-use');
        }

        return $this->store($grade->withStatus($activate ? ItemStatus::Active : ItemStatus::Inactive), $group, $activate ? 'reactivated' : 'deactivated', $actor, $ipAddress, $userAgent);
    }

    private function own(int $group, int $id): Grade
    {
        $grade = $this->grades->find($id);

        if ($grade === null || $grade->groupId !== $group) {
            throw EntityNotFound::of('Grade', $id);
        }

        return $grade;
    }

    private function store(Grade $grade, int $group, string $change, Actor $actor, ?string $ipAddress, ?string $userAgent): Grade
    {
        $this->grades->save($grade);
        $this->record($group, (int) $grade->id, $change, $actor, $ipAddress, $userAgent);

        return $grade;
    }

    private function record(int $group, int $id, string $change, Actor $actor, ?string $ipAddress, ?string $userAgent): void
    {
        $this->events->dispatch([new StructureItemChanged($group, 'grade', $id, $change, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
