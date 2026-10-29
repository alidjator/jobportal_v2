<?php

namespace Src\DemoData\Application;

use DateTimeZone;
use LogicException;
use Src\Assessment\Application\Attempts\AttemptSessionToken;
use Src\Assessment\Application\Attempts\FinishSection;
use Src\Assessment\Application\Attempts\SaveAnswer;
use Src\Assessment\Application\Attempts\StartAttempt;
use Src\Assessment\Application\Attempts\SubmitAttempt;
use Src\Assessment\Application\Delivery\ListApplicationInvitations;
use Src\Assessment\Application\Results\ListScoringAnswers;
use Src\Assessment\Application\Results\ScoreAnswer;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionType;
use Src\Company\Domain\MemberPreset;
use Src\Recruitment\Application\AnswerInterviewRsvp;
use Src\Recruitment\Application\AnswerReview;
use Src\Recruitment\Application\CreateReviewRequest;
use Src\Recruitment\Application\ListApplicationInterviews;
use Src\Recruitment\Application\ReviewRequestView;
use Src\Recruitment\Application\UpdateInterviewPanel;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\DomainException;

/**
 * The work that waits in the lists of the members of the verified company (`DemoMyWorkContent`): the interviews they hold or join get their panel and their answers, the recruiter asks the hiring manager to review shortlists and the
 * hiring manager answers some of them, and the candidates of the UI/UX test take it (one of them leaves it unfinished) while an assessor scores the case study of the first. It works on the applications `DemoApplications` made and
 * does nothing a member could not do in the application.
 */
