<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Job\JobDirectory;

/**
 * The other applications of the person to this company (`docs/design/phase-8-recruiter-workday.md` section 4): newest first, at most ten the member may see, and a count of the ones on
 * jobs they may not see. A company admin or a member with `jobs.view_all` sees every job; anyone else only the assigned ones. An anonymised applicant shows none.
 */
final readonly class OtherApplications
{
    public const int LIMIT = 10;

    public function __construct(private RecruiterApplicationReader $reader, private JobDirectory $jobs) {}

    /** @return array{list<OtherApplicationView>, int} the visible ones, and how many are hidden */
    public function of(RecruitmentMember $member, RecruiterApplicationDetailData $data): array
    {
        if ($data->snapshot === null) {
            return [[], 0];
        }

        $others = $this->reader->otherApplications($data->id);
        $visible = $this->visibleJobIds($member);
        $shown = [];
        $hidden = 0;

        foreach ($others as $other) {
            if ($visible !== null && ! in_array($other->jobId, $visible, true)) {
                $hidden++;
            } elseif (count($shown) < self::LIMIT) {
                $shown[] = $other;
            }
        }

        $titles = $this->jobs->titles(array_values(array_unique(array_map(static fn (OtherApplicationRow $row): int => $row->jobId, $shown))));

        return [array_map(static fn (OtherApplicationRow $row): OtherApplicationView => new OtherApplicationView($row, $titles[$row->jobId] ?? null), $shown), $hidden];
    }

    /**
     * How many other active applications of the same person each of these applications has, among the jobs the member may see.
     *
     * @param  list<int>  $applicationIds
     * @return array<int, int>
     */
    public function counts(RecruitmentMember $member, array $applicationIds): array
    {
        return $this->reader->activeOtherCounts($applicationIds, $this->visibleJobIds($member));
    }

    /** @return list<int>|null null when the member sees every job of the company */
    private function visibleJobIds(RecruitmentMember $member): ?array
    {
        return $member->viewsAll() ? null : $this->jobs->assignedJobIds($member->companyId, $member->userId);
    }
}
