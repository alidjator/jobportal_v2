<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationFacetRepository;
use Src\Shared\Application\JobInvitation\InvitedCandidates;
use Src\Shared\Application\TransactionManager;

/**
 * Builds the facets of the applications that have none, from their stored snapshots, imported ones included (`docs/design/phase-8-applicant-search.md` section 1). It
 * works in batches and only touches applications without facets, so it is safe to run again. An invitation counts when the application's source says so or an
 * invitation holds the application.
 */
final readonly class BuildApplicationFacets
{
    public const int BATCH = 200;

    public function __construct(
        private ApplicationFacetRepository $facets,
        private ApplicationFacetBuilder $builder,
        private InvitedCandidates $invitations,
        private TransactionManager $transactions,
    ) {}

    /** @return int how many applications got facets */
    public function execute(): int
    {
        $built = 0;
        $after = 0;

        while (($sources = $this->facets->sourcesWithoutFacets($after, self::BATCH)) !== []) {
            $held = $this->invitations->applicationIdsHeld(array_map(static fn ($source): int => $source->applicationId, $sources));

            $this->transactions->run(function () use ($sources, $held): void {
                foreach ($sources as $source) {
                    $this->facets->save(
                        $source->applicationId,
                        $source->companyId,
                        $source->jobId,
                        $source->appliedAt,
                        $this->builder->build(
                            $source->snapshot,
                            $source->appliedAt,
                            $source->viaInvitationSource || in_array($source->applicationId, $held, true),
                            $this->facets->hasEarlierApplication($source->userId, $source->companyId, $source->appliedAt, $source->applicationId),
                        ),
                    );
                }
            });

            $built += count($sources);
            $after = $sources[array_key_last($sources)]->applicationId;
        }

        return $built;
    }
}
