<?php

namespace Src\Recruitment\Application;

use LogicException;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\OfferRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Application\Assessment\AssessmentInvitations;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Email\EmailPreviewer;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Shows the email an action would send to the applicant before the recruiter does it (`phase-8-interview-calendar.md` section 5): the interview (new time or only the
 * note), the test and the offer. The request is checked by the rules of the real action, so the same errors come back. A new interview time and an offer run the real code in
 * a transaction that is rolled back (their rules are part of saving them); a test and a note only check. Nothing is saved, sent or audited. The member needs the
 * permission of the action itself.
 */
final readonly class PreviewApplicantEmail
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private InterviewScheduler $scheduler,
        private InterviewRepository $interviews,
        private OfferPlacer $offerPlacer,
        private OfferRepository $offers,
        private AssessmentInvitations $assessments,
        private ApplicantContacts $contacts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private CandidateMessageRules $messageRules,
        private NotificationPreferences $preferences,
        private EmailPreviewer $previewer,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `applicant-unavailable`
     * @throws BusinessRuleViolation the codes of scheduling an interview
     */
    public function interview(Actor $actor, int $applicationId, InterviewInput $input): EmailPreview
    {
        $contact = null;
        $userId = 0;

        $draft = $this->rolledBack(function () use ($actor, $applicationId, $input, &$contact, &$userId): array {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::InterviewsManage);
            $contact = $this->contactOf($team->application);
            $userId = $team->application->userId;
            $event = $this->scheduler->schedule($team->member, $team->application, $input);
            $interview = $this->interviews->findById($event->interviewId) ?? throw new LogicException('The previewed interview was not found.');
            [$jobTitle, $companyName] = $this->names($team->application);
            $key = RecruitmentEmailTemplates::keyForInterview($event->change) ?? throw new LogicException('A scheduled interview has no email.');

            return [$key, InterviewEmailVariables::of($interview, $contact, $jobTitle, $companyName, $team->application->companyId)];
        });

        return $this->render($draft, $contact, $userId, calendarAttached: true);
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `interview-not-active` or `applicant-unavailable`
     * @throws BusinessRuleViolation `invalid-interview` or `message-link-not-allowed`
     */
    public function interviewNote(Actor $actor, int $applicationId, ?string $note): EmailPreview
    {
        $team = $this->lookup->find($actor, $applicationId, permission: CompanyPermission::InterviewsManage);
        $contact = $this->contactOf($team->application);
        $interview = $this->interviews->active($applicationId) ?? throw new StateConflict('This interview is no longer active.', 'interview-not-active');
        $this->messageRules->assertAllowed($team->application->companyId, $note);
        [$jobTitle, $companyName] = $this->names($team->application);

        return $this->render([RecruitmentEmailTemplates::INTERVIEW_UPDATED, InterviewEmailVariables::of($interview->withNote($note), $contact, $jobTitle, $companyName, $team->application->companyId)], $contact, $team->application->userId, calendarAttached: true);
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `applicant-unavailable`
     * @throws BusinessRuleViolation the codes of sending a test
     */
    public function assessment(Actor $actor, int $applicationId, AssessmentInvitationInput $input): EmailPreview
    {
        $team = $this->lookup->find($actor, $applicationId, permission: CompanyPermission::AssessmentsSend);
        $contact = $this->contactOf($team->application);
        [$jobTitle, $companyName] = $this->names($team->application);
        $draft = $this->assessments->previewEmail($team->application->companyId, $team->application->jobId, $input, $contact, $jobTitle, $companyName);

        return $this->render([$draft['key'], $draft['variables']], $contact, $team->application->userId, calendarAttached: false);
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `applicant-unavailable`
     * @throws BusinessRuleViolation the codes of sending an offer
     */
    public function offer(Actor $actor, int $applicationId, OfferInput $input): EmailPreview
    {
        $contact = null;
        $userId = 0;

        $draft = $this->rolledBack(function () use ($actor, $applicationId, $input, &$contact, &$userId): array {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::OffersManage);
            $contact = $this->contactOf($team->application);
            $userId = $team->application->userId;
            $event = $this->offerPlacer->place($team->member, $team->application, $input);
            $offer = $this->offers->findById($event->offerId) ?? throw new LogicException('The previewed offer was not found.');
            [$jobTitle, $companyName] = $this->names($team->application);
            $key = RecruitmentEmailTemplates::keyForOffer($event->change) ?? throw new LogicException('A sent offer has no email.');

            return [$key, OfferEmailVariables::of($offer, $contact, $jobTitle, $companyName, $team->application->companyId)];
        });

        return $this->render($draft, $contact, $userId, calendarAttached: false);
    }

    /**
     * Runs the work in a transaction that never commits and returns what it produced; any error it throws undoes the work as usual.
     *
     * @param  callable(): array{0: string, 1: array<string, string>}  $work
     * @return array{0: string, 1: array<string, string>}
     */
    private function rolledBack(callable $work): array
    {
        try {
            $this->transactions->run(function () use ($work): never {
                throw new PreviewRollback($work());
            });
        } catch (PreviewRollback $done) {
            return $done->draft;
        }
    }

    /** @param  array{0: string, 1: array<string, string>}  $draft */
    private function render(array $draft, ?ApplicantContact $contact, int $applicantUserId, bool $calendarAttached): EmailPreview
    {
        if (! $contact instanceof ApplicantContact) {
            throw new LogicException('A preview has the contact of the applicant.');
        }

        [$key, $variables] = $draft;
        $rendered = $this->previewer->render($key, $contact->locale, ['name' => $contact->name, ...$variables]);

        return new EmailPreview($key, $rendered->subject, $rendered->html, $rendered->text, $contact->locale, $this->preferences->isEnabled($applicantUserId, ApplicationNotificationType::UPDATE_PREFERENCE, NotificationChannel::Email), $calendarAttached);
    }

    /** @throws StateConflict `applicant-unavailable` */
    private function contactOf(Application $application): ApplicantContact
    {
        return $this->contacts->contact($application->userId) ?? throw new StateConflict('The applicant account is no longer available.', 'applicant-unavailable');
    }

    /** @return array{string, string} */
    private function names(Application $application): array
    {
        $summary = $this->companies->summaries([$application->companyId])[$application->companyId] ?? null;

        return [$this->jobs->titles([$application->jobId])[$application->jobId] ?? '', $summary === null ? '' : $summary->name];
    }
}
