<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Application\Authoring\QuestionImageFile;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionImage;
use Src\Assessment\Domain\QuestionImageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A picture of a practice question (`docs/design/phase-8-question-images.md` G1): the same access as {@see GetPractice} (the candidate's own invitation to a skill test that can still be taken), and only a sample
 * question of that test. The samples are not drawn into attempts, so showing their pictures gives nothing away; no attempt or timer is involved.
 */
final readonly class GetPracticeQuestionImage
{
    public function __construct(private GetPractice $practice, private QuestionImageRepository $images, private FileStorage $files) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $invitationId, int $questionId, int $imageId): QuestionImageFile
    {
        $question = $this->sample($this->practice->execute($actor, $invitationId), $questionId);
        $image = $this->images->find($imageId);

        if (! $image instanceof QuestionImage || $image->questionId !== (int) $question->id) {
            throw EntityNotFound::of('Image', $imageId);
        }

        return new QuestionImageFile($this->files->get($image->path) ?? throw EntityNotFound::of('Image', $imageId), $image->contentType);
    }

    private function sample(PracticeView $view, int $questionId): Question
    {
        foreach ($view->sections as $section) {
            foreach ($section->questions as $question) {
                if ($question->id === $questionId) {
                    return $question;
                }
            }
        }

        throw EntityNotFound::of('Question', $questionId);
    }
}
