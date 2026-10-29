<?php

namespace Src\Approval\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Approval\Domain\ApprovalInstance;
use Src\Approval\Domain\ApprovalInstanceRepository;
use Src\Approval\Domain\InstanceStatus;
use Src\Approval\Domain\InstanceStep;
use Src\Approval\Domain\Slot;
use Src\Approval\Domain\SlotKind;
use Src\Approval\Domain\SlotStatus;
use Src\Approval\Domain\StepMode;
use Src\Approval\Domain\StepStatus;
use stdClass;

final readonly class DatabaseApprovalInstanceRepository implements ApprovalInstanceRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $id): ?ApprovalInstance
    {
        $row = $this->connection->table('approval_instances')->where('id', $id)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function findForUpdate(int $id): ?ApprovalInstance
    {
        $row = $this->connection->table('approval_instances')->where('id', $id)->lockForUpdate()->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function pendingOfSubject(string $subjectType, int $subjectId): ?ApprovalInstance
    {
        $row = $this->connection->table('approval_instances')
            ->where('subject_type', $subjectType)->where('subject_id', $subjectId)->where('status', InstanceStatus::Pending->value)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    public function create(ApprovalInstance $instance): ApprovalInstance
    {
        $id = $this->connection->transaction(function () use ($instance): int {
            $now = $instance->createdAt->format(self::TIME_FORMAT);
            $id = (int) $this->connection->table('approval_instances')->insertGetId([
                'subject_type' => $instance->subjectType,
                'subject_id' => $instance->subjectId,
                'company_id' => $instance->companyId,
                'group_id' => $instance->groupId,
                'requester_id' => $instance->requesterId,
                'title' => $instance->title,
                'status' => $instance->status->value,
                'facts' => json_encode($instance->facts, JSON_THROW_ON_ERROR),
                'outcome_reason' => $instance->outcomeReason,
                'outcome_by_user_id' => $instance->outcomeByUserId,
                'completed_at' => $instance->completedAt?->format(self::TIME_FORMAT),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($instance->steps as $step) {
                $stepId = (int) $this->connection->table('approval_instance_steps')->insertGetId([
                    'instance_id' => $id,
                    'position' => $step->position,
                    'mode' => $step->mode->value,
                    'status' => $step->status->value,
                    'activated_at' => $step->activatedAt?->format(self::TIME_FORMAT),
                    'completed_at' => $step->completedAt?->format(self::TIME_FORMAT),
                ]);

                foreach ($step->slots as $slot) {
                    $this->connection->table('approval_instance_slots')->insert([
                        'step_id' => $stepId,
                        'kind' => $slot->kind->value,
                        'user_id' => $slot->userId,
                        'permission' => $slot->permission,
                        'status' => $slot->status->value,
                    ]);
                }
            }

            return $id;
        });

        return $this->find($id) ?? throw new \LogicException("Approval {$id} was just created.");
    }

    public function save(ApprovalInstance $instance): void
    {
        $this->connection->transaction(function () use ($instance): void {
            $this->connection->table('approval_instances')->where('id', $instance->id)->update([
                'status' => $instance->status->value,
                'outcome_reason' => $instance->outcomeReason,
                'outcome_by_user_id' => $instance->outcomeByUserId,
                'completed_at' => $instance->completedAt?->format(self::TIME_FORMAT),
                'updated_at' => ($instance->completedAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(self::TIME_FORMAT),
            ]);

            foreach ($instance->steps as $step) {
                $this->connection->table('approval_instance_steps')->where('id', $step->id)->update([
                    'status' => $step->status->value,
                    'activated_at' => $step->activatedAt?->format(self::TIME_FORMAT),
                    'completed_at' => $step->completedAt?->format(self::TIME_FORMAT),
                    'reminded_at' => $step->remindedAt?->format(self::TIME_FORMAT),
                ]);

                foreach ($step->slots as $slot) {
                    $this->connection->table('approval_instance_slots')->where('id', $slot->id)->update([
                        'user_id' => $slot->userId,
                        'status' => $slot->status->value,
                        'decided_by_user_id' => $slot->decidedByUserId,
                        'on_behalf_of_user_id' => $slot->onBehalfOfUserId,
                        'decided_at' => $slot->decidedAt?->format(self::TIME_FORMAT),
                    ]);
                }
            }
        });
    }

    public function pendingFor(array $userIds, ?int $groupId, array $permissions): array
    {
        $query = $this->connection->table('approval_instances as instance')
            ->join('approval_instance_steps as step', 'step.instance_id', '=', 'instance.id')
            ->join('approval_instance_slots as slot', 'slot.step_id', '=', 'step.id')
            ->where('instance.status', InstanceStatus::Pending->value)
            ->where('step.status', StepStatus::Pending->value)
            ->where('slot.status', SlotStatus::Open->value)
            ->where(function ($match) use ($userIds, $groupId, $permissions): void {
                $match->where(fn ($users) => $users->where('slot.kind', SlotKind::User->value)->whereIn('slot.user_id', $userIds === [] ? [0] : $userIds));

                if ($groupId !== null && $permissions !== []) {
                    $match->orWhere(fn ($roles) => $roles->where('slot.kind', SlotKind::Role->value)->where('instance.group_id', $groupId)->whereIn('slot.permission', $permissions));
                }
            });

        return $query->orderByDesc('instance.id')->distinct()->pluck('instance.id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    public function pendingIds(): array
    {
        return $this->connection->table('approval_instances')->where('status', InstanceStatus::Pending->value)->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    public function ofRequester(int $userId): array
    {
        return $this->connection->table('approval_instances')->where('requester_id', $userId)->orderByDesc('id')->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    public function involves(int $userId): bool
    {
        return $this->connection->table('approval_instances')->where('requester_id', $userId)->exists()
            || $this->connection->table('approval_instance_slots')
                ->where(fn ($match) => $match->where('user_id', $userId)->orWhere('decided_by_user_id', $userId)->orWhere('on_behalf_of_user_id', $userId))
                ->exists();
    }

    public function pendingWithSlotOf(int $userId): array
    {
        return $this->connection->table('approval_instances as instance')
            ->join('approval_instance_steps as step', 'step.instance_id', '=', 'instance.id')
            ->join('approval_instance_slots as slot', 'slot.step_id', '=', 'step.id')
            ->where('instance.status', InstanceStatus::Pending->value)
            ->where('slot.user_id', $userId)
            ->whereIn('slot.status', [SlotStatus::Open->value, SlotStatus::Vacant->value])
            ->distinct()->pluck('instance.id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    private function hydrate(stdClass $row): ApprovalInstance
    {
        $id = (int) $row->id;
        $time = static fn (mixed $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        $slots = [];

        foreach ($this->connection->table('approval_instance_slots as slot')->join('approval_instance_steps as step', 'step.id', '=', 'slot.step_id')
            ->where('step.instance_id', $id)->orderBy('slot.id')->get(['slot.*']) as $slot) {
            $slots[(int) $slot->step_id][] = new Slot(
                (int) $slot->id,
                SlotKind::from((string) $slot->kind),
                $slot->user_id === null ? null : (int) $slot->user_id,
                $slot->permission === null ? null : (string) $slot->permission,
                SlotStatus::from((string) $slot->status),
                $slot->decided_by_user_id === null ? null : (int) $slot->decided_by_user_id,
                $slot->on_behalf_of_user_id === null ? null : (int) $slot->on_behalf_of_user_id,
                $time($slot->decided_at),
            );
        }

        $steps = $this->connection->table('approval_instance_steps')->where('instance_id', $id)->orderBy('position')->get()->map(
            static fn (stdClass $step): InstanceStep => new InstanceStep(
                (int) $step->id, (int) $step->position, StepMode::from((string) $step->mode), StepStatus::from((string) $step->status), $slots[(int) $step->id] ?? [],
                $time($step->activated_at), $time($step->completed_at), $time($step->reminded_at),
            ),
        )->all();

        return new ApprovalInstance(
            $id, (string) $row->subject_type, (int) $row->subject_id, (int) $row->company_id, (int) $row->group_id, (int) $row->requester_id, (string) $row->title,
            json_decode((string) $row->facts, true, 512, JSON_THROW_ON_ERROR), InstanceStatus::from((string) $row->status), $steps, $time($row->created_at) ?? new DateTimeImmutable,
            $row->outcome_reason === null ? null : (string) $row->outcome_reason, $row->outcome_by_user_id === null ? null : (int) $row->outcome_by_user_id, $time($row->completed_at),
        );
    }
}
