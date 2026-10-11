<?php

namespace Src\Assessment\Application\Review;

use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\Events\ReportSigned;
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
 * A psychologist signs a report. It needs an active psychologist whose STR and SILP are both valid today, an explanation for the candidate, a score for every
 * drawing and a norm for every scale. Signing numbers the report `CODE/YEAR/SEQUENCE` (the provider row is locked, so two signatures never take the same
 * number), fills the signature placeholders of the text, keeps a copy of the psychologist's name and licence numbers and locks the report.
 */
final readonly class SignReport
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
     * @throws StateConflict `report-signed`
     * @throws BusinessRuleViolation `explanation-required`, `drawings-unscored` or `scales-not-normed`
     */
    public function execute(Actor $actor, int $reportId, ?string $ipAddress, ?string $userAgent): ReviewView
    {
        [$view, $event] = $this->transactions->run(function () use ($actor, $reportId, $ipAddress, $userAgent): array {
            $psychologist = $this->access->psychologist($actor);
            $provider = $this->providers->findById($psychologist->providerId, forUpdate: true) ?? throw EntityNotFound::of('Provider', $psychologist->providerId);
            $report = $this->access->report($psychologist, $reportId, forUpdate: true);

            if ($report->isSigned()) {
                throw new StateConflict('This report is signed and cannot be changed.', 'report-signed');
            }

            $this->assertReady($this->views->build($psychologist, $report));

            $now = $this->clock->now();
            $year = (int) $now->format('Y');
            $sequence = $this->reports->nextSequence($psychologist->providerId, $year);
            $number = sprintf('%s/%d/%05d', $provider->code(), $year, $sequence);
            $accounts = $this->accounts->summaries([(int) $actor->id]);
            $name = isset($accounts[(int) $actor->id]) ? $accounts[(int) $actor->id]->name : '';

            $signed = $report->sign(
                ReportRenderer::signed($report->body, $number, $now->format('Y-m-d'), $name), $number, $year, $sequence, $psychologist, $name, $now,
            );
            $this->reports->update($signed);

            $attempt = $this->attempts->find($report->attemptId) ?? throw EntityNotFound::of('Attempt', $report->attemptId);
            $invitation = $this->invitations->find($attempt->invitationId) ?? throw EntityNotFound::of('Invitation', $attempt->invitationId);

            return [
                $this->views->build($psychologist, $signed),
                new ReportSigned(
                    (int) $signed->id, $number, (int) $invitation->id, $invitation->applicationId, $invitation->jobId, $invitation->companyId,
                    $psychologist->providerId, (int) $actor->id, $ipAddress, $userAgent, $now,
                ),
            ];
        });

        $this->events->dispatch([$event]);

        return $view;
    }

    /**
     * @throws AccessDenied `cannot-sign-reports`
     * @throws BusinessRuleViolation `explanation-required`, `drawings-unscored` or `scales-not-normed`
     */
    private function assertReady(ReviewView $view): void
    {
        $problems = $view->signingProblems;

        if (in_array(ReviewViews::LICENCE_INVALID, $problems, true)) {
            throw new AccessDenied('Your STR or SILP is not valid today, so you cannot sign reports.', 'cannot-sign-reports');
        }

        $messages = [
            ReviewViews::EXPLANATION_REQUIRED => ['explanation', 'Write the explanation for the candidate before signing.'],
            ReviewViews::DRAWINGS_UNSCORED => ['drawings', 'Score every drawing before signing.'],
            ReviewViews::SCALES_NOT_NORMED => ['norms', 'Some scales have no norm for the raw score, so the report cannot be signed.'],
        ];

        foreach ($messages as $code => [$field, $message]) {
            if (in_array($code, $problems, true)) {
                throw new BusinessRuleViolation($message, $code, extensions: ['errors' => [$field => [$message]]]);
            }
        }
    }
}
