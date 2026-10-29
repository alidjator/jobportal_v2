<?php

namespace Src\Approval\Domain;

interface ApprovalInstanceRepository
{
    public function find(int $id): ?ApprovalInstance;

    /** The instance read for a decision: the row is locked until the transaction ends, so two decisions on one approval never run side by side. */
    public function findForUpdate(int $id): ?ApprovalInstance;

    /** The running approval of the subject, if any. */
    public function pendingOfSubject(string $subjectType, int $subjectId): ?ApprovalInstance;

    /** Stores a new approval with its steps and slots (the ids are filled in the returned copy). */
    public function create(ApprovalInstance $instance): ApprovalInstance;

    /** Stores the state of the approval, its steps and its slots (not their shape). */
    public function save(ApprovalInstance $instance): void;

    /**
     * The ids of the running approvals whose current step has an open slot one of these accounts may fill, or a role slot of the group with one of the permissions, newest first.
     *
     * @param  list<int>  $userIds
     * @param  list<string>  $permissions
     * @return list<int>
     */
    public function pendingFor(array $userIds, ?int $groupId, array $permissions): array;

    /** @return list<int> the ids of every running approval, oldest first */
    public function pendingIds(): array;

    /** @return list<int> the ids of the approvals the account asked for, newest first */
    public function ofRequester(int $userId): array;

    /** Whether the account asked for an approval or holds (or held) a place in one, as itself or as the one who decided or stood in for another. */
    public function involves(int $userId): bool;

    /** @return list<int> the ids of the running approvals with a user slot of this account that is not approved yet */
    public function pendingWithSlotOf(int $userId): array;
}
