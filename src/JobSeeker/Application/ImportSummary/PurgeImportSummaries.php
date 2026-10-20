<?php

namespace Src\JobSeeker\Application\ImportSummary;

use Src\JobSeeker\Domain\ImportSummaryRepository;
use Src\Shared\Application\Accounts\FirstSignIns;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;

/**
 * Deletes the import summaries whose owner first signed in more than `identity.import_summary_retention_days` days ago (90 by default). A summary of an account that has not signed in
 * yet is kept. Running it again deletes nothing more.
 */
final readonly class PurgeImportSummaries
{
    private const int CHUNK = 500;

    public function __construct(
        private ImportSummaryRepository $summaries,
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

        while (($ids = $this->summaries->userIdsAfter($cursor, self::CHUNK)) !== []) {
            $cursor = max($ids);
            $expired = array_keys(array_filter($this->signIns->firstSignedInAt($ids), static fn ($at): bool => $at <= $cutoff));

            if ($expired !== []) {
                $this->summaries->deleteOfUsers($expired);
                $deleted += count($expired);
            }
        }

        return $deleted;
    }
}
