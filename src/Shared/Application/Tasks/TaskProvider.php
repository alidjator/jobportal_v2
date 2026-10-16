<?php

namespace Src\Shared\Application\Tasks;

/**
 * One item of the task list of a company member ("Perlu tindakan", `docs/design/phase-8-recruiter-workday.md` section 1), counted by the context that owns the data. A context registers
 * its providers in `TaskProviders` from its service provider, so the Dashboard context knows no other context.
 */
interface TaskProvider
{
    /** The key of the item in the response; unique. */
    public function key(): string;

    /** Where the item stands in the list (lower first). */
    public function order(): int;

    /** The company permission the member needs for the item (`applications.view`), or null when any member may have it. */
    public function permission(): ?string;

    /** The query that opens the matching list (a path with its filters, for the company app). */
    public function filter(TaskScope $scope): string;

    /** How many things wait for the member. Counts only what the member may see. */
    public function count(TaskScope $scope): int;
}
