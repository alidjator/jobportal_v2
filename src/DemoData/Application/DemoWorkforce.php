<?php

namespace Src\DemoData\Application;

use DateTimeImmutable;
use LogicException;
use Src\Approval\Application\Flow\ApprovalFlows;
use Src\Approval\Application\Flow\FlowInput;
use Src\Approval\Application\Instance\ApprovalDelegations;
use Src\Approval\Application\Instance\MyApprovals;
use Src\Approval\Domain\ApproverKind;
use Src\Approval\Domain\ConditionOperator;
use Src\Approval\Domain\FlowApprover;
use Src\Approval\Domain\FlowCondition;
use Src\Approval\Domain\FlowStep;
use Src\Approval\Domain\StepMode;
use Src\Identity\Application\Users\ListAdminUsers;
use Src\Organization\Application\Group\GetMyGroup;
use Src\Organization\Application\Roles\SetGroupRole;
use Src\Organization\Application\Structure\GradeCatalog;
use Src\Organization\Application\Structure\ListPositions;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Application\Plan\CreatePlan;
use Src\Workforce\Application\Plan\FiscalCalendar;
use Src\Workforce\Application\Plan\ListPlans;
use Src\Workforce\Application\Plan\PlanLineInput;
use Src\Workforce\Application\Plan\PlanSide;
use Src\Workforce\Application\Plan\RevisePlan;
use Src\Workforce\Application\Plan\SubmitPlan;
use Src\Workforce\Application\Plan\UpdatePlan;
use Src\Workforce\Application\Requisition\AmendmentInput;
use Src\Workforce\Application\Requisition\AmendRequisition;
use Src\Workforce\Application\Requisition\CancelRequisition;
use Src\Workforce\Application\Requisition\CloseRequisition;
use Src\Workforce\Application\Requisition\CreateJobFromRequisition;
use Src\Workforce\Application\Requisition\CreateRequisition;
use Src\Workforce\Application\Requisition\RequisitionInput;
use Src\Workforce\Application\Requisition\SubmitRequisition;
use Src\Workforce\Application\WorkforceApprovalSubjects;
use Src\Workforce\Domain\PlanLine;
use Src\Workforce\Domain\PlanStatus;

/**
 * The demo workforce (`docs/design/phase-8-demo-data.md`): the approval flows of the group and of the verified tenant, a group role and a delegation, the manpower plans of both tenants (one in force, one
 * revision and one draft of the next year for the verified tenant, one in force and one group-side draft for the other) and the requisitions of {@see DemoWorkforceContent} taken through the approval paths
 * (approved, waiting at each step, rejected, returned, cancelled, closed, amended, with jobs opened). Everything is written through the use cases the API runs, as the people of the demo would do it. It needs
 * `demo:seed` and `demo:seed-structure`, and it does nothing when the group already has an approval flow, which is the first thing it writes; a plan drafted by hand for next year gets the demo lines.
 */
