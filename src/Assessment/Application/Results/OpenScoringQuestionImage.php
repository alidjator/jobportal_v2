<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Application\Authoring\QuestionImageFile;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A picture of a question the assessor scores (`docs/design/phase-8-question-images.md` G3): the same access as the scoring list (an assessor of the invitation, after the attempt was submitted) and only a question that list shows. */
final readonly class OpenScoringQuestionImage
{
    public function __construct(private ScoringAccess $access, private FileStorage $files) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-an-assessor`
     * @throws EntityNotFound
     * @throws StateConflict `attempt-not-submitted`
     */
    public function execute(Actor $actor, int $invitationId, int $questionId, int $imageId): QuestionImageFile
    {
        $invitation = $this->access->invitation($actor, $invitationId);

        foreach ($this->access->scorable($this->access->submittedAttempt($invitation)) as $entry) {
            if ((int) $entry['question']->id !== $questionId) {
                continue;
            }

            foreach ($entry['question']->images as $image) {
                if ($image->id === $imageId) {
                    return new QuestionImageFile($this->files->get($image->path) ?? throw EntityNotFound::of('Image', $imageId), $image->contentType);
                }
            }
        }

        throw EntityNotFound::of('Image', $imageId);
    }
}
