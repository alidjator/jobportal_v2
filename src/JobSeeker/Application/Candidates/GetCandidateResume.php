<?php

namespace Src\JobSeeker\Application\Candidates;

use Psr\Log\LoggerInterface;
use Src\JobSeeker\Application\ResumeFile;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Audit\AuditAction;
use Src\Shared\Application\Audit\AuditActorType;
use Src\Shared\Application\Audit\AuditEntry;
use Src\Shared\Application\Audit\AuditTrail;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A resume of a candidate who lets companies see them; every download is logged (for the candidate's views, and in the audit log as `candidate.resume_downloaded` for the company activity log). A candidate who does not, or a resume that is not theirs, answers as not found. */
final readonly class GetCandidateResume
{
    public function __construct(
        private CandidateAccess $access,
        private CandidateReader $candidates,
        private ProfileRepository $profiles,
        private ResumeRepository $resumes,
        private FileStorage $files,
        private ProfileViews $views,
        private AuditTrail $audit,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, string $slug, int $resumeId, ?string $ipAddress = null, ?string $userAgent = null): ResumeFile
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
        $this->audit->record(new AuditEntry(
            AuditActorType::User, $actor->id, AuditAction::CANDIDATE_RESUME_DOWNLOADED, 'resume', $resumeId, ['candidate' => $slug], $ipAddress, $userAgent, $this->clock->now(), $viewer->companyId,
        ));

        return new ResumeFile($contents, $resume->name);
    }
}
