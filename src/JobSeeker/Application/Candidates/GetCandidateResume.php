<?php

namespace Src\JobSeeker\Application\Candidates;

use Psr\Log\LoggerInterface;
use Src\JobSeeker\Application\ResumeFile;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A resume of a candidate who lets companies see them; every download is logged. A candidate who does not, or a resume that is not theirs, answers as not found. */
final readonly class GetCandidateResume
{
    public function __construct(
        private CandidateAccess $access,
        private CandidateReader $candidates,
        private ProfileRepository $profiles,
        private ResumeRepository $resumes,
        private FileStorage $files,
        private ProfileViews $views,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, string $slug, int $resumeId): ResumeFile
    {
        $viewer = $this->access->viewer($actor);
        $row = $this->candidates->findBySlug($slug) ?? throw EntityNotFound::of('Candidate', $slug);

        if (! ($this->profiles->find($row->userId)->resumeDiscoverable ?? false)) {
            throw EntityNotFound::of('Resume', $resumeId);
        }

        $resume = $this->resumes->find($row->userId, $resumeId) ?? throw EntityNotFound::of('Resume', $resumeId);
        $contents = $this->files->get($resume->path);

        if ($contents === null) {
            $this->logger->error('The file of a resume is missing.', ['resume_id' => $resumeId, 'path' => $resume->path]);

            throw EntityNotFound::of('Resume', $resumeId);
        }

        $this->views->resumeDownloaded($viewer, $row->userId);

        return new ResumeFile($contents, $resume->name);
    }
}
