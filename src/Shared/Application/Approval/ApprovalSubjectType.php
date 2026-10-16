<?php

namespace Src\Shared\Application\Approval;

/**
 * One kind of thing that goes through approval (`workforce.requisition`, later `recruitment.offer`): its code, what it is, the facts the conditions of a step may read
 * and the relative roles a step may name as approvers (`hiring_manager`: resolved by the subject's context when the subject is submitted).
 */
final readonly class ApprovalSubjectType
{
    /**
     * @param  list<ApprovalFact>  $facts
     * @param  list<string>  $relativeRoles
     */
    public function __construct(public string $code, public string $description, public array $facts = [], public array $relativeRoles = []) {}

    public function fact(string $key): ?ApprovalFact
    {
        foreach ($this->facts as $fact) {
            if ($fact->key === $key) {
                return $fact;
            }
        }

        return null;
    }
}
