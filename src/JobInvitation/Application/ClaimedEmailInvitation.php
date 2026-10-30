<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitationChannel;

/** What a person gets back when they claim an invitation: the job to apply to, the channel and the CV the company holds for them (which they may take into their own resumes). */
final readonly class ClaimedEmailInvitation
{
    public function __construct(public int $jobId, public ?string $jobTitle, public ?string $companyName, public EmailInvitationChannel $channel, public bool $hasCv, public ?string $cvName) {}
}
