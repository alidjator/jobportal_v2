<?php

namespace Src\Identity\Application\Auth;

use Closure;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\VerificationCodeRepository;
use Src\Identity\Domain\VerificationPurpose;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * Emails a password reset code to the accounts that have no password (the legacy one was not bcrypt, so the import left it empty) and no social sign-in, so they can get in.
 * Accounts whose address is suppressed are counted and skipped; so are accounts that were ever issued a reset code (this run, an earlier run or their own request), which makes
 * a repeated run send nothing twice. A dry run counts only. The scan walks the whole pool, so the report is complete even when the run is limited to `$limit` emails.
 */
final readonly class SendImportedPasswordResets
{
    private const int CHUNK = 200;

    public function __construct(
        private UserRepository $users,
        private VerificationCodeRepository $codes,
        private VerificationCodeIssuer $issuer,
        private VerificationCodeSender $sender,
        private EmailSuppressions $suppressions,
        private TransactionManager $transactions,
    ) {}

    /** @param  Closure(): void|null  $afterSend called after each email, to pace a large run */
    public function execute(bool $write, int $limit, ?Closure $afterSend = null): ImportedResetReport
    {
        $withoutPassword = $suppressed = $alreadyIssued = $sendable = $sent = 0;
        $cursor = 0;

        while (($chunk = $this->users->findWithoutPassword($cursor, self::CHUNK)) !== []) {
            $reasons = $this->suppressions->reasons(array_map(fn (User $user): string => $user->email(), $chunk));

            foreach ($chunk as $user) {
                $cursor = (int) $user->id;
                $withoutPassword++;

                if (isset($reasons[mb_strtolower($user->email())])) {
                    $suppressed++;

                    continue;
                }

                if ($this->codes->lastIssuedAt(AccountScope::User, $cursor, VerificationPurpose::PasswordReset) !== null) {
                    $alreadyIssued++;

                    continue;
                }

                $sendable++;

                if ($write && $sent < $limit && $this->send($user)) {
                    $sent++;
                    $afterSend?->__invoke();
                }
            }
        }

        return new ImportedResetReport($withoutPassword, $suppressed, $alreadyIssued, $sendable, $sent);
    }

    private function send(User $user): bool
    {
        try {
            $issued = $this->transactions->run(fn () => $this->issuer->issueForUser((int) $user->id, VerificationPurpose::PasswordReset));
        } catch (TooManyAttempts) {
            return false;
        }

        $this->sender->sendPasswordReset($user->email(), $user->name(), $user->locale(), $issued->code, $issued->expiresAt);

        return true;
    }
}
