<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitationChannel;

/** What the person sees when they open the link: the company, the job and the note, never the name, the email or the CV the company holds. `open` says whether the link still works. */
final readonly class PublicEmailInvitation
{
    public function __construct(
        public ?string $companyName,
        public ?int $jobId,
        public ?string $jobTitle,
        public ?string $note,
        public EmailInvitationChannel $channel,
        public bool $open,
        public bool $jobLive,
    ) {}
}
