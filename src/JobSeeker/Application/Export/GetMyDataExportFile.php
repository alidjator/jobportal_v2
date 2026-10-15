<?php

namespace Src\JobSeeker\Application\Export;

use Psr\Log\LoggerInterface;
use Src\JobSeeker\Application\JobSeekerGuard;
use Src\JobSeeker\Domain\DataExportRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The archive of the caller's latest copy, while it is ready and has not expired. */
final readonly class GetMyDataExportFile
{
    public function __construct(
        private JobSeekerGuard $guard,
        private DataExportRepository $exports,
        private FileStorage $files,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return string the bytes of the ZIP archive
     *
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor): string
    {
        $userId = $this->guard->userId($actor);
        $export = $this->exports->latestOf($userId);

        if ($export === null || ! $export->isDownloadable($this->clock->now())) {
            throw EntityNotFound::of('DataExport', $userId);
        }

        $contents = $this->files->get((string) $export->path);

        if ($contents === null) {
            $this->logger->error('The archive of a personal data export is missing.', ['export_id' => $export->id]);
            throw EntityNotFound::of('DataExport', $userId);
        }

        return $contents;
    }
}
