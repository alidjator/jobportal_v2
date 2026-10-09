<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\ProfileViewKind;
use Src\JobSeeker\Domain\ProfileViewRepository;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

final readonly class DatabaseProfileViewRepository implements ProfileViewRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const string DAY_FORMAT = 'Y-m-d';

    public function __construct(private ConnectionInterface $connection) {}

    public function exists(int $memberId, int $userId, ProfileViewKind $kind, DateTimeImmutable $day): bool
    {
        return $this->connection->table('jobseeker_profile_views')
            ->where('member_id', $memberId)->where('user_id', $userId)->where('kind', $kind->value)->where('day', $day->format(self::DAY_FORMAT))
            ->exists();
    }

    public function record(int $userId, int $companyId, int $memberId, ProfileViewKind $kind, DateTimeImmutable $day, DateTimeImmutable $at): bool
    {
        return $this->connection->table('jobseeker_profile_views')->insertOrIgnore([
            'user_id' => $userId,
            'company_id' => $companyId,
            'member_id' => $memberId,
            'kind' => $kind->value,
            'day' => $day->format(self::DAY_FORMAT),
            'created_at' => $at->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT),
        ]) === 1;
    }

    public function countOfMember(int $memberId, ProfileViewKind $kind, DateTimeImmutable $day): int
    {
        return $this->connection->table('jobseeker_profile_views')
            ->where('member_id', $memberId)->where('kind', $kind->value)->where('day', $day->format(self::DAY_FORMAT))
            ->count();
    }

    public function ofCandidate(int $userId, PageRequest $request): Page
    {
        $views = $this->connection->table('jobseeker_profile_views')->where('user_id', $userId)->select('company_id', 'kind', 'day')->distinct();
        $total = $this->connection->table($views, 'v')->count();

        $rows = $this->connection->table('jobseeker_profile_views')
            ->where('user_id', $userId)
            ->groupBy('company_id', 'kind', 'day')
            ->selectRaw('company_id, kind, day, MIN(created_at) as viewed_at')
            ->orderByDesc('day')->orderByDesc('viewed_at')->orderBy('company_id')->orderBy('kind')
            ->offset($request->offset())->limit($request->perPage)
            ->get();

        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'company_id' => (int) $row->company_id,
                'kind' => ProfileViewKind::from((string) $row->kind),
                'day' => substr((string) $row->day, 0, 10),
                'viewed_at' => new DateTimeImmutable((string) $row->viewed_at, new DateTimeZone('UTC')),
            ];
        }

        return new Page($items, $total, $request);
    }
}
