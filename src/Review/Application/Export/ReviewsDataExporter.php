<?php

namespace Src\Review\Application\Export;

use DateTimeInterface;
use Src\Review\Domain\Review;
use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\Privacy\PersonalDataExporter;
use Src\Shared\Application\Privacy\PersonalDataSection;

/** The Review part of the personal data export: the company reviews the job seeker wrote, with their status and the reason code of a rejection. Moderators' notes and the companies' replies are not part of it. */
final readonly class ReviewsDataExporter implements PersonalDataExporter
{
    public function __construct(private ReviewRepository $reviews, private CompanyDirectory $companies) {}

    public function key(): string
    {
        return 'reviews';
    }

    public function export(int $userId): PersonalDataSection
    {
        $reviews = [];

        for ($page = 1; ; $page++) {
            $result = $this->reviews->pageOfUser($userId, new PageRequest($page, PageRequest::MAX_PER_PAGE));
            array_push($reviews, ...$result->items);

            if (count($result->items) < PageRequest::MAX_PER_PAGE) {
                break;
            }
        }

        $companies = $this->companies->summaries(array_values(array_unique(array_map(static fn (Review $review): int => $review->companyId, $reviews))));

        return new PersonalDataSection(array_map(static fn (Review $review): array => [
            'company' => ($companies[$review->companyId] ?? null)?->name,
            'rating' => $review->content->rating,
            'culture_rating' => $review->content->cultureRating,
            'pay_rating' => $review->content->payRating,
            'hiring_rating' => $review->content->hiringRating,
            'title' => $review->content->title,
            'text' => $review->content->body,
            'status' => $review->status->value,
            'rejection_reason' => $review->reason?->value,
            'written_at' => $review->createdAt->format(DateTimeInterface::ATOM),
            'published_at' => $review->publishedAt?->format(DateTimeInterface::ATOM),
        ], $reviews));
    }
}
