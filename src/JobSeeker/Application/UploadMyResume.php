<?php

namespace Src\JobSeeker\Application;

use Psr\Log\LoggerInterface;
use Src\JobSeeker\Domain\ProfileRepository;
use Src\JobSeeker\Domain\Resume;
use Src\JobSeeker\Domain\ResumePdf;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * A job seeker adds a PDF resume: at most 5, no larger than the admin setting, checked by its content. The file is stored first under a random name and the
 * database changed next; if that fails the new file is deleted again. The profile row is locked, so two simultaneous uploads cannot both pass the limit.
 */
final readonly class UploadMyResume
{
    private const string DIRECTORY = 'jobseeker/resumes';

    public function __construct(
        private JobSeekerGuard $guard,
        private ProfileRepository $profiles,
        private ResumeRepository $resumes,
        private Settings $settings,
        private FileStorage $files,
        private TransactionManager $transactions,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws BusinessRuleViolation `file-too-large` or `invalid-resume`
     * @throws StateConflict `resume-limit-reached`
     */
    public function execute(Actor $actor, string $contents, string $originalName): Resume
    {
        $userId = $this->guard->userId($actor);
        $maxKb = $this->settings->integer(JobSeekerSettings::RESUME_MAX_SIZE_KB);

        if (strlen($contents) > $maxKb * 1024) {
            throw new BusinessRuleViolation("The file is larger than the allowed {$maxKb} KB.", 'file-too-large', extensions: ['max_size_kb' => $maxKb]);
        }

        ResumePdf::check($contents);

        $path = self::DIRECTORY.'/'.bin2hex(random_bytes(16)).'.pdf';
        $now = $this->clock->now();

        $this->files->put($path, $contents);

        try {
            $id = $this->transactions->run(function () use ($userId, $path, $originalName, $contents, $now): int {
                $this->profiles->lock($userId, $now);

                if ($this->resumes->count($userId) >= ResumePdf::RESUMES_MAX) {
                    throw new StateConflict('A profile can have at most '.ResumePdf::RESUMES_MAX.' resumes.', 'resume-limit-reached');
                }

                return $this->resumes->add(new Resume($userId, ResumePdf::cleanName($originalName), $path, strlen($contents), $now));
            });
        } catch (Throwable $e) {
            $this->deleteQuietly($path);

            throw $e;
        }

        return $this->resumes->find($userId, $id) ?? throw new \LogicException('The new resume was not found.');
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('A resume file could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
