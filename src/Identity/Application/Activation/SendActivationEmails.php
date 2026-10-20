<?php

namespace Src\Identity\Application\Activation;

use Closure;
use DateTimeImmutable;
use Src\Identity\Application\IdentitySettings;
use Src\Identity\Domain\AccountStatus;
use Src\Identity\Domain\ActivationEmailRepository;
use Src\Identity\Domain\ActivationKind;
use Src\Identity\Domain\ActivationVariant;
use Src\Identity\Domain\User;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\UserType;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Throwable;

/**
 * Emails the imported accounts that have not signed in yet how to get in (`docs/design/phase-8-activation.md` section 3). A run takes the accounts of one audience (company owners or
 * job seekers), skips the suppressed, the deactivated and those that already got this kind of email, sends verified addresses first and the unverified ones only when no verified one is left,
 * and logs each email. A real run is refused while the support address is empty, before the date set for job seekers, and while the previous batch bounced or drew complaints above the limits.
 * A dry run only counts and names the guards that would stop it.
 */
final readonly class SendActivationEmails
{
    private const int CHUNK = 200;

    /** Registered by Platform (a platform-wide contact); read here by its key. */
    private const string SUPPORT_EMAIL = 'platform.support_email';

    public function __construct(
        private UserRepository $users,
        private ActivationEmailRepository $log,
        private ActivationEmailSender $sender,
        private ActivationLinks $links,
        private EmailSuppressions $suppressions,
        private Settings $settings,
        private Clock $clock,
        private CompanyMembershipResolver $memberships,
        private CompanyDirectory $companies,
    ) {}

    /** @param  Closure(): void|null  $afterSend called after each email, to pace a large run */
    public function execute(ActivationRun $run, ?Closure $afterSend = null): ActivationReport
    {
        $now = $this->clock->now();
        $staffTest = $run->emails !== null;
        $kind = $staffTest ? ActivationKind::Manual : ($run->reminders ? ActivationKind::Reminder : ActivationKind::Activation);
        $batch = $this->batchLabel($run, $kind, $now);
        $blockedBy = $this->guards($run, $staffTest, $now);

        if ($run->write && $blockedBy !== []) {
            throw $blockedBy[0];
        }

        $counts = ['eligible' => 0, 'suppressed' => 0, 'deactivated' => 0, 'alreadySent' => 0, 'notDue' => 0];
        $verified = $unverified = [];
        $cursor = 0;

        while (($chunk = $this->users->findImportedAwaitingSignIn($run->audience, $cursor, self::CHUNK, $run->emails)) !== []) {
            $ids = array_map(static fn (User $user): int => (int) $user->id, $chunk);
            $cursor = max($ids);
            $reasons = $this->suppressions->reasons(array_map(static fn (User $user): string => $user->email(), $chunk));
            $earlier = $kind === ActivationKind::Manual ? [] : $this->log->sentAt($kind, $ids);
            $activated = $kind === ActivationKind::Reminder ? $this->log->sentAt(ActivationKind::Activation, $ids) : [];

            foreach ($chunk as $user) {
                $counts['eligible']++;
                $id = (int) $user->id;

                if (isset($reasons[mb_strtolower($user->email())])) {
                    $counts['suppressed']++;
                } elseif ($user->status() !== AccountStatus::Active) {
                    $counts['deactivated']++;
                } elseif (isset($earlier[$id])) {
                    $counts['alreadySent']++;
                } elseif ($kind === ActivationKind::Reminder && ! $this->reminderDue($activated[$id] ?? null, $now)) {
                    $counts['notDue']++;
                } elseif ($user->isEmailVerified()) {
                    $verified[] = $user;
                } else {
                    $unverified[] = $user;
                }
            }
        }

        // Unverified addresses have their turn only when no verified one is left.
        $candidates = $verified !== [] ? $verified : $unverified;
        $heldBack = $verified !== [] ? count($unverified) : 0;
        $sent = 0;

        if ($run->write) {
            foreach ($candidates as $user) {
                if ($sent >= $run->limit) {
                    break;
                }

                if ($this->send($user, $kind, $batch, $now)) {
                    $sent++;
                    $afterSend?->__invoke();
                }
            }
        }

        return new ActivationReport(
            $batch,
            $counts['eligible'],
            $counts['suppressed'],
            $counts['deactivated'],
            $counts['alreadySent'],
            $counts['notDue'],
            $heldBack,
            count($candidates),
            $sent,
            $run->audience === UserType::CompanyMember ? $this->users->countImported($run->audience, true) : null,
            array_map(static fn (BusinessRuleViolation $violation): string => $violation->getMessage(), $blockedBy),
        );
    }

    private function send(User $user, ActivationKind $kind, string $batch, DateTimeImmutable $now): bool
    {
        $id = (int) $user->id;

        if (! $this->log->reserve($id, $kind, ActivationVariant::of($user), $batch, $now)) {
            return false;
        }

        try {
            $this->sender->send($user, $kind, ActivationVariant::of($user), $this->companyName($user));
        } catch (Throwable $e) {
            $this->log->release($id, $kind);

            throw $e;
        }

        return true;
    }

    private function companyName(User $user): string
    {
        if (! $user->isCompanyMember()) {
            return '';
        }

        $membership = $this->memberships->forUser((int) $user->id);

        return $membership === null ? '' : ($this->companies->summaries([$membership->companyId])[$membership->companyId]->name ?? '');
    }

    private function batchLabel(ActivationRun $run, ActivationKind $kind, DateTimeImmutable $now): string
    {
        $prefix = match (true) {
            $kind === ActivationKind::Manual => 'staff-test',
            $run->pilot => 'pilot',
            $kind === ActivationKind::Reminder => 'reminder',
            default => 'batch',
        };

        return "{$prefix}-{$run->audience->value}-{$now->format('Ymd-His')}";
    }

    private function reminderDue(?DateTimeImmutable $activatedAt, DateTimeImmutable $now): bool
    {
        return $activatedAt !== null && $activatedAt <= $now->modify('-'.$this->settings->integer(IdentitySettings::ACTIVATION_REMINDER_DAYS).' days');
    }

    /** @return list<BusinessRuleViolation> the guards that stop a real run */
    private function guards(ActivationRun $run, bool $staffTest, DateTimeImmutable $now): array
    {
        $blocked = [];

        if ($this->settings->text(self::SUPPORT_EMAIL) === '') {
            $blocked[] = new BusinessRuleViolation('Set the setting platform.support_email before sending activation emails.', 'activation-support-contact-missing');
        }

        if ($this->links->activationUrl($run->audience) === null || ($run->audience === UserType::JobSeeker && $this->links->signInUrl() === null)) {
            $blocked[] = new BusinessRuleViolation('Set the activation and sign-in page URLs (see .env.example) before sending activation emails.', 'activation-url-missing');
        }

        if ($staffTest) {
            return $blocked;
        }

        if ($run->audience === UserType::JobSeeker) {
            $notBefore = $this->settings->text(IdentitySettings::ACTIVATION_JOB_SEEKERS_NOT_BEFORE);

            if ($notBefore === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $notBefore) || $now->format('Y-m-d') < $notBefore) {
                $blocked[] = new BusinessRuleViolation('Job seekers are not emailed before the date in identity.activation_job_seekers_not_before'.($notBefore === '' ? ' (empty: set it)' : " ({$notBefore})").'.', 'activation-not-yet-open');
            }
        }

        $limit = $this->batchLimit($run->audience);

        if ($limit !== null) {
            $blocked[] = $limit;
        }

        return $blocked;
    }

    /** The previous batch of this audience bounced or drew complaints above the limits of the settings. */
    private function batchLimit(UserType $audience): ?BusinessRuleViolation
    {
        $previous = $this->log->latestBatch(ActivationVariant::ofAudience($audience));

        if ($previous === null || $previous['userIds'] === []) {
            return null;
        }

        $bounceLimit = $this->percent(IdentitySettings::ACTIVATION_BOUNCE_LIMIT_PERCENT);
        $complaintLimit = $this->percent(IdentitySettings::ACTIVATION_COMPLAINT_LIMIT_PERCENT);

        if ($bounceLimit === null || $complaintLimit === null) {
            return new BusinessRuleViolation('The settings identity.activation_bounce_limit_percent and identity.activation_complaint_limit_percent must be numbers.', 'activation-limit-invalid');
        }

        $emails = array_map(static fn (User $user): string => $user->email(), array_values($this->users->findByIds($previous['userIds'])));
        $reasons = array_count_values($this->suppressions->reasons($emails));
        $total = count($previous['userIds']);
        $bounces = 100 * ($reasons['hard_bounce'] ?? 0) / $total;
        $complaints = 100 * ($reasons['complaint'] ?? 0) / $total;

        if ($bounces > $bounceLimit || $complaints > $complaintLimit) {
            return new BusinessRuleViolation(sprintf(
                'The previous batch (%s) had %.2f%% bounces (limit %s%%) and %.2f%% complaints (limit %s%%). Look at it before sending more.',
                $previous['batch'], $bounces, $bounceLimit, $complaints, $complaintLimit,
            ), 'activation-batch-limit-exceeded');
        }

        return null;
    }

    private function percent(string $key): ?float
    {
        $value = $this->settings->text($key);

        return is_numeric($value) && (float) $value >= 0 ? (float) $value : null;
    }
}
