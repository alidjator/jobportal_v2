<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\Attempt;
use Src\Assessment\Domain\AttemptQuestion;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\QuestionDraw;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\SectionFormat;
use Src\Assessment\Domain\SectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The candidate agrees to the terms of the test and starts it. The attempt begins at once: the first section opens, the server timer starts and the questions
 * of every section are drawn. Starting a test that was already started changes nothing and gives the attempt as it is, so a reload after a lost response is safe.
 */
final readonly class StartAttempt
{
    public function __construct(
        private InvitationRepository $invitations,
        private AttemptRepository $attempts,
        private QuestionRepository $questions,
        private SectionRepository $sections,
        private AttemptGate $gate,
        private AttemptViews $views,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @return array{view: AttemptView, created: bool}
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `consent-required`
     * @throws StateConflict `invitation-not-open`
     */
    public function execute(Actor $actor, int $invitationId, bool $consent, ?string $ip): array
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker can take a test.', 'job-seeker-only');
        }

        if (! $consent) {
            throw new BusinessRuleViolation('Agree to the terms of the test to start it.', 'consent-required', extensions: ['errors' => ['consent' => ['Agree to the terms of the test to start it.']]]);
        }

        $userId = $actor->id;

        $created = $this->transactions->run(function () use ($userId, $invitationId, $ip): bool {
            $invitation = $this->invitations->find($invitationId, forUpdate: true);

            if (! $invitation instanceof Invitation || $invitation->applicantUserId !== $userId) {
                throw EntityNotFound::of('Invitation', $invitationId);
            }

            if ($this->attempts->findByInvitation($invitationId) !== null) {
                return false;
            }

            $now = $this->clock->now();
            $started = $invitation->start($now);
            $sections = $this->sections->ofTest($invitation->testId);

            $attempt = Attempt::begin($invitationId, $now, array_values(array_map(static fn (Section $section): int => $section->durationSeconds, $sections)), random_int(1, 2_147_483_647), $ip);
            $this->invitations->update($started);
            $this->attempts->add($attempt, $this->draw($sections, $attempt->seed));

            return true;
        });

        $view = $this->gate->run($actor, $invitationId, $ip, fn (AttemptContext $context): AttemptView => $this->views->build($context), readOnly: true);

        return ['view' => $view, 'created' => $created];
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
