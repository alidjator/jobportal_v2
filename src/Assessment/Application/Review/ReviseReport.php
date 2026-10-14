<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\Events\ReportRevised;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\ProviderRepository;
use Src\Assessment\Domain\ReportRenderer;
use Src\Assessment\Domain\ReportRepository;
use Src\Shared\Application\Accounts\PsychologistAccounts;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A psychologist of the provider, with a valid STR and SILP, replaces the text and the explanation of a report that was issued automatically. The report keeps its
 * number and gets the suffix `-R1`, `-R2`; the version it replaces is kept; the reviser signs (a copy of their name and licence numbers) and the report counts as
 * reviewed. The company team and the candidate are told. A report a psychologist signed by hand stays locked.
 */
final readonly class ReviseReport
{
    public function __construct(
        private ReviewAccess $access,
        private ReviewViews $views,
        private ReportRepository $reports,
        private ProviderRepository $providers,
        private AttemptRepository $attempts,
        private InvitationRepository $invitations,
        private PsychologistAccounts $accounts,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-psychologist`, `psychologist-suspended`, `provider-suspended` or `cannot-sign-reports`
     * @throws EntityNotFound
     * @throws StateConflict `report-not-auto-issued`
     * @throws BusinessRuleViolation `invalid-report` or `explanation-required`
     */
    public function execute(Actor $actor, int $reportId, string $body, string $explanation, ?string $ipAddress, ?string $userAgent): ReviewView
    {
        [$view, $event] = $this->transactions->run(function () use ($actor, $reportId, $body, $explanation, $ipAddress, $userAgent): array {
            $psychologist = $this->access->psychologist($actor);
            // The provider row first, as signing does.
            $this->providers->findById($psychologist->providerId, forUpdate: true) ?? throw EntityNotFound::of('Provider', $psychologist->providerId);
            $report = $this->access->report($psychologist, $reportId, forUpdate: true);
            $now = $this->clock->now();

            if (! $psychologist->canSignReports($now)) {
                throw new AccessDenied('Your STR or SILP is not valid today, so you cannot revise reports.', 'cannot-sign-reports');
            }

            $body = trim($body);
            $explanation = trim($explanation);

            if ($body === '') {
                throw new BusinessRuleViolation('The report text cannot be empty.', 'invalid-report', extensions: ['errors' => ['body' => ['The report text cannot be empty.']]]);
            }

            if ($explanation === '') {
                throw new BusinessRuleViolation('Write the explanation for the candidate.', 'explanation-required', extensions: ['errors' => ['explanation' => ['Write the explanation for the candidate.']]]);
            }

            $accounts = $this->accounts->summaries([(int) $actor->id]);
            $name = isset($accounts[(int) $actor->id]) ? $accounts[(int) $actor->id]->name : '';

            $revised = $report->revised($body, $explanation, $psychologist, $name, $now);
            $revised = $revised->withBody(ReportRenderer::signed($revised->body, (string) $revised->number, $now->format('Y-m-d'), $name));

            $this->reports->keepVersion($report, $now);
            $this->reports->update($revised);

            $attempt = $this->attempts->find($report->attemptId) ?? throw EntityNotFound::of('Attempt', $report->attemptId);
            $invitation = $this->invitations->find($attempt->invitationId) ?? throw EntityNotFound::of('Invitation', $attempt->invitationId);

            return [
                $this->views->build($psychologist, $revised),
                new ReportRevised(
                    (int) $revised->id, (string) $revised->number, $revised->revision, (int) $invitation->id, $invitation->applicationId, $invitation->jobId, $invitation->companyId,
                    $psychologist->providerId, (int) $actor->id, $ipAddress, $userAgent, $now,
                ),
            ];
        });

        $this->events->dispatch([$event]);

        return $view;
    }
}
