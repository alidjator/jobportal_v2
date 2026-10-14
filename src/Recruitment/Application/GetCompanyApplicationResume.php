<?php

namespace Src\Recruitment\Application;

use Psr\Log\LoggerInterface;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The resume copy of an application for a member who may see its job. An application whose copy was erased (the account was anonymised) or whose file is
 * missing answers as not found; a missing file is also reported.
 */
final readonly class GetCompanyApplicationResume
{
    public function __construct(
        private RecruitmentAccess $access,
        private CompanyApplicationAccess $applicationAccess,
        private ApplicationRepository $applications,
        private FileStorage $files,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $id): ApplicationFile
    {
        $member = $this->access->viewer($actor);
        $application = $this->applications->find($id) ?? throw EntityNotFound::of('Application', $id);
        $this->applicationAccess->assertCanSee($member, $id, $application->companyId, $application->jobId);

        $resume = $application->resume ?? throw EntityNotFound::of('Resume', $id);
        $contents = $this->files->get($resume->path);

        if ($contents === null) {
            $this->logger->error('The resume copy of an application is missing.', ['application_id' => $id, 'path' => $resume->path]);

            throw EntityNotFound::of('Resume', $id);
        }

        return new ApplicationFile($contents, $resume->name);
    }
}
