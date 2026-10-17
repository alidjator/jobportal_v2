<?php

namespace Src\Recruitment\Application;

/** The in-app notification types and the email preference of shortlist reviews (`docs/design/phase-8-hiring-managers.md` section 3). */
final class ReviewNotificationType
{
    /** The reviewers are asked to review applications. */
    public const string REQUESTED = 'review.requested';

    /** The day before the due date, to the reviewers who still have applications to answer. */
    public const string REMINDER = 'review.reminder';

    /** After the due date, to the requester. */
    public const string OVERDUE = 'review.overdue';

    /** Every reviewer answered every active application: to the requester. */
    public const string COMPLETED = 'review.completed';

    /** The team preference for the review emails (on by default, company members). */
    public const string PREFERENCE = 'team_reviews';
}
