<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Recruitment\PoolApplicationFacts;
use Src\Shared\Application\Recruitment\PoolApplicationRef;
use Src\Shared\Application\Recruitment\PoolApplications;
use Src\Shared\Application\Recruitment\PoolConsentSnapshot;

/**
 * The view of Recruitment the talent pool works with. The snapshot of a consenting application is cut down to what a company may see in a list (name, headline, city,
 * latest position, five skills). An application's id is given only to a member who holds `applications.view` and sees its job (a company admin or `jobs.view_all`: every
 * job; anyone else the assigned ones; a bound hiring manager only the marked applications), the same rule as the other applications of a person.
 */
final readonly class RecruitmentPoolApplications implements PoolApplications
{
    private const int SKILLS = 5;

    private const int LINKS = 20;

    public function __construct(private PoolApplicationReader $reader, private RecruitmentAccess $access, private HiringManagerScope $scope, private JobDirectory $jobs) {}

    public function facts(int $applicationId): ?PoolApplicationFacts
    {
        $facts = $this->reader->facts($applicationId);

        return $facts === null ? null : new PoolApplicationFacts($applicationId, $facts['companyId'], $facts['applicantUserId'], $facts['stageType'] === StageType::Hired, $facts['consentGiven'], $facts['appliedAt']);
    }

    public function consentingUserIds(int $companyId, array $userIds, DateTimeImmutable $since): array
    {
        return $userIds === [] ? [] : $this->reader->consentingUserIds($companyId, $userIds, $since);
    }

    public function consentSnapshots(int $companyId, array $userIds, DateTimeImmutable $since, string $language): array
    {
        if ($userIds === []) {
            return [];
        }

        $snapshots = [];

        foreach ($this->reader->latestConsenting($companyId, $userIds, $since) as $userId => $row) {
            $snapshot = $row['snapshot'];
            $experience = $snapshot['experiences'][0] ?? null;

            $snapshots[$userId] = new PoolConsentSnapshot(
                $row['id'],
                $row['appliedAt'],
                $this->text($snapshot['name'] ?? null),
                $this->text($snapshot['headline'] ?? null),
                $this->text($snapshot['location']['city'] ?? null),
                is_array($experience) ? ['title' => (string) ($experience['job_title'] ?? ''), 'employer' => (string) ($experience['company_name'] ?? '')] : null,
                $this->skills($snapshot['skills'] ?? [], $language),
                $this->withoutContact($snapshot),
            );
        }

        return $snapshots;
    }

    public function latestApplications(Actor $actor, array $userIds): array
    {
        $member = $this->access->member($actor);
        $rows = $userIds === [] ? [] : $this->reader->latest($member->companyId, $userIds);
        $titles = $this->titles($rows);
        $sees = $this->visibility($member);
        $refs = [];

        foreach ($rows as $userId => $row) {
            $refs[$userId] = new PoolApplicationRef($sees($row) ? $row->id : null, $titles[$row->jobId] ?? null, $row->stageType->value, $row->appliedAt);
        }

        return $refs;
    }

    public function visibleApplications(Actor $actor, int $applicantUserId): array
    {
        $member = $this->access->member($actor);
        $sees = $this->visibility($member);
        $rows = array_slice(array_values(array_filter($this->reader->of($member->companyId, $applicantUserId), $sees)), 0, self::LINKS);
        $titles = $this->titles($rows);

        return array_map(static fn (PoolApplicationRow $row): PoolApplicationRef => new PoolApplicationRef($row->id, $titles[$row->jobId] ?? null, $row->stageType->value, $row->appliedAt), $rows);
    }

    /**
     * @param  array<PoolApplicationRow>  $rows
     * @return array<int, string>
     */
    private function titles(array $rows): array
    {
        return $this->jobs->titles(array_values(array_unique(array_map(static fn (PoolApplicationRow $row): int => $row->jobId, $rows))));
    }

    /** @return callable(PoolApplicationRow): bool */
    private function visibility(RecruitmentMember $member): callable
    {
        if (! $member->membership->can(CompanyPermission::ApplicationsView)) {
            return static fn (PoolApplicationRow $row): bool => false;
        }

        $assigned = $member->viewsAll() ? null : $this->jobs->assignedJobIds($member->companyId, $member->userId);
        $bound = $this->scope->boundJobIds($member);

        return static fn (PoolApplicationRow $row): bool => ($assigned === null || in_array($row->jobId, $assigned, true))
            && ($row->hiringManagerVisible || ! in_array($row->jobId, $bound, true));
    }

    /**
     * The snapshot without the ways to reach the person: a company that wants to contact someone it kept only through consent invites them to a job.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function withoutContact(array $snapshot): array
    {
        unset($snapshot['email'], $snapshot['phone'], $snapshot['whatsapp'], $snapshot['links']);

        if (is_array($snapshot['location'] ?? null)) {
            unset($snapshot['location']['address_line']);
        }

        return $snapshot;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * @param  mixed  $skills  the `skills` of a snapshot: `{id, name: {id, en}}` each
     * @return list<string>
     */
    private function skills(mixed $skills, string $language): array
    {
        $names = [];

        foreach (is_array($skills) ? $skills : [] as $skill) {
            $name = is_array($skill) && is_array($skill['name'] ?? null) ? ($skill['name'][$language] ?? $skill['name']['en'] ?? $skill['name']['id'] ?? null) : null;

            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }

            if (count($names) === self::SKILLS) {
                break;
            }
        }

        return $names;
    }
}
