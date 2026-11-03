<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Assessment\Domain\ProviderNoticeRepository;
use Src\Assessment\Domain\PsychologistLicences;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportStatus;
use Src\Assessment\Domain\ReviewsWaiting;
use Src\Shared\Application\Clock;

final readonly class DatabaseProviderNoticeRepository implements ProviderNoticeRepository
{
    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function reviewsWaiting(): array
    {
        $live = $this->clock->now()->modify(Report::CLAIM_LAPSE);
        $counts = [];

        foreach ($this->connection->table('assessment_reports')
            ->where(fn ($query) => $query
                ->where(fn ($draft) => $draft->where('status', ReportStatus::PendingReview->value)
                    ->where(fn ($free) => $free->whereNull('claimed_by_psychologist_id')->orWhere('claim_touched_at', '<=', $live)))
                ->orWhere(fn ($automatic) => $automatic->where('status', ReportStatus::Signed->value)->where('auto_issued', true)->whereNull('reviewed_at')))
            ->groupBy('provider_id')
            ->selectRaw('provider_id, count(*) as waiting')
            ->get() as $row) {
            $counts[(int) $row->provider_id] = (int) $row->waiting;
        }

        $rows = [];

        foreach ($this->activePsychologists()->get(['psychologists.id', 'psychologists.user_id', 'psychologists.provider_id', 'psychologists.reviews_notified_count']) as $row) {
            $rows[] = new ReviewsWaiting((int) $row->id, (int) $row->user_id, (int) $row->provider_id, $counts[(int) $row->provider_id] ?? 0, (int) $row->reviews_notified_count);
        }

        return $rows;
    }

    public function markReviewsNotified(int $psychologistId, int $count): void
    {
        $this->connection->table('assessment_psychologists')->where('id', $psychologistId)->update(['reviews_notified_count' => $count]);
    }

    public function psychologistsWithLicences(): array
    {
        $utc = new DateTimeZone('UTC');
        $rows = [];

        foreach ($this->activePsychologists()->get(['psychologists.id', 'psychologists.user_id', 'psychologists.provider_id', 'providers.name as provider_name', 'psychologists.str_valid_until', 'psychologists.silp_valid_until', 'psychologists.licence_notified']) as $row) {
            $notified = $row->licence_notified === null ? [] : json_decode((string) $row->licence_notified, true, 512, JSON_THROW_ON_ERROR);

            $rows[] = new PsychologistLicences(
                (int) $row->id,
                (int) $row->user_id,
                (int) $row->provider_id,
                (string) $row->provider_name,
                new DateTimeImmutable((string) $row->str_valid_until, $utc),
                new DateTimeImmutable((string) $row->silp_valid_until, $utc),
                is_array($notified) ? $notified : [],
            );
        }

        return $rows;
    }

    public function markLicenceNotified(int $psychologistId, array $notified): void
    {
        $this->connection->table('assessment_psychologists')->where('id', $psychologistId)->update(['licence_notified' => json_encode($notified, JSON_THROW_ON_ERROR)]);
    }

    public function autoIssueBatteriesOf(int $psychologistId): array
    {
        return $this->connection->table('assessment_tests')
            ->where('responsible_psychologist_id', $psychologistId)
            ->where('auto_issue', true)
            ->orderBy('id')
            ->get(['id', 'title'])
            ->map(static fn ($row): array => ['id' => (int) $row->id, 'title' => (string) $row->title])
            ->all();
    }

    public function activeUserIdsOfProvider(int $providerId): array
    {
        return array_map('intval', $this->connection->table('assessment_psychologists')->where('provider_id', $providerId)->where('status', 'active')->pluck('user_id')->all());
    }

    /** The active psychologists of the providers that are active, with the provider joined as `providers`. */
    private function activePsychologists(): Builder
    {
        return $this->connection->table('assessment_psychologists as psychologists')
            ->join('assessment_providers as providers', 'providers.id', '=', 'psychologists.provider_id')
            ->where('psychologists.status', 'active')
            ->where('providers.status', 'active');
    }
}
