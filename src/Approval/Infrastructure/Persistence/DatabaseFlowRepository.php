<?php

namespace Src\Approval\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Approval\Domain\ApproverKind;
use Src\Approval\Domain\ConditionOperator;
use Src\Approval\Domain\Flow;
use Src\Approval\Domain\FlowApprover;
use Src\Approval\Domain\FlowCondition;
use Src\Approval\Domain\FlowOwner;
use Src\Approval\Domain\FlowRepository;
use Src\Approval\Domain\FlowStep;
use Src\Approval\Domain\OwnerType;
use Src\Approval\Domain\StepMode;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseFlowRepository implements FlowRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function find(FlowOwner $owner, string $subjectType): ?Flow
    {
        $row = $this->query($owner)->where('subject_type', $subjectType)->first();

        return $row === null ? null : $this->load([$row])[0];
    }

    public function ofOwner(FlowOwner $owner): array
    {
        return $this->load($this->query($owner)->orderBy('subject_type')->get()->all());
    }

    public function save(Flow $flow): Flow
    {
        $this->connection->transaction(function () use ($flow): void {
            $now = $this->clock->now()->format(self::TIME_FORMAT);
            $existing = $this->query($flow->owner)->where('subject_type', $flow->subjectType)->first();

            if ($existing === null) {
                $flowId = (int) $this->connection->table('approval_flows')->insertGetId([
                    'owner_type' => $flow->owner->type->value, 'owner_id' => $flow->owner->id, 'subject_type' => $flow->subjectType, 'created_at' => $now, 'updated_at' => $now,
                ]);
            } else {
                $flowId = (int) $existing->id;
                $this->connection->table('approval_flows')->where('id', $flowId)->update(['updated_at' => $now]);
                $this->connection->table('approval_flow_steps')->where('flow_id', $flowId)->delete();
            }

            foreach ($flow->steps as $step) {
                $stepId = (int) $this->connection->table('approval_flow_steps')->insertGetId([
                    'flow_id' => $flowId,
                    'position' => $step->position,
                    'mode' => $step->mode->value,
                    'conditions' => json_encode(array_map(static fn (FlowCondition $condition): array => $condition->toArray(), $step->conditions), JSON_THROW_ON_ERROR),
                ]);

                $this->connection->table('approval_flow_step_approvers')->insert(array_map(static fn (FlowApprover $approver): array => [
                    'step_id' => $stepId, 'kind' => $approver->kind->value, 'value' => $approver->value,
                ], $step->approvers));
            }
        });

        return $this->find($flow->owner, $flow->subjectType) ?? throw new \LogicException('The flow was just saved.');
    }

    public function remove(FlowOwner $owner, string $subjectType): bool
    {
        return $this->query($owner)->where('subject_type', $subjectType)->delete() > 0;
    }

    private function query(FlowOwner $owner): Builder
    {
        return $this->connection->table('approval_flows')->where('owner_type', $owner->type->value)->where('owner_id', $owner->id);
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<Flow>
     */
    private function load(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $flowIds = array_map(static fn (stdClass $row): int => (int) $row->id, $rows);
        $steps = $this->connection->table('approval_flow_steps')->whereIn('flow_id', $flowIds)->orderBy('position')->get();
        $approvers = [];

        foreach ($this->connection->table('approval_flow_step_approvers')->whereIn('step_id', $steps->pluck('id')->all())->orderBy('id')->get() as $row) {
            $approvers[(int) $row->step_id][] = new FlowApprover(ApproverKind::from((string) $row->kind), (string) $row->value);
        }

        $stepsByFlow = [];

        foreach ($steps as $row) {
            $conditions = json_decode((string) $row->conditions, true, 512, JSON_THROW_ON_ERROR);
            $stepsByFlow[(int) $row->flow_id][] = new FlowStep(
                (int) $row->position,
                StepMode::from((string) $row->mode),
                array_map(static fn (array $condition): FlowCondition => new FlowCondition((string) $condition['fact'], ConditionOperator::from((string) $condition['operator']), $condition['value']), $conditions),
                $approvers[(int) $row->id] ?? [],
            );
        }

        return array_map(static fn (stdClass $row): Flow => new Flow(
            (int) $row->id, new FlowOwner(OwnerType::from((string) $row->owner_type), (int) $row->owner_id), (string) $row->subject_type, $stepsByFlow[(int) $row->id] ?? [],
        ), $rows);
    }
}
