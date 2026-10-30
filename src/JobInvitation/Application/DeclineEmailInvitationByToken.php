<?php

namespace Src\JobInvitation\Application;

use Src\JobInvitation\Domain\EmailInvitationRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The person refuses with the link, signed in or not ("Bukan saya / tolak"): the CV, the name and the email are deleted at once and the team sees only the status. */
final readonly class DeclineEmailInvitationByToken
{
    public function __construct(
        private EmailInvitationAccess $access,
        private EmailInvitationRepository $invitations,
        private FileStorage $files,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws StateConflict `invitation-closed`
     */
    public function execute(string $token): void
    {
        $this->access->assertEnabled();

        $hash = InvitationToken::of($token)->hash();

        $path = $this->transactions->run(function () use ($hash, $token): ?string {
            $locked = $this->invitations->findByTokenHash($hash, forUpdate: true) ?? throw EntityNotFound::of('Invitation', substr($token, 0, 4));
            $this->invitations->save($locked->declined($this->clock->now()));

            return $locked->cvPath;
        });

        if ($path !== null) {
            $this->files->delete($path);
        }
    }
}
