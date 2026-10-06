<?php

namespace Src\Recruitment\Application;

use Psr\Log\LoggerInterface;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The resume copy of one of the applicant's own applications. An application whose copy was erased (the account is being anonymised) or whose file is
 * missing answers as not found; a missing file is also reported.
 */
final readonly class GetMyApplicationResume
{
    public function __construct(
        private ApplicantGuard $guard,
        private ApplicationRepository $applications,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): ApplicationFile
    {
        $application = $this->applications->findOfApplicant($this->guard->userId($actor), $id) ?? throw EntityNotFound::of('Application', $id);
        $resume = $application->resume ?? throw EntityNotFound::of('Resume', $id);
        $contents = $this->files->get($resume->path);

        if ($contents === null) {
            $this->logger->error('The resume copy of an application is missing.', ['application_id' => $id, 'path' => $resume->path]);

            throw EntityNotFound::of('Resume', $id);
        }

        return new ApplicationFile($contents, $resume->name);
    }
}
