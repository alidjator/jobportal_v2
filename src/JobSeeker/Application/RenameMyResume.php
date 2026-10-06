<?php

namespace Src\JobSeeker\Application;

use Src\JobSeeker\Domain\Resume;
use Src\JobSeeker\Domain\ResumePdf;
use Src\JobSeeker\Domain\ResumeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A job seeker changes the display name of one of their resumes: 1 to 100 characters, without a path or control characters. */
final readonly class RenameMyResume
{
    public function __construct(private JobSeekerGuard $guard, private ResumeRepository $resumes) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-resume` with `errors` on `name`
     */
    public function execute(Actor $actor, int $id, string $name): Resume
    {
        $userId = $this->guard->userId($actor);
        $resume = $this->resumes->find($userId, $id) ?? throw EntityNotFound::of('Resume', $id);

        $clean = ResumePdf::cleanName($name);

        if (trim($name) === '' || mb_strlen(trim($name)) > ResumePdf::NAME_MAX_LENGTH || $clean !== trim($name)) {
            throw new BusinessRuleViolation(
                'The name must have between 1 and '.ResumePdf::NAME_MAX_LENGTH.' characters and no path or control characters.',
                'invalid-resume',
                extensions: ['errors' => ['name' => ['The name must have between 1 and '.ResumePdf::NAME_MAX_LENGTH.' characters and no path or control characters.']]],
            );
        }

        $this->resumes->rename($userId, $id, $clean);

        return new Resume($resume->userId, $clean, $resume->path, $resume->sizeBytes, $resume->createdAt, $resume->id);
    }
}
