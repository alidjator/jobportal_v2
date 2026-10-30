<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitation;
use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;

/**
 * Daily: an invitation still open 30 days after it was sent becomes `expired` and loses its CV, its name and its email (the row stays for the counts); the team's copy of a CV the claiming
 * person never chose is deleted 30 days after the claim. A refused or withdrawn invitation lost its data at once.
 */
final readonly class PurgeEmailInvitations
{
    private const int BATCH = 200;

    public function __construct(private EmailInvitationRepository $invitations, private FileStorage $files, private TransactionManager $transactions, private Clock $clock) {}

    /** @return int how many invitations were changed */
    public function execute(): int
    {
        $now = $this->clock->now();
        $before = $now->modify('-'.EmailInvitation::VALID_DAYS.' days');
        $count = 0;

        do {
            $due = $this->invitations->openSentBefore($before, self::BATCH);

            foreach ($due as $invitation) {
                $this->transactions->run(function () use ($invitation, $now): void {
                    $this->invitations->save($invitation->expired($now));
                });
                $this->deleteCv($invitation);
                $count++;
            }
        } while (count($due) === self::BATCH);

        do {
            $due = $this->invitations->claimedWithCvBefore($before, self::BATCH);

            foreach ($due as $invitation) {
                $this->transactions->run(function () use ($invitation, $now): void {
                    $this->invitations->save($invitation->withoutCv($now));
                });
                $this->deleteCv($invitation);
                $count++;
            }
        } while (count($due) === self::BATCH);

        return $count;
    }

    private function deleteCv(EmailInvitation $invitation): void
    {
        if ($invitation->cvPath !== null) {
            $this->files->delete($invitation->cvPath);
        }
    }
}
