<?php

namespace Src\Company\Application\ImportSummary;

use Src\Company\Domain\CompanyMemberRepository;
use Src\Company\Domain\ImportSummaryRepository;
use Src\Shared\Application\Accounts\FirstSignIns;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;

/**
 * Deletes the import summaries of the companies whose owner first signed in more than `identity.import_summary_retention_days` days ago (90 by default). A company whose owner has not signed in
 * yet, or that has no owner, keeps its summary. Running it again deletes nothing more.
 */
final readonly class PurgeCompanyImportSummaries
{
    private const int CHUNK = 200;

    public function __construct(
        private ImportSummaryRepository $summaries,
        private CompanyMemberRepository $members,
        private FirstSignIns $signIns,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /** @return int how many summaries were deleted */
    public function execute(): int
    {
        $cutoff = $this->clock->now()->modify('-'.$this->settings->integer('identity.import_summary_retention_days').' days');
        $deleted = 0;
        $cursor = 0;

        while (($ids = $this->summaries->companyIdsAfter($cursor, self::CHUNK)) !== []) {
            $cursor = max($ids);
            $ownerOf = [];

            foreach ($ids as $companyId) {
                $owner = $this->members->ownerUserId($companyId);

                if ($owner !== null) {
                    $ownerOf[$companyId] = $owner;
                }
            }

            $signedIn = $this->signIns->firstSignedInAt(array_values($ownerOf));
            $expired = array_keys(array_filter($ownerOf, static fn (int $owner): bool => isset($signedIn[$owner]) && $signedIn[$owner] <= $cutoff));

            if ($expired !== []) {
                $this->summaries->deleteOfCompanies($expired);
                $deleted += count($expired);
            }
        }

        return $deleted;
    }
}
