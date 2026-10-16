<?php

namespace Src\Shared\Application\Approval;

/**
 * A subject that goes to approval, as the context that owns it describes it: what it is, who asks, the facts the flow's conditions read, and who the relative roles of the subject type
 * name right now (`hiring_manager` => the account ids). `$companyId` is the tenant the subject belongs to; the flow that applies is the tenant's own, else its group's.
 */
final readonly class ApprovalSubmission
{
    /**
     * @param  array<string, int|bool|string>  $facts  one value for every fact of the subject type
     * @param  array<string, list<int>>  $relativeApprovers  relative role => account ids
     */
    public function __construct(
        public string $subjectType,
        public int $subjectId,
        public int $companyId,
        public int $requesterId,
        public string $title,
        public array $facts,
        public array $relativeApprovers = [],
    ) {}
}
