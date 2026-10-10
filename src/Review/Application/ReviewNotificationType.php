<?php

namespace Src\Review\Application;

/** The `type` keys of the in-app notifications of this context. The data holds `review_id` and `company_id` (and `reason` for a rejection), never the text. */
final class ReviewNotificationType
{
    public const string PUBLISHED = 'review.published';

    public const string REJECTED = 'review.rejected';

    public const string REMOVED = 'review.removed';

    public const string REPLY_PUBLISHED = 'review.reply_published';

    public const string RECEIVED = 'review.received';
}
