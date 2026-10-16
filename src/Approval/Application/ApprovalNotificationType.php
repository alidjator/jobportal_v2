<?php

namespace Src\Approval\Application;

/**
 * The `type` keys of the in-app notifications of this context, and the keys of the two preferences that switch their email. The server stores no text: clients write the message
 * from the key and the data (`approval_id`, `subject_type`, `subject_id`, `title`, and `reason` for a rejection or a return).
 */
final class ApprovalNotificationType
{
    /** An approval is waiting for you (it reached your step). */
    public const string REQUESTED = 'approval.requested';

    /** An approval is still waiting for you. */
    public const string REMINDER = 'approval.reminder';

    public const string GRANTED = 'approval.granted';

    public const string REJECTED = 'approval.rejected';

    public const string RETURNED = 'approval.returned';

    /** An approver left while their step waited: the requester is told that an admin names a replacement. */
    public const string APPROVER_VACATED = 'approval.approver_vacated';

    /** Email preference: approvals waiting for you and their reminders. */
    public const string TASK_PREFERENCE = 'approval_task';

    /** Email preference: what happened to the approvals you asked for. */
    public const string UPDATE_PREFERENCE = 'approval_update';
}
