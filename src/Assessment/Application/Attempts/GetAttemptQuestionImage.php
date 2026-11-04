<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Application\Authoring\QuestionImageFile;
use Src\Assessment\Domain\QuestionImageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A picture of a question, for the candidate who is taking the test: only while the attempt is open and only for a question the section lets them see now (the same rule as answering it). It is a read, so it
 * neither extends the idle timer nor needs the attempt session token: the picture is an `<img>` that cannot send the header.
 */
final readonly class GetAttemptQuestionImage
{
    public function __construct(
        private AttemptGate $gate,
        private AttemptQuestions $reach,
        private QuestionImageRepository $images,
        private FileStorage $files,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws StateConflict `attempt-not-started` or `question-locked`
     */
    public function execute(Actor $actor, int $invitationId, int $questionId, int $imageId, ?string $ip): QuestionImageFile
    {
        return $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use ($questionId, $imageId): QuestionImageFile {
            $question = $this->reach->reachable($context, $questionId);
            $image = $this->images->find($imageId);

            if ($image === null || $image->questionId !== (int) $question->id) {
                throw EntityNotFound::of('Image', $imageId);
            }

            return new QuestionImageFile($this->files->get($image->path) ?? throw EntityNotFound::of('Image', $imageId), $image->contentType);
        }, readOnly: true, checkSession: false);
    }
}
