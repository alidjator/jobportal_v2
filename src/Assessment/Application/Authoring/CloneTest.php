<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Application\AssessmentSettings;
use Src\Assessment\Domain\NormRepository;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionImage;
use Src\Assessment\Domain\QuestionImageRepository;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\RecommendationRuleRepository;
use Src\Assessment\Domain\ReportTemplateRepository;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\SectionRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/** The author copies one of their own tests, with its sections, questions, norms and report template, into a new draft. */
final readonly class CloneTest
{
    public function __construct(
        private AuthoringAccess $access,
        private TestRepository $tests,
        private SectionRepository $sections,
        private QuestionRepository $questions,
        private QuestionImageRepository $pictures,
        private QuestionImages $images,
        private FileStorage $files,
        private NormRepository $norms,
        private RecommendationRuleRepository $recommendationRules,
        private ReportTemplateRepository $templates,
        private Settings $settings,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-limit-reached`
     */
    public function execute(Actor $actor, int $testId): Test
    {
        $author = $this->access->author($actor);

        $copied = [];

        try {
            return $this->transactions->run(function () use ($author, $testId, &$copied): Test {
                $source = $this->access->ownedTest($author, $testId);
                $companyId = $author->owner->companyId;

                if ($companyId !== null && $this->tests->countLiveOfCompany($companyId) >= $this->settings->integer(AssessmentSettings::MAX_COMPANY_TESTS, $companyId)) {
                    throw new StateConflict('The company has reached its limit of skill tests. Archive or delete one first.', 'test-limit-reached');
                }

                $copy = $this->tests->save(Test::draft(
                    $author->owner,
                    mb_substr($source->title(), 0, 190).' (copy)',
                    $source->description(),
                    $source->instructions(),
                    $source->capabilities(),
                    $author->userId,
                    $author->adminId,
                ));

                foreach ($this->sections->ofTest((int) $source->id) as $section) {
                    $newSection = $this->sections->add(new Section(
                        null, (int) $copy->id, 0, $section->title, $section->instructions, $section->format, $section->durationSeconds,
                        $section->allowSkip, $section->allowBack, $section->allowJump, $section->drawCount, $section->settings,
                    ));

                    foreach ($this->questions->ofSection((int) $section->id) as $question) {
                        $new = $this->questions->add(new Question(
                            null, (int) $newSection->id, 0, $question->type, $question->text, $question->points, $question->scaleKey, $question->isSample, $question->options, $question->thinkSeconds, $question->answerSeconds, $question->maxRetakes,
                        ));

                        foreach ($this->pictures->ofQuestion((int) $question->id) as $picture) {
                            $this->copyPicture($picture, (int) $copy->id, (int) $new->id, $copied);
                        }
                    }
                }

                $this->norms->replace((int) $copy->id, $this->norms->ofTest((int) $source->id));
                $this->recommendationRules->replace((int) $copy->id, $this->recommendationRules->ofTest((int) $source->id));
                $body = $this->templates->bodyOf((int) $source->id);

                if ($body !== null) {
                    $this->templates->save((int) $copy->id, $body, $this->clock->now());
                }

                $explanation = $this->templates->explanationTemplateOf((int) $source->id);

                if ($explanation !== null) {
                    $this->templates->saveExplanationTemplate((int) $copy->id, $explanation, $this->clock->now());
                }

                return $copy;
            });
        } catch (Throwable $e) {
            $this->images->discard($copied);

            throw $e;
        }
    }

    /**
     * Copies the picture with a file of its own, so deleting one test never breaks the other.
     *
     * @param  list<string>  $copied  the paths written so far, to delete if the copy fails
     */
    private function copyPicture(QuestionImage $picture, int $testId, int $questionId, array &$copied): void
    {
        $contents = $this->files->get($picture->path);

        if ($contents === null) {
            return;
        }

        $path = 'assessment/question-images/'.$testId.'/'.bin2hex(random_bytes(16)).'.'.pathinfo($picture->path, PATHINFO_EXTENSION);
        $this->files->put($path, $contents);
        $copied[] = $path;
        $this->pictures->create($picture->copiedTo($testId, $questionId, $path));
    }
}
