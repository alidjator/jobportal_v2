<?php

namespace Src\Newsletter\Application;

/** One issue as one subscriber gets it: the subject, the Markdown body with the unsubscribe footer, and the two unsubscribe addresses (page and one-click). */
final readonly class IssueMessage
{
    public function __construct(
        public string $email,
        public string $subject,
        public string $markdown,
        public string $unsubscribeUrl,
        public string $oneClickUrl,
    ) {}
}
