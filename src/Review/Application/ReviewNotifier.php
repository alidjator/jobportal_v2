<?php

namespace Src\Review\Application;

use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Notifications\InAppNotifier;

/** Writes the in-app notifications of the review actions. The data carries ids and a reason code, never the text of a review. */
final readonly class ReviewNotifier
{
    public function __construct(
        private InAppNotifier $notifier,
        private CompanyTeam $team,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function author(int $userId, string $type, int $reviewId, int $companyId, array $data = []): void
    {
        $this->notifier->send([$userId], $type, ['review_id' => $reviewId, 'company_id' => $companyId, ...$data]);
    }

    public function companyAdmins(int $companyId, string $type, int $reviewId): void
    {
        $admins = array_keys(array_filter($this->team->roles($companyId), static fn (string $role): bool => $role === 'company_admin'));

        if ($admins !== []) {
            $this->notifier->send($admins, $type, ['review_id' => $reviewId, 'company_id' => $companyId]);
        }
    }
}
