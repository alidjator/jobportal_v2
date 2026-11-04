<?php

namespace Src\Assessment\Application\Authoring;

use Src\Assessment\Domain\QuestionImage;
use Src\Assessment\Domain\QuestionImageRepository;
use Src\Assessment\Domain\SectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The author removes a section with its questions. */
final readonly class DeleteSection
{
    public function __construct(
        private AuthoringAccess $access,
        private SectionRepository $sections,
        private QuestionImageRepository $pictures,
        private QuestionImages $images,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied see `AuthoringAccess::author`
     * @throws EntityNotFound
     * @throws StateConflict `test-archived` or `test-locked`
     */
    public function execute(Actor $actor, int $sectionId): void
    {
        $author = $this->access->author($actor);

        $paths = $this->transactions->run(function () use ($author, $sectionId): array {
            $section = $this->sections->find($sectionId) ?? throw EntityNotFound::of('Section', $sectionId);
            $this->access->editableTest($author, $section->testId);

            $paths = array_map(static fn (QuestionImage $image): string => $image->path, $this->pictures->ofSection($sectionId));
            $this->sections->delete($sectionId);

            return $paths;
        });

        $this->images->discard($paths);
    }
}
