<?php

namespace Src\Approval\Application\Instance;

use Src\Approval\Application\ApprovalEmailSender;
use Src\Approval\Application\ApprovalEmailTemplates;
use Src\Approval\Application\ApprovalNotificationType;
use Src\Approval\Domain\ApprovalInstance;
use Src\Approval\Domain\SlotKind;
use Src\Approval\Domain\SlotStatus;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;
use Src\Shared\Application\Organization\GroupPermissions;

/**
 * Tells the people in an approval what they need to know. The approvers of the step being decided hear that it reached them and are reminded while it waits (preference
 * `approval_task`); the requester hears the outcome, and that an approver left (preference `approval_update`). The in-app notification is always written; the email only when the
 * person's preference allows it and the account is active. The delegates of an approver are not notified: they find the approval in their list.
 */
final readonly class ApprovalNotifier
{
    public function __construct(
        private GroupPermissions $groups,
        private CompanyMemberAccounts $accounts,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private ApprovalEmailSender $emails,
    ) {}

    /** @return list<int> the accounts who may fill an open place of the step being decided, without the requester */
    public function approversOf(ApprovalInstance $instance): array
    {
        $ids = [];

        foreach ($instance->currentStep()->slots ?? [] as $slot) {
            if ($slot->status !== SlotStatus::Open) {
                continue;
            }

            if ($slot->kind === SlotKind::User && $slot->userId !== null) {
                $ids[$slot->userId] = $slot->userId;
            } elseif ($slot->kind === SlotKind::Role && $slot->permission !== null) {
                foreach ($this->groups->holders($instance->groupId, $slot->permission) as $id) {
                    $ids[$id] = $id;
                }
            }
        }

        unset($ids[$instance->requesterId]);

        return array_values($ids);
    }

    /** The approvers of the step being decided: it reached them (`requested`) or it still waits (`reminder`). Returns how many were told. */
    public function approvers(ApprovalInstance $instance, bool $reminder): int
    {
        $ids = $this->approversOf($instance);

        $this->tell(
            $ids, $reminder ? ApprovalNotificationType::REMINDER : ApprovalNotificationType::REQUESTED, ApprovalNotificationType::TASK_PREFERENCE,
            $reminder ? ApprovalEmailTemplates::REMINDER : ApprovalEmailTemplates::REQUESTED, $instance, null,
        );

        return count($ids);
    }

    /** The requester: `granted`, `rejected`, `returned` or `approver_vacated`. */
    public function requester(ApprovalInstance $instance, string $change): void
    {
        [$type, $template] = match ($change) {
            'granted' => [ApprovalNotificationType::GRANTED, ApprovalEmailTemplates::GRANTED],
            'rejected' => [ApprovalNotificationType::REJECTED, ApprovalEmailTemplates::REJECTED],
            'returned' => [ApprovalNotificationType::RETURNED, ApprovalEmailTemplates::RETURNED],
            default => [ApprovalNotificationType::APPROVER_VACATED, ApprovalEmailTemplates::APPROVER_VACATED],
        };

        $this->tell([$instance->requesterId], $type, ApprovalNotificationType::UPDATE_PREFERENCE, $template, $instance, $instance->outcomeReason);
    }

    /** @param  list<int>  $userIds */
    private function tell(array $userIds, string $type, string $preference, string $template, ApprovalInstance $instance, ?string $reason): void
    {
        if ($userIds === []) {
            return;
        }

        $this->notifier->send($userIds, $type, [
            'approval_id' => $instance->id, 'subject_type' => $instance->subjectType, 'subject_id' => $instance->subjectId, 'title' => $instance->title, 'reason' => $reason,
        ]);

        $wantEmail = $this->preferences->enabledAmong($userIds, $preference, NotificationChannel::Email);
        $people = $wantEmail === [] ? [] : $this->accounts->summaries(array_values(array_unique([...$wantEmail, $instance->requesterId])));
        $requester = $people[$instance->requesterId]->name ?? '';

        foreach ($wantEmail as $userId) {
            $person = $people[$userId] ?? null;

            if ($person !== null && $person->status === 'active') {
                $this->emails->send($person->email, $person->locale, $template, ['name' => $person->name, 'title' => $instance->title, 'requester' => $requester, 'reason' => $reason ?? '']);
            }
        }
    }
}
