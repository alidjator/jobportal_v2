<?php

namespace Src\JobSeeker\Application;

use Psr\Log\LoggerInterface;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Throwable;

/** A job seeker deletes one of their resumes. The row goes first and the file last; a failure to delete the file only leaves a stray file behind. */
final readonly class DeleteMyResume
{
    public function __construct(
        private JobSeekerGuard $guard,
        private ResumeRepository $resumes,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): void
    {
        $userId = $this->guard->userId($actor);
        $resume = $this->resumes->find($userId, $id) ?? throw EntityNotFound::of('Resume', $id);

        $this->resumes->delete($userId, $id);

        try {
            $this->files->delete($resume->path);
        } catch (Throwable $e) {
            $this->logger->warning('A resume file could not be deleted.', ['path' => $resume->path, 'exception' => $e]);
        }
    }
}
