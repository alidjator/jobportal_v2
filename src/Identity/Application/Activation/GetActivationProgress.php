<?php

namespace Src\Identity\Application\Activation;

use DateTimeImmutable;
use DateTimeInterface;
use Src\Identity\Domain\ActivationEmailRepository;
use Src\Identity\Domain\ActivationVariant;
use Src\Identity\Domain\UserRepository;
use Src\Identity\Domain\UserType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Email\EmailSuppressions;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * How far the activation of the imported accounts has come (`docs/design/phase-8-activation.md` section 10), live from the accounts, the log of activation emails and the suppression list: how
 * many accounts there are and why some cannot be reached, what was sent, how many signed in, the 30, 60 and 90 day marks counted from the first activation email of the audience (no target), and
 * every batch. For the company audience (the owners) it also names the companies not signed in yet, so they can be called. Nothing is stored.
 */
final readonly class GetActivationProgress
{
    private const array MILESTONES = [30, 60, 90];

    /** The most companies named in the list of the ones not signed in. */
    private const int COMPANY_LIST_LIMIT = 1000;

    public function __construct(
        private UserRepository $users,
        private ActivationEmailRepository $log,
        private EmailSuppressions $suppressions,
        private Clock $clock,
        private CompanyMembershipResolver $memberships,
        private CompanyDirectory $companies,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws AccessDenied when the actor is neither an admin nor the system (the console)
     */
    public function execute(Actor $actor, UserType $audience): array
    {
        if (! $actor->isAdmin() && ! $actor->isSystem()) {
            throw new AccessDenied('Only an administrator can read the activation report.');
        }

        $now = $this->clock->now();
        $variants = ActivationVariant::ofAudience($audience);
        $counts = $this->users->importedCounts($audience);
        $deliverable = $this->users->importedDeliverableEmails($audience);
        $suppressedAmongDeliverable = count($this->suppressions->reasons($deliverable));
        $summary = $this->log->summary($variants);
        $sentReasons = array_count_values($this->suppressions->reasons($this->emailsOf($summary['userIds'])));
        $signedInOfSent = count($this->users->signedInAmong($summary['userIds']));
        $first = $summary['firstSentAt'];

        $report = [
            'audience' => $audience->value,
            'imported' => $counts['imported'],
            'deactivated' => $counts['deactivated'],
            'unverified' => $counts['unverified'],
            'suppressed' => $suppressedAmongDeliverable,
            'eligible' => count($deliverable) - $suppressedAmongDeliverable,
            'activation' => [
                'first_sent_at' => $first?->format(DateTimeInterface::ATOM),
                'sent' => $summary['sent'],
                'reminders_sent' => $summary['reminders'],
                'bounced' => $sentReasons['hard_bounce'] ?? 0,
                'complaints' => $sentReasons['complaint'] ?? 0,
            ],
            'signed_in' => [
                'count' => $counts['signedIn'],
                'percent_of_sent' => self::percent($signedInOfSent, $summary['sent']),
                'percent_of_imported' => self::percent($counts['signedIn'], $counts['imported']),
            ],
            'milestones' => $first === null ? [] : array_map(function (int $day) use ($first, $now, $audience, $summary): array {
                $date = $first->modify("+{$day} days");
                $reached = $now >= $date;
                $signedIn = $reached ? $this->users->countImportedSignedInBy($audience, $date) : null;

                return [
                    'day' => $day,
                    'date' => $date->format(DateTimeInterface::ATOM),
                    'reached' => $reached,
                    'signed_in' => $signedIn,
                    'percent_of_sent' => $signedIn === null ? null : self::percent($signedIn, $summary['sent']),
                ];
            }, self::MILESTONES),
            'batches' => array_map(fn (array $batch): array => $this->batch($batch), $this->log->batches($variants)),
        ];

        if ($audience === UserType::CompanyMember) {
            $report['companies_not_signed_in'] = $this->companiesNotSignedIn();
        }

        return $report;
    }

    /**
     * The share of imported accounts that signed in, per audience, for the card of the admin dashboard; null while an audience has no imported account.
     *
     * @return array{job_seekers: float|null, companies: float|null}
     */
    public function signInRates(): array
    {
        $seekers = $this->users->importedCounts(UserType::JobSeeker);
        $companies = $this->users->importedCounts(UserType::CompanyMember);

        return [
            'job_seekers' => self::percent($seekers['signedIn'], $seekers['imported']),
            'companies' => self::percent($companies['signedIn'], $companies['imported']),
        ];
    }

    /**
     * @param  array{batch: string, sentAt: DateTimeImmutable, userIds: list<int>}  $batch
     * @return array<string, mixed>
     */
    private function batch(array $batch): array
    {
        $reasons = array_count_values($this->suppressions->reasons($this->emailsOf($batch['userIds'])));

        return [
            'batch' => $batch['batch'],
            'sent_at' => $batch['sentAt']->format(DateTimeInterface::ATOM),
            'sent' => count($batch['userIds']),
            'bounced' => $reasons['hard_bounce'] ?? 0,
            'complaints' => $reasons['complaint'] ?? 0,
            'signed_in' => count($this->users->signedInAmong($batch['userIds'])),
        ];
    }

    /**
     * @param  list<int>  $userIds
     * @return list<string>
     */
    private function emailsOf(array $userIds): array
    {
        $emails = [];

        foreach (array_chunk($userIds, 1000) as $chunk) {
            foreach ($this->users->findByIds($chunk) as $user) {
                $emails[] = $user->email();
            }
        }

        return $emails;
    }

    /** @return list<array{company_id: int, name: string}> by name */
    private function companiesNotSignedIn(): array
    {
        $companyIds = [];
        $after = 0;

        while (count($companyIds) < self::COMPANY_LIST_LIMIT && ($owners = $this->users->findImportedAwaitingSignIn(UserType::CompanyMember, $after, 200)) !== []) {
            foreach ($owners as $owner) {
                $membership = $this->memberships->forUser((int) $owner->id);

                if ($membership !== null) {
                    $companyIds[$membership->companyId] = $membership->companyId;
                }
            }

            $after = (int) $owners[array_key_last($owners)]->id;
        }

        $list = [];

        foreach ($this->companies->summaries(array_values($companyIds)) as $companyId => $company) {
            $list[] = ['company_id' => (int) $companyId, 'name' => $company->name];
        }

        usort($list, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $list;
    }

    private static function percent(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round(100 * $part / $whole, 1);
    }
}
