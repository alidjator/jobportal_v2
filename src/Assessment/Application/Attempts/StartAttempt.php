<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptQuestion;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\IntegrityEventType;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\InvitationStatus;
use Src\Assessment\Domain\NormCandidate;
use Src\Assessment\Domain\QuestionDraw;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\SectionFormat;
use Src\Assessment\Domain\SectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Recruitment\ApplicantNormData;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The candidate agrees to the terms of the test (and, when it has video questions, to be recorded) and starts it. The attempt begins at once: the first section opens, the server timer starts and the questions
 * of every section are drawn. Every start gives the device an `attempt_session` token that the other calls must carry. Starting a test that was already started (and is still
 * open) gives the attempt as it is and takes it over for this device: the token of the device that held it stops working (`attempt-moved`) and a `device_switch` signal is
 * recorded. The clock never stops. A test that is closed (submitted or cancelled) is given back as it is, with no token.
 */
final readonly class StartAttempt
{
    public function __construct(
        private InvitationRepository $invitations,
        private AttemptRepository $attempts,
        private AttemptOutcomeRepository $outcomes,
        private QuestionRepository $questions,
        private SectionRepository $sections,
        private AttemptGate $gate,
        private ApplicantNormData $normData,
        private AttemptViews $views,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @return array{view: AttemptView, created: bool, session: string|null}
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `consent-required`, or `video-consent-required` when the test has video questions and the recording consent is missing
     * @throws StateConflict `invitation-not-open`
     */
    public function execute(Actor $actor, int $invitationId, bool $consent, ?string $ip, bool $videoConsent = false): array
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can take a test.', 'job-seeker-only');
        }

        if (! $consent) {
            throw new BusinessRuleViolation('Agree to the terms of the test to start it.', 'consent-required', extensions: ['errors' => ['consent' => ['Agree to the terms of the test to start it.']]]);
        }

        $userId = $actor->id;

        /** @var array{0: bool, 1: string|null} $started */
        $started = $this->transactions->run(function () use ($userId, $invitationId, $ip, $videoConsent): array {
            $invitation = $this->invitations->find($invitationId, forUpdate: true);

            if (! $invitation instanceof Invitation || $invitation->applicantUserId !== $userId) {
                throw EntityNotFound::of('Invitation', $invitationId);
            }

            $existing = $this->attempts->findByInvitation($invitationId, forUpdate: true);
            $now = $this->clock->now();

            if ($existing !== null) {
                if ($existing->isSubmitted() || $invitation->status !== InvitationStatus::Started) {
                    return [false, null];
                }

                [$token, $hash] = AttemptSessionToken::issue();
                $this->attempts->update($existing->heldBy($hash));
                $this->outcomes->addEvent((int) $existing->id, IntegrityEventType::DeviceSwitch, $now);

                return [false, $token];
            }

            $opened = $invitation->start($now);
            $sections = $this->sections->ofTest($invitation->testId);

            if (! $videoConsent && $this->questions->testHasType($invitation->testId, QuestionType::VideoAnswer)) {
                $message = 'This test records video answers: agree to be recorded to start it, or decline the test.';

                throw new BusinessRuleViolation($message, 'video-consent-required', extensions: ['errors' => ['video_consent' => [$message]]]);
            }

            [$token, $hash] = AttemptSessionToken::issue();
            $attempt = Attempt::begin($invitationId, $now, array_map(static fn (Section $section): int => $section->durationSeconds, $sections), random_int(1, 2_147_483_647), $ip, $videoConsent, $hash, $this->normCandidate($invitation));
            $this->invitations->update($opened);
            $this->attempts->add($attempt, $this->draw($sections, $attempt->seed));

            return [true, $token];
        });

        $view = $this->gate->run($actor, $invitationId, $ip, fn (AttemptContext $context): AttemptView => $this->views->build($context), readOnly: true, checkSession: false);

        return ['view' => $view->withSession($started[1]), 'created' => $started[0], 'session' => $started[1]];
    }

    /** The age and the education rank of the application, copied onto the attempt so a later erasure of the snapshot does not change a report. */
    private function normCandidate(Invitation $invitation): NormCandidate
    {
        $facts = $this->normData->of($invitation->applicationId);

        return new NormCandidate($facts->age, $facts->educationRank);
    }

    /**
     * @param  list<Section>  $sections
     * @return list<AttemptQuestion>
     */
    private function draw(array $sections, int $seed): array
    {
        $drawn = [];

        foreach ($sections as $section) {
            if ($section->format === SectionFormat::SpeedColumns) {
                continue;
            }

            $bank = $this->questions->ofSection((int) $section->id);

            foreach (QuestionDraw::pick($bank, $section->drawCount, $seed, (int) $section->id) as $index => $question) {
                $drawn[] = new AttemptQuestion((int) $section->id, (int) $question->id, $index + 1);
            }
        }

        return $drawn;
    }
}
