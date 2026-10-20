<?php

namespace Src\TalentPool\Application;

use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Candidate\CandidateDirectory;
use Src\Shared\Application\Clock;
use Src\Shared\Application\JobInvitation\LatestInvitations;
use Src\Shared\Application\Recruitment\PoolApplications;
use Src\TalentPool\Domain\CategoryRepository;
use Src\TalentPool\Domain\ConsentWindow;
use Src\TalentPool\Domain\Entry;
use Src\TalentPool\Domain\EntrySource;

/**
 * Turns entries into what the company sees. Who is available, and how, is looked up once for the whole list through ports: a findable candidate shows the candidate
 * search summary; a person available through consent alone shows the snapshot of their latest consenting application (never the live profile, no contact data).
 * A private list carried over from karir.pkp (`legacy` source) shows the name of a person who is not available too, and nothing else about them.
 * Nothing about the person is copied into TalentPool.
 */
final readonly class EntryViews
{
    public function __construct(
        private CandidateDirectory $candidates,
        private PoolApplications $applications,
        private LatestInvitations $invitations,
        private CompanyMemberAccounts $members,
        private CategoryRepository $categories,
        private Clock $clock,
    ) {}

    /**
     * @param  list<Entry>  $entries
     * @return list<EntryView> in the order of the entries
     */
    public function views(Actor $actor, PoolMember $member, array $entries, string $language): array
    {
        if ($entries === []) {
            return [];
        }

        $userIds = array_values(array_unique(array_map(static fn (Entry $entry): int => $entry->candidateUserId, $entries)));
        $found = $this->candidates->summaries($userIds, $language);
        $others = array_values(array_filter($userIds, static fn (int $id): bool => ! isset($found[$id])));
        $snapshots = $this->applications->consentSnapshots($member->companyId, $others, ConsentWindow::since($this->clock->now()), $language);
        $available = array_values(array_filter($userIds, static fn (int $id): bool => isset($found[$id]) || isset($snapshots[$id])));
        $applications = $this->applications->latestApplications($actor, $available);
        $invitations = $this->invitations->ofCompany($member->companyId, $available);
        $legacyNames = $this->candidates->names(array_values(array_unique(array_map(
            static fn (Entry $entry): int => $entry->candidateUserId,
            array_filter($entries, static fn (Entry $entry): bool => $entry->source === EntrySource::Legacy && ! isset($found[$entry->candidateUserId]) && ! isset($snapshots[$entry->candidateUserId])),
        ))));
        $savers = $this->members->summaries(array_values(array_unique(array_filter(array_map(static fn (Entry $entry): ?int => $entry->savedByUserId, $entries)))));

        $categoryNames = [];

        foreach ($this->categories->ofCompany($member->companyId) as $category) {
            $categoryNames[(int) $category->id] = $category->name;
        }

        return array_map(function (Entry $entry) use ($found, $snapshots, $applications, $invitations, $savers, $categoryNames, $legacyNames): EntryView {
            $userId = $entry->candidateUserId;
            $summary = $found[$userId] ?? null;
            $snapshot = $snapshots[$userId] ?? null;
            $isAvailable = $summary !== null || $snapshot !== null;

            return new EntryView(
                (int) $entry->id,
                $entry->categoryId,
                $entry->categoryId === null ? null : ($categoryNames[$entry->categoryId] ?? null),
                $isAvailable,
                $summary?->slug,
                $summary->name ?? $snapshot->name ?? ($legacyNames[$userId] ?? null),
                $isAvailable ? $entry->note : null,
                $entry->createdAt,
                $entry->source,
                $entry->savedByUserId !== null && isset($savers[$entry->savedByUserId]) ? $entry->savedByUserId : null,
                $entry->savedByUserId === null ? null : ($savers[$entry->savedByUserId]->name ?? null),
                $summary->headline ?? $snapshot?->headline,
                $summary->city ?? $snapshot?->city,
                $summary->latestPosition ?? $snapshot?->latestPosition,
                $summary->skills ?? $snapshot->skills ?? [],
                $isAvailable ? ($applications[$userId] ?? null) : null,
                $isAvailable ? ($invitations[$userId] ?? null) : null,
            );
        }, $entries);
    }
}
