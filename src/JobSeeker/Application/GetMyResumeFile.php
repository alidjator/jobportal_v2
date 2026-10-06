<?php

namespace Src\JobSeeker\Application;

use Psr\Log\LoggerInterface;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The file of one of the caller's own resumes. Another account's resume does not exist; a resume whose file is missing is reported and answered as not found. */
final readonly class GetMyResumeFile
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
    public function execute(Actor $actor, int $id): ResumeFile
    {
        $userId = $this->guard->userId($actor);
        $resume = $this->resumes->find($userId, $id) ?? throw EntityNotFound::of('Resume', $id);
        $contents = $this->files->get($resume->path);

        if ($contents === null) {
            $this->logger->error('The file of a resume is missing.', ['resume_id' => $id, 'path' => $resume->path]);

            throw EntityNotFound::of('Resume', $id);
        }

        return new ResumeFile($contents, $resume->name);
    }
}
