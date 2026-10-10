<?php

namespace Src\Review\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Review\Domain\ModerationReason;
use Src\Review\Domain\Review;
use Src\Review\Domain\ReviewContent;
use Src\Review\Domain\ReviewQuery;
use Src\Review\Domain\ReviewRepository;
use Src\Review\Domain\ReviewSort;
use Src\Review\Domain\ReviewStatus;
use Src\Review\Domain\ReviewSummary;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\StateConflict;
use stdClass;

final readonly class DatabaseReviewRepository implements ReviewRepository
{
    private const string TABLE = 'review_reviews';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function add(Review $review): Review
    {
        try {
            $id = (int) $this->connection->table(self::TABLE)->insertGetId($this->columns($review) + [
                'company_id' => $review->companyId,
                'user_id' => $review->userId,
                'created_at' => $review->createdAt->format(self::TIME_FORMAT),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('You already reviewed this company.', 'already-reviewed');
        }

        return new Review(
            $review->companyId, $review->userId, $review->content, $review->status, $review->reason, $review->note, $review->moderatedByAdminId,
            $review->moderatedAt, $review->publishedAt, $review->createdAt, $review->updatedAt, $id,
        );
    }

    public function update(Review $review): void
    {
        $this->connection->table(self::TABLE)->where('id', $review->id)->update($this->columns($review));
    }

    public function delete(int $reviewId): void
    {
        $this->connection->table(self::TABLE)->where('id', $reviewId)->delete();
    }

    public function find(int $reviewId, bool $forUpdate = false): ?Review
    {
        $query = $this->connection->table(self::TABLE)->where('id', $reviewId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toReview($row);
    }

    public function findOfUser(int $companyId, int $userId, bool $forUpdate = false): ?Review
    {
        $query = $this->connection->table(self::TABLE)->where('company_id', $companyId)->where('user_id', $userId);

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : $this->toReview($row);
    }

    public function publishedPage(int $companyId, ReviewSort $sort, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE)->where('company_id', $companyId)->where('status', ReviewStatus::Published->value);

        match ($sort) {
            ReviewSort::Newest => $builder->orderByDesc('published_at'),
            ReviewSort::Highest => $builder->orderByDesc('rating')->orderByDesc('published_at'),
            ReviewSort::Lowest => $builder->orderBy('rating')->orderByDesc('published_at'),
        };

        return $this->paged($builder->orderByDesc('id'), $request);
    }

    public function pageOfUser(int $userId, PageRequest $request): Page
    {
        return $this->paged($this->connection->table(self::TABLE)->where('user_id', $userId)->orderByDesc('id'), $request);
    }

    public function page(ReviewQuery $query, PageRequest $request): Page
    {
        $builder = $this->connection->table(self::TABLE);

        if ($query->status !== null) {
            $builder->where('status', $query->status->value);
        }

        if ($query->companyId !== null) {
            $builder->where('company_id', $query->companyId);
        }

        if ($query->reported) {
            $builder->whereExists(fn (Builder $reports) => $reports->from('review_reports')->whereColumn('review_reports.review_id', self::TABLE.'.id')->where('review_reports.status', 'open'));
        }

        return $this->paged($builder->orderByDesc('id'), $request);
    }

    public function summary(int $companyId): ReviewSummary
    {
        $row = $this->connection->table(self::TABLE)
            ->where('company_id', $companyId)->where('status', ReviewStatus::Published->value)
            ->selectRaw('count(*) as total, avg(rating) as average')
            ->selectRaw('count(culture_rating) as culture_count, avg(culture_rating) as culture_average')
            ->selectRaw('count(pay_rating) as pay_count, avg(pay_rating) as pay_average')
            ->selectRaw('count(hiring_rating) as hiring_count, avg(hiring_rating) as hiring_average')
            ->first();

        if ($row === null) {
            return new ReviewSummary(0, null, null, null, null);
        }

        $mean = static fn (mixed $value): ?float => $value === null ? null : (float) $value;

        return ReviewSummary::from((int) $row->total, $mean($row->average), [
            'culture' => [(int) $row->culture_count, $mean($row->culture_average)],
            'pay' => [(int) $row->pay_count, $mean($row->pay_average)],
            'hiring' => [(int) $row->hiring_count, $mean($row->hiring_average)],
        ]);
    }

    public function eraseOfUser(int $userId, DateTimeImmutable $now): array
    {
        $companies = array_map('intval', $this->connection->table(self::TABLE)->where('user_id', $userId)->where('status', ReviewStatus::Published->value)->pluck('company_id')->all());

        $this->connection->table(self::TABLE)->where('user_id', $userId)->where('status', '!=', ReviewStatus::Published->value)->delete();
        $this->connection->table(self::TABLE)->where('user_id', $userId)->update(['title' => null, 'body' => null, 'updated_at' => $now->format(self::TIME_FORMAT)]);

        return array_values(array_unique($companies));
    }

    /** @return Page<Review> */
    private function paged(Builder $builder, PageRequest $request): Page
    {
        $total = $builder->count();
        $rows = $builder->offset($request->offset())->limit($request->perPage)->get();

        return new Page(array_map(fn (stdClass $row): Review => $this->toReview($row), $rows->all()), $total, $request);
    }

    /** @return array<string, mixed> */
    private function columns(Review $review): array
    {
        return [
            'rating' => $review->content->rating,
            'culture_rating' => $review->content->cultureRating,
            'pay_rating' => $review->content->payRating,
            'hiring_rating' => $review->content->hiringRating,
            'title' => $review->content->title,
            'body' => $review->content->body,
            'status' => $review->status->value,
            'reason' => $review->reason?->value,
            'note' => $review->note,
            'moderated_by_admin_id' => $review->moderatedByAdminId,
            'moderated_at' => $review->moderatedAt?->format(self::TIME_FORMAT),
            'published_at' => $review->publishedAt?->format(self::TIME_FORMAT),
            'updated_at' => $review->updatedAt->format(self::TIME_FORMAT),
        ];
    }

    private function toReview(stdClass $row): Review
    {
        $int = static fn (mixed $value): ?int => $value === null ? null : (int) $value;
        $time = static fn (mixed $value): ?DateTimeImmutable => $value === null ? null : new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));

        return new Review(
            (int) $row->company_id,
            (int) $row->user_id,
            new ReviewContent((int) $row->rating, $int($row->culture_rating), $int($row->pay_rating), $int($row->hiring_rating), $row->title === null ? null : (string) $row->title, $row->body === null ? null : (string) $row->body),
            ReviewStatus::from((string) $row->status),
            $row->reason === null ? null : ModerationReason::from((string) $row->reason),
            $row->note === null ? null : (string) $row->note,
            $int($row->moderated_by_admin_id),
            $time($row->moderated_at),
            $time($row->published_at),
            new DateTimeImmutable((string) $row->created_at, new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row->updated_at, new DateTimeZone('UTC')),
            (int) $row->id,
        );
    }
}