final readonly class DemoMyWork
{
    public function __construct(
        private ListApplicationInterviews $interviews,
        private UpdateInterviewPanel $panel,
        private AnswerInterviewRsvp $rsvp,
        private CreateReviewRequest $createReview,
        private AnswerReview $answerReview,
        private ListApplicationInvitations $invitations,
        private AttemptSessionToken $session,
        private StartAttempt $start,
        private SaveAnswer $saveAnswer,
        private FinishSection $finishSection,
        private SubmitAttempt $submit,
        private ListScoringAnswers $scoringAnswers,
        private ScoreAnswer $score,
        private DemoActors $actors,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, array{company_id: int, admin_id: int, members: array<string, int>}>  $teams
     * @param  array<string, int>  $jobIds  the job ids by job key
     * @param  array<string, int>  $applicationIds  the application ids by `"{job key}:{seeker}"`
     * @param  list<array{user_id: int, resume_id: int}>  $seekers
     * @return array{interviews: int, reviews: int, tests: int} the interview answers, the review answers and the tests taken
     */
    public function build(array $teams, array $jobIds, array $applicationIds, array $seekers): array
    {
        $members = $teams[DemoCast::SINAR]['members'];
        $recruiter = $this->actors->member($members[MemberPreset::Recruiter->value]);

        return [
            'interviews' => $this->interviewWork($recruiter, $members, $applicationIds),
            'reviews' => $this->reviewWork($recruiter, $members, $jobIds, $applicationIds),
            'tests' => $this->testWork($recruiter, $members, $applicationIds, $seekers),
        ];
    }

    /**
     * @param  array<string, int>  $members
     * @param  array<string, int>  $applicationIds
     * @return int the answers given
     */
    private function interviewWork(Actor $recruiter, array $members, array $applicationIds): int
    {
        foreach (DemoMyWorkContent::panels() as $application => $presets) {
            $interview = $this->step("interview of {$application}", fn (): int => $this->interviewOf($recruiter, $applicationIds[$application] ?? throw new LogicException("The demo has no application {$application}.")));
            $this->step("panel of {$application}", fn (): mixed => $this->panel->execute($recruiter, $interview, array_map(static fn (string $preset): int => $members[$preset], $presets)));
        }

        $answers = 0;

        foreach (DemoMyWorkContent::rsvps() as [$application, $preset, $answer, $note]) {
            $interview = $this->step("interview of {$application}", fn (): int => $this->interviewOf($recruiter, $applicationIds[$application]));
            $this->step("answer of {$preset} to {$application}", fn (): mixed => $this->rsvp->execute($this->actors->member($members[$preset]), $interview, $answer, $note));
            $answers++;
        }

        return $answers;
    }

    /**
     * Runs one step of the seeding and names it when it is refused, because a refusal of a use case does not say which of the many calls of the run it was.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function step(string $what, callable $work): mixed
    {
        try {
            return $work();
        } catch (DomainException $e) {
            throw new LogicException("The demo work, {$what}, was refused: {$e->getMessage()}", previous: $e);
        }
    }

    /** The id of the interview of an application, which the demo gives one only. */
    private function interviewOf(Actor $team, int $applicationId): int
    {
        $views = $this->interviews->execute($team, $applicationId);

        return (int) ($views[0]->interview->id ?? throw new LogicException("The application {$applicationId} has no interview."));
    }

    /**
     * @param  array<string, int>  $members
     * @param  array<string, int>  $jobIds
     * @param  array<string, int>  $applicationIds
     * @return int the answers given
     */
    private function reviewWork(Actor $recruiter, array $members, array $jobIds, array $applicationIds): int
    {
        $hiringManager = $this->actors->member($members[MemberPreset::HiringManager->value]);
        $jakarta = new DateTimeZone('Asia/Jakarta');
        $answers = 0;

        foreach (DemoMyWorkContent::reviewRequests() as $plan) {
            $due = $this->clock->now()->setTimezone($jakarta)->modify('+'.$plan['days'].' days')->format('Y-m-d');
            $request = $this->step("review request of {$plan['job']}", fn (): ReviewRequestView => $this->createReview->execute(
                $recruiter, $jobIds[$plan['job']], array_map(static fn (string $key): int => $applicationIds[$key], $plan['applications']), [$members[MemberPreset::HiringManager->value]], $due, $plan['message'],
            ));

            foreach ($plan['answers'] as [$application, $decision, $reason]) {
                $this->step("review answer on {$application}", fn (): mixed => $this->answerReview->execute($hiringManager, (int) $request->request->id, $applicationIds[$application], $decision, $reason));
                $answers++;
            }
        }

        return $answers;
    }

    /**
     * The candidates of the UI/UX test: the first takes it with good answers and gets the case study scored by an assessor, the second takes it with weaker answers and waits for the score, the third has the invitation
     * and has not started.
     *
     * @param  array<string, int>  $members
     * @param  array<string, int>  $applicationIds
     * @param  list<array{user_id: int, resume_id: int}>  $seekers
     * @return int the tests taken
     */
    private function testWork(Actor $recruiter, array $members, array $applicationIds, array $seekers): int
    {
        $assessor = $this->actors->member($members[MemberPreset::Recruiter->value]);
        $taken = 0;

        foreach ([[3, true], [4, false]] as [$seeker, $strong]) {
            $invitation = $this->step("test invitation of uiux:{$seeker}", fn (): int => $this->invitationOf($recruiter, $applicationIds["uiux:{$seeker}"]));
            $candidate = Actor::jobSeeker($seekers[$seeker]['user_id']);

            $this->step("test of uiux:{$seeker}", fn (): mixed => $this->takeTest($candidate, $invitation, $strong));
            $taken++;

            if ($strong) {
                $this->step("scoring of uiux:{$seeker}", fn (): mixed => $this->scoreCaseStudy($assessor, $invitation));
            }
        }

        return $taken;
    }

    /** The id of the test the application was invited to, which the demo sends once. */
    private function invitationOf(Actor $team, int $applicationId): int
    {
        $views = $this->invitations->execute($team, $applicationId);

        return (int) ($views[0]->invitation->id ?? throw new LogicException("The application {$applicationId} has no test."));
    }

    /** Takes the test section by section as the candidate does: start, answer every question of the section, go on, and submit at the last section. */
    private function takeTest(Actor $candidate, int $invitationId, bool $strong): void
    {
        $started = $this->start->execute($candidate, $invitationId, true, null);
        $view = $started['view'];
        $this->session->set($started['session']);
        $case = DemoMyWorkContent::caseAnswers($strong);
        $caseIndex = 0;

        while (true) {
            foreach ($view->questions as $delivered) {
                $this->answer($candidate, $invitationId, $delivered->question, $strong, $case, $caseIndex);
            }

            if ($view->context->attempt->sectionPosition >= count($view->context->sections)) {
                break;
            }

            $view = $this->finishSection->execute($candidate, $invitationId, null);
        }

        $this->submit->execute($candidate, $invitationId, null);
    }

    /**
     * Answers one question: a strong candidate chooses what is correct, a weak one gets the first choice wrong and a number one off.
     *
     * @param  list<string>  $case  the answers of the case study, in the order of its questions
     */
    private function answer(Actor $candidate, int $invitationId, Question $question, bool $strong, array $case, int &$caseIndex): void
    {
        $id = (int) $question->id;

        $type = $question->type;

        if ($type === QuestionType::SingleChoice || $type === QuestionType::TrueFalse) {
            $this->saveAnswer->save($candidate, $invitationId, $id, [$this->position($question, $strong)], null, null, null);
        } elseif ($type === QuestionType::MultipleResponse) {
            $this->saveAnswer->save($candidate, $invitationId, $id, $this->correctPositions($question, $strong), null, null, null);
        } elseif ($type === QuestionType::Number) {
            $this->saveAnswer->save($candidate, $invitationId, $id, null, $this->accepted($question) + ($strong ? 0 : 1), null, null);
        } elseif ($type === QuestionType::ShortText) {
            $this->saveAnswer->save($candidate, $invitationId, $id, null, null, $strong ? $this->acceptedText($question) : 'tidak tahu', null);
        } elseif ($type === QuestionType::LongText) {
            $this->saveAnswer->save($candidate, $invitationId, $id, null, null, $case[$caseIndex++] ?? 'Belum sempat saya uraikan.', null);
        } else {
            throw new LogicException("The demo test has no answer for a {$type->value} question.");
        }
    }

    /** The 1-based position of the first correct option, or, for a weak candidate, of the first option that is not correct. */
    private function position(Question $question, bool $correct): int
    {
        foreach ($question->options as $index => $option) {
            if ($option->isCorrect === $correct) {
                return $index + 1;
            }
        }

        return 1;
    }

    /**
     * @return list<int> every correct position for a strong candidate, the first one only for a weak one
     */
    private function correctPositions(Question $question, bool $strong): array
    {
        $positions = [];

        foreach ($question->options as $index => $option) {
            if ($option->isCorrect) {
                $positions[] = $index + 1;
            }
        }

        return $strong ? $positions : array_slice($positions, 0, 1);
    }

    private function accepted(Question $question): int|float
    {
        return (float) str_replace(',', '.', $this->acceptedText($question));
    }

    private function acceptedText(Question $question): string
    {
        foreach ($question->options as $option) {
            if ($option->isCorrect) {
                return $option->label;
            }
        }

        throw new LogicException("The question {$question->id} has no accepted answer.");
    }

    /** An assessor scores each long-text answer with four fifths of its points. */
    private function scoreCaseStudy(Actor $assessor, int $invitationId): void
    {
        foreach ($this->scoringAnswers->execute($assessor, $invitationId) as $item) {
            $this->score->execute($assessor, $invitationId, $item->questionId, (int) round($item->points * 0.8));
        }
    }
}
