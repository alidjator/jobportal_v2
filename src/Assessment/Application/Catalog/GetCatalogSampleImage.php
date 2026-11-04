<?php

namespace Src\Assessment\Application\Catalog;

use Src\Assessment\Application\Authoring\QuestionImageFile;
use Src\Assessment\Domain\QuestionImage;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A picture of a sample question of a catalogue test (`docs/design/phase-8-question-images.md` G2): the same access as {@see GetCatalogTest} and only a sample the author chose to show; a picture of any other
 * question of the test stays hidden, like the question itself.
 */
final readonly class GetCatalogSampleImage
{
    public function __construct(private GetCatalogTest $catalog, private FileStorage $files) {}

    /**
     * @throws AccessDenied see `GetCatalogTest`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $testId, int $questionId, int $imageId): QuestionImageFile
    {
        foreach ($this->catalog->execute($actor, $testId)->samples as $sample) {
            if ((int) $sample->id === $questionId) {
                foreach ([...$sample->images, ...array_map(static fn ($option) => $option->image, $sample->options)] as $image) {
                    if ($image instanceof QuestionImage && $image->id === $imageId) {
                        return new QuestionImageFile($this->files->get($image->path) ?? throw EntityNotFound::of('Image', $imageId), $image->contentType);
                    }
                }
            }
        }

        throw EntityNotFound::of('Image', $imageId);
    }
}
