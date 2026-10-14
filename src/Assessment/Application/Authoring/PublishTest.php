<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\NormRepository;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\ReportTemplateRepository;
use Src\Assessment\Domain\SectionFormat;
use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The author publishes a draft once it is complete: every section has what its format needs, and a battery also has a norm for every scale
 * its questions use and a report template (and, when it issues reports automatically, what that needs: see `AutoIssueChecks`). A test that is not complete is refused with every problem listed.
 */
final readonly class PublishTest
{
    public function __construct(
        private AuthoringAccess $access,
        private TestRepository $tests,
        private SectionRepository $sections,
        private QuestionRepository $questions,
        private NormRepository $norms,
        private ReportTemplateRepository $templates,
        private AutoIssueChecks $autoIssue,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-not-draft`
     * @throws BusinessRuleViolation `test-incomplete` with `errors` on `test`
     */
    public function execute(Actor $actor, int $testId): Test
    {
        $author = $this->access->author($actor);
        $now = $this->clock->now();

        return $this->transactions->run(function () use ($author, $testId, $now): Test {
            $test = $this->access->ownedTest($author, $testId, true);

            if (! $test->isDraft()) {
                throw new StateConflict('Only a draft can be published.', 'test-not-draft');
            }

            $problems = $this->problems($test);

            if ($problems !== []) {
                throw new BusinessRuleViolation('The test is not complete.', 'test-incomplete', extensions: ['errors' => ['test' => $problems]]);
            }

            $test->publish($now);

            return $this->tests->save($test);
        });
    }

    /** @return list<string> */
    private function problems(Test $test): array
    {
        $problems = [];
        $sections = $this->sections->ofTest((int) $test->id);
        $counts = $this->questions->countsBySection((int) $test->id);

        if ($sections === []) {
            $problems[] = 'The test needs at least one section.';
        }

        foreach ($sections as $section) {
            $count = $counts[(int) $section->id] ?? 0;

            if ($section->format !== SectionFormat::SpeedColumns && $count === 0) {
                $problems[] = "The section \"{$section->title}\" needs at least one question.";
            }

            if ($section->format === SectionFormat::Video) {
                $needed = array_sum(array_map(static fn (Question $question): int => $question->minimumSeconds(), $this->questions->ofSection((int) $section->id)));

                if ($section->durationSeconds < $needed) {
                    $problems[] = "The section \"{$section->title}\" needs at least {$needed} seconds for its video questions (the limited think times plus every take at full length) but lasts {$section->durationSeconds}.";
                }
            }

            if ($section->drawCount !== null && $section->drawCount > $count) {
                $problems[] = "The section \"{$section->title}\" draws {$section->drawCount} questions but has only {$count}.";
            }
        }

        if ($test->kind() === TestKind::Psychological) {
            $normed = array_unique(array_map(static fn ($norm): string => $norm->scaleKey, $this->norms->ofTest((int) $test->id)));

            foreach (array_diff($this->questions->scalesOfTest((int) $test->id), $normed) as $scale) {
                $problems[] = "The scale {$scale} has no norm.";
            }

            if ($this->templates->bodyOf((int) $test->id) === null) {
                $problems[] = 'The battery needs a report template.';
            }

            if ($test->autoIssue()) {
                $problems = [...$problems, ...$this->autoIssue->problems($test)];
            }
        }

        return $problems;
    }
}
