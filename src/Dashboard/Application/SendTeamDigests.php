<?php

namespace Src\Dashboard\Application;

use DateTimeZone;
use Illuminate\Support\Carbon;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Accounts\MemberAccountSummary;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Calendar\CalendarEvent;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;
use Src\Shared\Application\Recruitment\ApplicationCounts;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The daily team digest (`docs/design/phase-8-recruiter-workday.md` section 3): to every active member who wants it, the task list, the applications of the last 24 hours on the
 * jobs assigned to them and their interviews of the day. One email per member and company, and none when every count is zero. A member whose digest cannot be built is skipped, so one
 * member never stops the others.
 */
final readonly class SendTeamDigests
{
    public const string PREFERENCE = 'team_daily_digest';

    private const string ZONE = 'Asia/Jakarta';

    private const int BATCH = 100;

    public function __construct(
        private CompanyDirectory $companies,
        private CompanyTeam $team,
        private CompanyMembershipResolver $memberships,
        private CompanyMemberAccounts $accounts,
        private NotificationPreferences $preferences,
        private GetCompanyTasks $tasks,
        private GetCompanyCalendar $calendar,
        private JobDirectory $jobs,
        private ApplicationCounts $applications,
        private TeamDigestSender $sender,
        private Clock $clock,
        private ?string $companyAppUrl,
    ) {}

    /** @return int how many digests were sent */
    public function execute(): int
    {
        $sent = 0;
        $afterId = 0;

        while (($companyIds = $this->companies->idsAfter($afterId, self::BATCH)) !== []) {
            $afterId = max($companyIds);

            foreach ($this->companies->summaries($companyIds) as $companyId => $company) {
                if ($this->companies->isActive($companyId)) {
                    $sent += $this->sendForCompany($companyId, $company->name);
                }
            }
        }

        return $sent;
    }

    private function sendForCompany(int $companyId, string $companyName): int
    {
        $userIds = array_keys($this->team->roles($companyId));
        $wanted = $this->preferences->enabledAmong($userIds, self::PREFERENCE, NotificationChannel::Email);

        if ($wanted === []) {
            return 0;
        }

        $sent = 0;

        foreach ($this->accounts->summaries($wanted) as $person) {
            if ($person->status === 'active' && $this->sendToMember($person, $companyId, $companyName)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function sendToMember(MemberAccountSummary $person, int $companyId, string $companyName): bool
    {
        $membership = $this->memberships->forUser($person->userId);

        if ($membership === null || $membership->companyId !== $companyId) {
            return false;
        }

        $actor = Actor::companyMember($person->userId)->withMembership($membership);

        try {
            $lines = $this->lines($actor, $person, $companyId);
        } catch (AccessDenied|BusinessRuleViolation) {
            return false;
        }

        if ($lines === []) {
            return false;
        }

        $now = Carbon::instance($this->clock->now())->setTimezone(self::ZONE)->locale($person->locale);

        $this->sender->send($person->email, $person->locale, [
            'name' => $person->name,
            'company' => $companyName,
            'date' => $now->translatedFormat('j F Y'),
            'summary' => implode("\n\n", $lines),
        ]);

        return true;
    }

    /**
     * One line per thing that waits; empty when nothing does.
     *
     * @return list<string>
     */
    private function lines(Actor $actor, MemberAccountSummary $person, int $companyId): array
    {
        $now = $this->clock->now();
        $today = $now->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d');
        $lines = [];

        $assigned = $this->jobs->assignedJobIds($companyId, $person->userId);
        $received = $assigned === [] ? 0 : array_sum($this->applications->perJob($assigned, $now->modify('-24 hours')));

        if ($received > 0) {
            $lines[] = $this->withLink(TeamDigestLines::newApplications($received, $person->locale), 'company/applications');
        }

        foreach ($this->tasks->execute($actor)['items'] as $item) {
            if ($item['count'] > 0) {
                $lines[] = $this->withLink(TeamDigestLines::task($item['key'], $item['count'], $person->locale), $item['filter']);
            }
        }

        foreach ($this->interviewsToday($actor, $today) as $event) {
            $time = $event->startsAt->setTimezone(new DateTimeZone(self::ZONE))->format('H:i');
            $lines[] = $this->withLink(TeamDigestLines::interview($time, $event->candidateName, $event->jobTitle, $person->locale), 'company/applications/'.$event->applicationId);
        }

        return $lines;
    }

    /** @return list<CalendarEvent> the interviews of the day that involve the member, earliest first; none for a member who may not see applications */
    private function interviewsToday(Actor $actor, string $today): array
    {
        try {
            return $this->calendar->execute($actor, $today, $today, [CalendarEvent::INTERVIEW], null, true)['events'];
        } catch (AccessDenied) {
            return [];
        }
    }

    /** The line followed by its link when the company app is configured; the filter is the path and query the task list gives. */
    private function withLink(string $line, ?string $path): string
    {
        if ($this->companyAppUrl === null || $this->companyAppUrl === '' || $path === null || $path === '') {
            return $line;
        }

        return $line.': '.rtrim($this->companyAppUrl, '/').'/'.ltrim($path, '/');
    }
}
