<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\QuestionImageRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The author reads a picture of their own test (to preview it in the editor), attached or not. */
final readonly class GetQuestionImage
{
    public function __construct(private AuthoringAccess $access, private QuestionImageRepository $images, private FileStorage $files) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $testId, int $imageId): QuestionImageFile
    {
        $author = $this->access->author($actor);
        $this->access->ownedTest($author, $testId);

        $image = $this->images->find($imageId);

        if ($image === null || $image->testId !== $testId) {
            throw EntityNotFound::of('Image', $imageId);
        }

        return new QuestionImageFile($this->files->get($image->path) ?? throw EntityNotFound::of('Image', $imageId), $image->contentType);
    }
}
