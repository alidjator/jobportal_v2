<?php

namespace Src\Workforce\Domain;

interface PlanRepository
{
    /** The plan with its lines; `$lock` holds its row until the transaction ends. */
    public function find(int $id, bool $lock = false): ?Plan;

    /** Stores a new plan with its lines and returns it with its id. */
    public function create(Plan $plan): Plan;

    /** Stores the status, approval, decision and dates of the plan (not its lines). */
    public function save(Plan $plan): void;

    /** Stores the lines of the plan in place of the ones it had. */
    public function replaceLines(int $planId, array $lines): void;

    /**
     * The plans of these legal entities with their lines, newest fiscal year and version first.
     *
     * @param  list<int>  $legalEntityIds
     * @return list<Plan>
     */
    public function ofEntities(array $legalEntityIds, ?int $fiscalYear, ?PlanStatus $status): array;

    /**
     * Every version of the entity's plan for the year, by version.
     *
     * @return list<Plan>
     */
    public function versionsOf(int $legalEntityId, int $fiscalYear): array;
}
