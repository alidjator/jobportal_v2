<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationAnswerChecker;
use Src\Recruitment\Domain\ApplicationFacetRepository;
use Src\Recruitment\Domain\ApplicationJobSnapshots;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\ApplicationResume;
use Src\Recruitment\Domain\ApplicationSource;
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
use Src\Shared\Application\JobInvitation\InvitedCandidates;
use Src\Shared\Application\Matching\ApplicantMatchScores;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Throwable;

/**
 * A job seeker applies to a live public job. The application keeps its own copy of the chosen resume, a snapshot of the profile as it is now and a copy of the job's content as the candidate read it, so
 * what the recruiter sees never changes under them. The applicant is locked first, so two requests of the same person (and the daily count) run one
 * after the other, then the job, so a job that is closed meanwhile takes no application; the unique key of the application is the last line of defence against a duplicate. It also records where the applicant came from (an invitation
 * wins over the hint of the client) and how well they fitted the job, for the analytics.
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
        private InvitedCandidates $invitations,
        private ApplicantMatchScores $matchScores,
        private ApplicationRepository $applications,
        private ApplicationFacetRepository $facets,
        private ApplicationFacetBuilder $facetBuilder,
        private ApplicationJobSnapshots $jobSnapshots,
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
     * @throws StateConflict `already-applied` or `job-on-hold`
     * @throws TooManyAttempts `application-limit-reached` or `cv-limit`
     * @throws BusinessRuleViolation `profile-incomplete`, `invalid-application`, `invalid-answers`, `template-unavailable`, `invalid-cv`
     */
    public function execute(Actor $actor, int $jobId, ApplyToJobInput $input): Application
    {
        $userId = $this->guard->userId($actor);
        $companyId = $this->liveCompanyId($jobId);
        $coverLetter = $this->coverLetter($input->coverLetter);
        $storedPath = null;

        if (($input->resumeId === null) === ($input->cv === null)) {
            throw new BusinessRuleViolation('Choose one of your own resumes or a CV to generate.', 'invalid-application', extensions: ['errors' => ['resume_id' => ['Choose one of your own resumes or a CV to generate.']]]);
        }

        // The CV is rendered before the transaction, so the locks are not held while the PDF is built. The checks that would make the request fail run first,
        // so it does not spend a PDF or one of the hourly CVs; they run again under the locks, where they decide.
        $generated = null;

        if ($input->cv !== null) {
            $this->assertCanApply($userId, $jobId);
            $generated = $this->applicants->generateCv($userId, $input->cv);
        }

        try {
            $application = $this->transactions->run(function () use ($userId, $jobId, $input, $coverLetter, $generated, &$storedPath): Application {
                $this->applicants->lock($userId);
                // Closing, expiring or taking down a job locks it, so the job is checked again once nothing can change it under this application.
                $this->jobs->lock($jobId);
                $companyId = $this->liveCompanyId($jobId);

                $this->assertCanApply($userId, $jobId);

                $submission = $input->cv !== null
                    ? $this->applicants->forApplicationWithResume($userId, $generated)
                    : $this->applicants->forApplication($userId, (int) $input->resumeId);

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

                $appliedAt = $this->clock->now();
                $source = $this->invitations->jobIdsOf($userId, [$jobId]) === [] ? ApplicationSource::fromHint($input->source) : ApplicationSource::Invitation;
                $snapshot = ['name' => $contact->name, 'email' => $contact->email] + $submission->snapshot;

                $application = $this->applications->add(
                    Application::submit(
                        $userId,
                        $jobId,
                        $companyId,
                        $appliedStage->id,
                        $coverLetter,
                        new ApplicationResume($submission->resume->name, $storedPath, strlen($submission->resume->contents)),
                        $snapshot,
                        $checked->meetsMustHave,
                        $appliedAt,
                        $source,
                        $this->matchScores->percent($userId, $jobId),
                        $input->talentPoolConsent ? $appliedAt : null,
                    ),
                    $checked->answers,
                );

                // What the candidate read when they applied, kept so they can read it again after the job closes.
                $content = $this->jobs->contentSnapshot($jobId);

                if ($content !== null) {
                    $this->jobSnapshots->save((int) $application->id, $content);
                }

                $this->facets->save(
                    (int) $application->id,
                    $companyId,
                    $jobId,
                    $appliedAt,
                    $this->facetBuilder->build($snapshot, $appliedAt, $source === ApplicationSource::Invitation, $this->facets->hasEarlierApplication($userId, $companyId, $appliedAt)),
                );

                return $application;
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

    /** @throws EntityNotFound the job is not live */
    private function liveCompanyId(int $jobId): int
    {
        $companyId = $this->jobs->publicCompanyId($jobId);

        if ($companyId !== null) {
            return $companyId;
        }

        if ($this->jobs->isOnHold($jobId)) {
            throw new StateConflict('This job is under review and takes no applications right now.', 'job-on-hold');
        }

        throw EntityNotFound::of('Job', $jobId);
    }

    /**
     * @throws StateConflict `already-applied`
     * @throws TooManyAttempts `application-limit-reached`
     */
    private function assertCanApply(int $userId, int $jobId): void
    {
        if ($this->applications->hasLiveApplication($userId, $jobId)) {
            throw new StateConflict('You have already applied to this job.', 'already-applied');
        }

        $this->assertBelowDailyLimit($userId);
    }

    private function coverLetter(?string $text): ?string
    {
        $text = $text === null ? null : trim($text);

        if ($text === null || $text === '') {
            return null;
        }

        if (mb_strlen($text) > self::COVER_LETTER_MAX) {
            $message = 'The cover letter can have at most :max characters.';

            throw new BusinessRuleViolation($message, 'invalid-application', extensions: ['errors' => ['cover_letter' => [$message]]], parameters: ['max' => self::COVER_LETTER_MAX]);
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