final readonly class DemoWorkforce
{
    private const int STEP_ONE = 1;

    public function __construct(
        private ListAdminUsers $users,
        private DemoActors $actors,
        private Clock $clock,
        private FiscalCalendar $calendar,
        private GetMyGroup $group,
        private ListPositions $listPositions,
        private GradeCatalog $grades,
        private ReferenceValues $references,
        private ApprovalFlows $flows,
        private SetGroupRole $setGroupRole,
        private ApprovalDelegations $delegations,
        private MyApprovals $approvals,
        private ListPlans $listPlans,
        private CreatePlan $createPlan,
        private UpdatePlan $updatePlan,
        private SubmitPlan $submitPlan,
        private RevisePlan $revisePlan,
        private CreateRequisition $createRequisition,
        private SubmitRequisition $submitRequisition,
        private AmendRequisition $amendRequisition,
        private CancelRequisition $cancelRequisition,
        private CloseRequisition $closeRequisition,
        private CreateJobFromRequisition $createJob,
    ) {}

    /**
     * @return array{flows: int, plans: int, requisitions: int, approvals: int, jobs: int} what this run wrote; all zero when the demo workforce is already there
     *
     * @throws StateConflict `demo-workforce-needs-structure` without the structure, `demo-structure-needs-demo-data` without the demo data
     */
    public function build(): array
    {
        $done = ['flows' => 0, 'plans' => 0, 'requisitions' => 0, 'approvals' => 0, 'jobs' => 0];
        $people = $this->people();
        $rina = $people[DemoWorkforceContent::RINA];

        // The flows are what this run writes first, so they tell whether it ran; a plan can be there already, drafted by hand.
        foreach ($this->flows->ofGroup($rina) as $view) {
            if ($view->flow !== null) {
                return $done;
            }
        }

        $positions = $this->positions($rina);
        $gradeIds = [];

        foreach ($this->grades->list($rina) as $grade) {
            $gradeIds[$grade->code] = (int) $grade->id;
        }

        $this->groupRole($rina, $people[DemoWorkforceContent::HENDRA]);
        $done['flows'] = $this->saveFlows($rina, $people);
        $this->delegations->create(
            $people[DemoWorkforceContent::HENDRA], $this->idOf($people[DemoWorkforceContent::SARI]), $this->clock->now(), $this->clock->now()->modify('+'.DemoWorkforceContent::DELEGATION_DAYS.' days'), null, DemoCast::USER_AGENT,
        );
        $this->plans($people, $positions, $done);
        $this->requisitions($people, $positions, $gradeIds, $done);

        return $done;
    }

    /** @return array<string, Actor> the people of the demo by their keys in {@see DemoWorkforceContent} */
    private function people(): array
    {
        $members = [];

        foreach (DemoCast::sinarMembers() as $member) {
            $members[$member['preset']->value] = $member['email'];
        }

        $emails = [
            DemoWorkforceContent::RINA => DemoCast::COMPANIES[DemoCast::SINAR]['admin_email'],
            DemoWorkforceContent::HENDRA => $members['hiring_manager'] ?? throw new LogicException('The demo has no hiring manager.'),
            DemoWorkforceContent::SARI => $members['recruiter'] ?? throw new LogicException('The demo has no recruiter.'),
            DemoWorkforceContent::MAYA => $members['interviewer'] ?? throw new LogicException('The demo has no interviewer.'),
            DemoWorkforceContent::AGUS => DemoCast::COMPANIES[DemoCast::ARUNIKA]['admin_email'],
        ];

        return array_map(fn (string $email): Actor => $this->actors->member($this->userId($email)), $emails);
    }

    private function userId(string $email): int
    {
        foreach ($this->users->execute($email, new PageRequest(1, 5))->items as $view) {
            if (mb_strtolower($view->user->email()) === mb_strtolower($email)) {
                return (int) $view->user->id;
            }
        }

        throw new StateConflict('The demo account :email does not exist. Run demo:seed first.', 'demo-structure-needs-demo-data', parameters: ['email' => $email]);
    }

    private function idOf(Actor $actor): int
    {
        return (int) $actor->id;
    }

    /**
     * The position ids of each tenant by its position code.
     *
     * @return array<string, array<string, int>> by tenant key
     *
     * @throws StateConflict `demo-workforce-needs-structure`
     */
    private function positions(Actor $rina): array
    {
        $byName = [];

        foreach ($this->group->execute($rina)->legalEntities as $view) {
            $byName[$view->entity->legalName] = (int) $view->entity->id;
        }

        $positions = [];

        foreach ([DemoCast::SINAR, DemoCast::ARUNIKA] as $tenant) {
            $entityId = $byName[DemoCast::COMPANIES[$tenant]['legal_name']] ?? throw new StateConflict('The demo group has no legal entity for the tenant.', 'demo-structure-needs-demo-data');

            foreach ($this->listPositions->execute($rina, $entityId, null) as $view) {
                $positions[$tenant][$view->position->code] = (int) $view->position->id;
            }

            if (! isset($positions[$tenant]['POS-DU'])) {
                throw new StateConflict('The demo structure does not exist. Run demo:seed-structure first.', 'demo-workforce-needs-structure');
            }
        }

        return $positions;
    }

    /** The hiring manager of the verified tenant also holds the group workforce role, so he can decide the steps of that role (the company admin is the requester of the plans). */
    private function groupRole(Actor $rina, Actor $hendra): void
    {
        $this->setGroupRole->execute($rina, null, $this->idOf($hendra), [GroupPermission::WorkforceView, GroupPermission::WorkforceManage], null, DemoCast::USER_AGENT);
    }

    /**
     * The group flow (one step: the holders of the group workforce role) applies to the tenant without a flow of its own; the verified tenant has its own.
     *
     * @param  array<string, Actor>  $people
     */
    private function saveFlows(Actor $rina, array $people): int
    {
        $role = static fn (): FlowApprover => new FlowApprover(ApproverKind::GroupRole, GroupPermission::WorkforceManage->value);
        $member = fn (string $key): FlowApprover => new FlowApprover(ApproverKind::Member, (string) $this->idOf($people[$key]));
        $manager = static fn (): FlowApprover => new FlowApprover(ApproverKind::Relative, 'hiring_manager');
        $when = static fn (string $fact, ConditionOperator $operator, int|bool|string $value): FlowCondition => new FlowCondition($fact, $operator, $value);
        $step = static fn (int $position, array $conditions, FlowApprover ...$approvers): FlowStep => new FlowStep($position, StepMode::Any, $conditions, array_values($approvers));
        $agent = DemoCast::USER_AGENT;
        $saved = 0;

        foreach ([WorkforceApprovalSubjects::PLAN, WorkforceApprovalSubjects::REQUISITION, WorkforceApprovalSubjects::REQUISITION_AMENDMENT] as $subject) {
            $this->flows->saveForGroup($rina, $subject, new FlowInput([$step(self::STEP_ONE, [], $role())]), null, $agent);
            $saved++;
        }

        $this->flows->saveForCompany($rina, WorkforceApprovalSubjects::PLAN, new FlowInput([
            $step(1, [], $member(DemoWorkforceContent::HENDRA)),
            $step(2, [$when('additions', ConditionOperator::Gte, 5)], $role()),
        ]), null, $agent);
        $this->flows->saveForCompany($rina, WorkforceApprovalSubjects::REQUISITION, new FlowInput([
            $step(1, [], $manager()),
            $step(2, [$when('hires', ConditionOperator::Gte, 3)], $member(DemoWorkforceContent::RINA)),
            $step(3, [$when('max_budget', ConditionOperator::Gte, 20_000_000)], $role()),
        ]), null, $agent);
        $this->flows->saveForCompany($rina, WorkforceApprovalSubjects::REQUISITION_AMENDMENT, new FlowInput([
            $step(1, [], $manager()),
            $step(2, [$when('hires', ConditionOperator::Gte, 3)], $member(DemoWorkforceContent::RINA)),
        ]), null, $agent);

        return $saved + 3;
    }

    /**
     * @param  array<string, Actor>  $people
     * @param  array<string, array<string, int>>  $positions
     * @param  array<string, int>  $done
     */
    private function plans(array $people, array $positions, array &$done): void
    {
        $agent = DemoCast::USER_AGENT;
        $rina = $people[DemoWorkforceContent::RINA];
        $hendra = $people[DemoWorkforceContent::HENDRA];
        $agus = $people[DemoWorkforceContent::AGUS];
        $companyIds = $this->companyIds($rina, $agus);
        $sinarYear = $this->calendar->yearOf($this->clock->now(), $companyIds[DemoCast::SINAR]);
        $arunikaYear = $this->calendar->yearOf($this->clock->now(), $companyIds[DemoCast::ARUNIKA]);
        $content = DemoWorkforceContent::plans();

        // The verified tenant: the plan of this year is approved in both its steps and then revised; the revision waits for the first step; next year's plan is a draft.
        $current = $this->createPlan->execute($rina, PlanSide::Company, null, $sinarYear, $this->lines($content[DemoCast::SINAR]['current'], $positions[DemoCast::SINAR]), null, $agent);
        $this->decide($hendra, $this->submitPlan->execute($rina, PlanSide::Company, (int) $current->plan->id, null, $agent)->plan->approvalId, 2, $done);
        $revision = $this->revisePlan->execute($rina, PlanSide::Company, (int) $current->plan->id, null, $agent);
        $this->updatePlan->execute($rina, PlanSide::Company, (int) $revision->plan->id, $this->lines($content[DemoCast::SINAR]['revision'], $positions[DemoCast::SINAR]), null, $agent);
        $this->submitPlan->execute($rina, PlanSide::Company, (int) $revision->plan->id, null, $agent);
        $this->draft($rina, PlanSide::Company, null, $sinarYear + 1, $this->lines($content[DemoCast::SINAR]['next'], $positions[DemoCast::SINAR]));

        // The other tenant: its plan goes through the group flow, decided by the group role of the company admin of the verified tenant; the group drafts next year's plan on its behalf.
        $plan = $this->createPlan->execute($agus, PlanSide::Company, null, $arunikaYear, $this->lines($content[DemoCast::ARUNIKA]['current'], $positions[DemoCast::ARUNIKA]), null, $agent);
        $this->decide($rina, $this->submitPlan->execute($agus, PlanSide::Company, (int) $plan->plan->id, null, $agent)->plan->approvalId, 1, $done);
        $this->draft($rina, PlanSide::Group, $plan->plan->legalEntityId, $arunikaYear + 1, $this->lines($content[DemoCast::ARUNIKA]['next'], $positions[DemoCast::ARUNIKA]));
        $done['plans'] = 5;
    }

    /**
     * The draft of a year: a plan someone already started by hand gets the demo lines, any other draft is made, and a plan that is no longer a draft stays as it is.
     *
     * @param  list<PlanLineInput>  $lines
     */
    private function draft(Actor $actor, PlanSide $side, ?int $legalEntityId, int $year, array $lines): void
    {
        foreach ($this->listPlans->execute($actor, $side, $legalEntityId, $year, null) as $view) {
            if ($view->plan->status === PlanStatus::Draft) {
                $this->updatePlan->execute($actor, $side, (int) $view->plan->id, $lines, null, DemoCast::USER_AGENT);
            }

            return;
        }

        $this->createPlan->execute($actor, $side, $legalEntityId, $year, $lines, null, DemoCast::USER_AGENT);
    }

    /** @return array<string, int> the company id of each tenant */
    private function companyIds(Actor $rina, Actor $agus): array
    {
        return [DemoCast::SINAR => (int) $rina->membership?->companyId, DemoCast::ARUNIKA => (int) $agus->membership?->companyId];
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int, 3: int}>  $rows
     * @param  array<string, int>  $positions
     * @return list<PlanLineInput>
     */
    private function lines(array $rows, array $positions): array
    {
        return array_map(function (array $row) use ($positions): PlanLineInput {
            [$code, $opening, $target, $perHire] = $row;
            $additions = $target - $opening;

            return new PlanLineInput($positions[$code] ?? throw new LogicException("The position {$code} of the demo plans is not in the demo structure."), $opening, $target, $this->months($additions), $additions * $perHire);
        }, $rows);
    }

    /**
     * The additions spread over the twelve fiscal months, one at a time from the second month on, every other month.
     *
     * @return list<int>
     */
    private function months(int $additions): array
    {
        $months = array_fill(0, PlanLine::MONTHS, 0);

        for ($left = $additions, $month = 1; $left > 0; $left--, $month = ($month + 2) % PlanLine::MONTHS) {
            $months[$month]++;
        }

        return $months;
    }

    /**
     * Approves an approval the given number of times by the same person: one for each step they hold.
     *
     * @param  array<string, int>  $done
     */
    private function decide(Actor $actor, ?int $approvalId, int $steps, array &$done): void
    {
        $id = $approvalId ?? throw new LogicException('The submission did not start an approval.');

        for ($step = 0; $step < $steps; $step++) {
            $this->approvals->approve($actor, $id, null, DemoCast::USER_AGENT);
            $done['approvals']++;
        }
    }

    /**
     * @param  array<string, Actor>  $people
     * @param  array<string, array<string, int>>  $positions
     * @param  array<string, int>  $gradeIds
     * @param  array<string, int>  $done
     */
    private function requisitions(array $people, array $positions, array $gradeIds, array &$done): void
    {
        $agent = DemoCast::USER_AGENT;
        $today = $this->clock->now();

        foreach (DemoWorkforceContent::requisitions() as $row) {
            $requester = $people[$row['by']];
            $companyId = (int) $requester->membership?->companyId;
            $input = new RequisitionInput(
                $row['type'], $positions[$row['tenant']][$row['position']] ?? throw new LogicException("The position {$row['position']} of the demo requisitions is not in the demo structure."),
                $row['grade'] === null ? null : ($gradeIds[$row['grade']] ?? throw new LogicException("The grade {$row['grade']} is not in the demo structure.")),
                $row['hires'], $this->jobType($row['job_type']), $row['contract_months'], $this->joinDate($today, $row['days'], $companyId), null, $row['salary'][0], $row['salary'][1],
                $row['justification'], $row['replaces'][0] ?? null, isset($row['replaces']) ? $today->modify('+'.$row['replaces'][1].' days') : null, $this->idOf($people[$row['manager']]),
            );
            $view = $this->createRequisition->execute($requester, $input, null, $agent);
            $id = (int) $view->requisition->id;
            $approvalId = null;
            $done['requisitions']++;

            foreach ($row['path'] as $action) {
                match ($action[0]) {
                    'submit' => $approvalId = $this->submitRequisition->execute($requester, $id, null, $agent)->requisition->approvalId,
                    'approve' => $this->decideOnce($people[(string) $action[1]], $approvalId, $done),
                    'reject' => $this->approvals->reject($people[(string) $action[1]], $approvalId ?? 0, (string) $action[2], null, $agent),
                    'return' => $this->approvals->returnForRevision($people[(string) $action[1]], $approvalId ?? 0, (string) $action[2], null, $agent),
                    'open_job' => $this->openJob($people[(string) $action[1]], $id, (int) $action[2], $done),
                    'close' => $this->closeRequisition->execute($people[(string) $action[1]], $id, (string) $action[2], null, $agent),
                    'cancel' => $this->cancelRequisition->execute($requester, $id, null, $agent),
                    'amend' => $approvalId = $this->amend($requester, $id, $action),
                    'approve_amendment' => $this->decideOnce($people[(string) $action[1]], $approvalId, $done),
                    default => throw new LogicException("The demo requisition action {$action[0]} is unknown."),
                };
            }
        }
    }

    /** @param  array<string, int>  $done */
    private function decideOnce(Actor $actor, ?int $approvalId, array &$done): void
    {
        $this->decide($actor, $approvalId, 1, $done);
    }

    /** @param  array<string, int>  $done */
    private function openJob(Actor $actor, int $requisitionId, int $vacancies, array &$done): void
    {
        $this->createJob->execute($actor, $requisitionId, null, $vacancies, null, DemoCast::USER_AGENT);
        $done['jobs']++;
    }

    /**
     * Asks for a change of an open requisition and returns the approval it started.
     *
     * @param  list<int|string|null>  $action  `amend`, the hires, the lowest and highest salary (null: unchanged) and the reason
     */
    private function amend(Actor $requester, int $id, array $action): int
    {
        $view = $this->amendRequisition->execute(
            $requester, $id, new AmendmentInput($action[1] === null ? null : (int) $action[1], $action[2] === null ? null : (int) $action[2], $action[3] === null ? null : (int) $action[3], (string) $action[4]), null, DemoCast::USER_AGENT,
        );
        $amendment = $view->amendments[array_key_last($view->amendments)] ?? throw new LogicException('The amendment was not recorded.');

        return $amendment->approvalId ?? throw new LogicException('The amendment did not start an approval.');
    }

    private function jobType(string $slug): int
    {
        return $this->references->idBySlug('job-types', $slug) ?? throw new StateConflict('The job type :slug does not exist.', 'demo-references-missing', parameters: ['slug' => $slug]);
    }

    /** The join date `$days` from today, never later than the last day of the fiscal year that holds today, so the plan line of that year is the quota. */
    private function joinDate(DateTimeImmutable $today, int $days, int $companyId): DateTimeImmutable
    {
        $year = $this->calendar->yearOf($today, $companyId);
        $end = $this->calendar->period($year, $companyId)[1];
        $date = $today->modify("+{$days} days");

        return $date > $end ? $end : $date;
    }
}
