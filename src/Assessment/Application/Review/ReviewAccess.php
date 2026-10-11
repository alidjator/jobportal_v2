<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\Psychologist;
use Src\Assessment\Domain\PsychologistRepository;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The checks every review action starts with: the actor is an active psychologist of an active provider, and the report is their provider's. */
final readonly class ReviewAccess
{
    public function __construct(
        private PsychologistRepository $psychologists,
        private ProviderRepository $providers,
        private ReportRepository $reports,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended` or `provider-suspended`
     */
    public function psychologist(Actor $actor): Psychologist
    {
        if ($actor->kind !== ActorKind::Psychologist || $actor->id === null) {
            throw new AccessDenied('Only a psychologist can review reports.', 'not-a-psychologist');
        }

        $psychologist = $this->psychologists->findByUserId($actor->id) ?? throw new AccessDenied('This account is not a psychologist of a provider.', 'not-a-psychologist');

        if (! $psychologist->isActive()) {
            throw new AccessDenied('This psychologist account is suspended.', 'psychologist-suspended');
        }

        $provider = $this->providers->findById($psychologist->providerId);

        if ($provider === null || ! $provider->isActive()) {
            throw new AccessDenied('The provider of this psychologist is suspended.', 'provider-suspended');
        }

        return $psychologist;
    }

    /**
     * The report, when it belongs to the psychologist's provider; the same 404 whether it does not exist or belongs to another provider.
     *
     * @throws EntityNotFound
     */
    public function report(Psychologist $psychologist, int $reportId, bool $forUpdate = false): Report
    {
        $report = $this->reports->find($reportId, $forUpdate);

        if (! $report instanceof Report || $report->providerId !== $psychologist->providerId) {
            throw EntityNotFound::of('Report', $reportId);
        }

        return $report;
    }
}
