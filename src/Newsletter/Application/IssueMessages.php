<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\Issue;

/** Builds what one subscriber receives: the issue body followed by a footer, in their language, with their own unsubscribe link. */
final readonly class IssueMessages
{
    private const array FOOTERS = [
        'id' => "---\n\nAnda menerima email ini karena berlangganan newsletter kami. [Berhenti berlangganan]({{url}})",
        'en' => "---\n\nYou receive this email because you subscribed to our newsletter. [Unsubscribe]({{url}})",
    ];

    public function __construct(private NewsletterLinks $links, private UnsubscribeTokens $tokens) {}

    public function for(Issue $issue, int $subscriberId, string $email, string $locale, string $subjectPrefix = ''): IssueMessage
    {
        $token = $this->tokens->forSubscriber($subscriberId);
        $url = $this->links->unsubscription($token);
        $footer = str_replace('{{url}}', $url, self::FOOTERS[$locale] ?? self::FOOTERS['id']);

        return new IssueMessage($email, $subjectPrefix.$issue->subject, rtrim($issue->body)."\n\n".$footer, $url, $this->links->oneClickUnsubscription($token));
    }
}
