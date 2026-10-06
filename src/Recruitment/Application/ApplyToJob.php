<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationAnswerChecker;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\ApplicationResume;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Recruitment\Domain\JobQuestionRepository;
use Src\Recruitment\Domain\StageRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Applicant\ApplicantProfileSource;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Throwable;

/**
 * A job seeker applies to a live public job. The application keeps its own copy of the chosen resume and a snapshot of the profile as it is now, so
 * what the recruiter sees never changes under them. The applicant is locked first, so two requests of the same person (and the daily count) run one
 * after the other; the unique key of the application is the last line of defence against a duplicate.
 */
final readonly class ApplyToJob
{
    public const int COVER_LETTER_MAX = 2000;

    private const string TIME_ZONE = 'Asia/Jakarta';

    public function __construct(
        private ApplicantGuard $guard,
        private JobDirectory $jobs,
        private ApplicantProfileSource $applicants,
        private ApplicantContacts $contacts,
        private ApplicationRepository $applications,
        private JobQuestionRepository $jobQuestions,
        private StageRepository $stages,
        private ScreeningSwitch $screening,
        private Settings $settings,
        private FileStorage $files,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound the job is not live
     * @throws StateConflict `already-applied`
     * @throws TooManyAttempts `application-limit-reached`
     * @throws BusinessRuleViolation `profile-incomplete`, `invalid-application`, `invalid-answers`
     */
    public function execute(Actor $actor, int $jobId, ApplyToJobInput $input): Application
    {
        $userId = $this->guard->userId($actor);
        $companyId = $this->jobs->publicCompanyId($jobId) ?? throw EntityNotFound::of('Job', $jobId);
        $coverLetter = $this->coverLetter($input->coverLetter);
        $storedPath = null;

        try {
            $application = $this->transactions->run(function () use ($userId, $jobId, $companyId, $input, $coverLetter, &$storedPath): Application {
                $this->applicants->lock($userId);

                if ($this->applications->hasLiveApplication($userId, $jobId)) {
                    throw new StateConflict('You have already applied to this job.', 'already-applied');
                }

                $this->assertBelowDailyLimit($userId);

                $submission = $this->applicants->forApplication($userId, $input->resumeId);

                if ($submission->missing !== []) {
                    throw new BusinessRuleViolation('Complete your profile before applying.', 'profile-incomplete', extensions: ['missing' => $submission->missing]);
                }

                if ($submission->resume === null) {
                    throw new BusinessRuleViolation('Choose one of your own resumes.', 'invalid-application', extensions: ['errors' => ['resume_id' => ['Choose one of your own resumes.']]]);
                }

                $questions = $this->screening->isOn($companyId) ? $this->jobQuestions->forJob($jobId) : [];
                $checked = ApplicationAnswerChecker::check($questions, $input->answers);

                $appliedStage = null;

                foreach ($this->stages->forCompany($companyId) as $stage) {
                    if ($stage->isDefault && $stage->type === StageType::Applied) {
                        $appliedStage = $stage;
                    }
                }

                if ($appliedStage?->id === null) {
                    throw new StateConflict('This company cannot take applications right now.', 'applications-unavailable');
                }

                $contact = $this->contacts->contact($userId) ?? throw EntityNotFound::of('Account', $userId);

                $storedPath = 'recruitment/applications/'.bin2hex(random_bytes(20)).'.pdf';
                $this->files->put($storedPath, $submission->resume->contents);

                return $this->applications->add(
                    Application::submit(
                        $userId,
                        $jobId,
                        $companyId,
                        $appliedStage->id,
                        $coverLetter,
                        new ApplicationResume($submission->resume->name, $storedPath, strlen($submission->resume->contents)),
                        ['name' => $contact->name, 'email' => $contact->email] + $submission->snapshot,
                        $checked->meetsMustHave,
                        $this->clock->now(),
                    ),
                    $checked->answers,
                );
            });
        } catch (Throwable $e) {
            // The copy of the resume is written inside the transaction; when anything fails afterwards it must not stay behind.
            if ($storedPath !== null) {
                $this->files->delete($storedPath);
            }

            throw $e;
        }

        $this->events->dispatch([new ApplicationSubmitted((int) $application->id, $jobId, $companyId, $this->clock->now())]);

        return $application;
    }

    private function coverLetter(?string $text): ?string
    {
        $text = $text === null ? null : trim($text);

        if ($text === null || $text === '') {
            return null;
        }

        if (mb_strlen($text) > self::COVER_LETTER_MAX) {
            $message = 'The cover letter can have at most '.self::COVER_LETTER_MAX.' characters.';

            throw new BusinessRuleViolation($message, 'invalid-application', extensions: ['errors' => ['cover_letter' => [$message]]]);
        }

        return $text;
    }

    private function assertBelowDailyLimit(int $userId): void
    {
        $now = $this->clock->now();
        $zone = new DateTimeZone(self::TIME_ZONE);
        $dayStart = (new DateTimeImmutable($now->setTimezone($zone)->format('Y-m-d'), $zone));

        if ($this->applications->countSubmittedSince($userId, $dayStart) < $this->settings->integer(RecruitmentSettings::MAX_APPLICATIONS_PER_DAY)) {
            return;
        }

        $retryAfter = max(1, $dayStart->modify('+1 day')->getTimestamp() - $now->getTimestamp());

        throw new TooManyAttempts('You have reached the daily limit of applications. Try again tomorrow.', $retryAfter, 'application-limit-reached');
    }
}
