<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewKit;
use Src\Recruitment\Domain\InterviewKitRepository;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewQuestionRepository;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\JobKitDefaults;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * Puts an interview on an application: checks the interviewer and the panel, cancels the interview that was active (a new time replaces it), saves the new
 * one and gives it its questions (a copy of the chosen kit, of the job's default kit, or of the replaced interview). It runs inside the caller's
 * transaction, with the application row already locked.
 */
final readonly class InterviewScheduler
{
    public function __construct(
        private InterviewRepository $interviews,
        private InterviewKitRepository $kits,
        private JobKitDefaults $defaults,
        private InterviewQuestionRepository $questions,
        private InterviewPanel $panel,
        private CompanyTeam $team,
        private JobDirectory $jobs,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /**
     * @throws BusinessRuleViolation `invalid-interview`, `invalid-interviewer`, `video-not-enabled` or `voice-not-enabled`
     * @throws EntityNotFound the named kit is not one of the company's
     */
    public function schedule(RecruitmentMember $member, Application $application, InterviewInput $input): InterviewChanged
    {
        $now = $this->clock->now();
        $applicationId = (int) $application->id;
        $this->assertInterviewer($application->companyId, $application->jobId, $input->interviewerId);
        $this->assertFormatAvailable($application->companyId, $input->format);
        $participants = $this->participants($application, $input);
        $kit = $input->kitGiven ? $this->chosenKit($application->companyId, $input->kitId) : null;

        $previous = $this->interviews->active($applicationId, forUpdate: true);
        // Without a kit in the request, a new time keeps what the interview it replaces had; a first interview gets the job's default kit.
        $source = $input->kitGiven || ! $previous instanceof Interview ? null : $previous;

        if (! $input->kitGiven && ! $source instanceof Interview) {
            $kit = $this->defaultKit($application);
        }

        // Without a flag in the request, a new video or voice time keeps the flag of the interview it replaces.
        $recorded = $input->recorded ?? ($input->format->isHeldInPlatform() && $previous instanceof Interview && $previous->recorded);

        $interview = Interview::schedule(
            $applicationId,
            $input->startsAt,
            $input->durationMinutes,
            $input->format,
            $input->location,
            $input->meetingUrl,
            $input->interviewerId,
            $member->userId,
            $now,
            $source instanceof Interview ? $source->kitId : $kit?->id,
            $source instanceof Interview ? $source->kitName : $kit?->name,
            $recorded,
        );

        if ($previous instanceof Interview) {
            $this->interviews->update($previous->cancel());
        }

        $saved = $this->interviews->add($interview);
        $savedId = (int) $saved->id;

        if ($source instanceof Interview) {
            $this->questions->copy((int) $source->id, $savedId);
        } elseif ($kit instanceof InterviewKit) {
            $this->questions->replace($savedId, $kit->questions);
        }

        if ($participants !== null) {
            $this->panel->replace($savedId, $participants);
        } elseif ($previous instanceof Interview) {
            $this->panel->copy((int) $previous->id, $savedId);
        }

        return InterviewChanged::of($previous instanceof Interview ? InterviewChange::Rescheduled : InterviewChange::Scheduled, $application, $saved, $now);
    }

    /**
     * A video interview can be scheduled only while an admin has switched video interviews on, and a voice interview while voice interviews are on.
     *
     * @throws BusinessRuleViolation `video-not-enabled` or `voice-not-enabled`
     */
    public function assertFormatAvailable(int $companyId, InterviewFormat $format): void
    {
        if ($format === InterviewFormat::Video && ! $this->settings->boolean(InterviewSettings::VIDEO_ENABLED, $companyId)) {
            $message = 'Interviews in the platform are not available yet.';

            throw new BusinessRuleViolation($message, 'video-not-enabled', extensions: ['errors' => ['format' => [$message]]]);
        }

        if ($format === InterviewFormat::Voice && ! $this->settings->boolean(InterviewSettings::VOICE_ENABLED, $companyId)) {
            $message = 'Phone interviews are not available yet.';

            throw new BusinessRuleViolation($message, 'voice-not-enabled', extensions: ['errors' => ['format' => [$message]]]);
        }
    }

    /**
     * The interviewer is a member of the company who may see the job (an admin, or a recruiter the job is assigned to).
     *
     * @throws BusinessRuleViolation `invalid-interviewer`
     */
    public function assertInterviewer(int $companyId, int $jobId, int $interviewerId): void
    {
        $this->assertWorksOnJob($companyId, $jobId, $interviewerId, 'interviewer_id');
    }

    /**
     * The extra panel members of the request: at most {@see InterviewPanel::EXTRA_MAX}, each a member who may see the job, none of them the main interviewer.
     * Null when the request named none.
     *
     * @return list<int>|null
     *
     * @throws BusinessRuleViolation `invalid-interviewer`
     */
    private function participants(Application $application, InterviewInput $input): ?array
    {
        return $input->participantIds === null ? null : $this->checkedParticipants($application->companyId, $application->jobId, $input->interviewerId, $input->participantIds);
    }

    /**
     * The extra members of a panel without duplicates, checked: at most {@see InterviewPanel::EXTRA_MAX}, each a member who may see the job, none of them the
     * main interviewer.
     *
     * @param  list<int>  $participantIds
     * @return list<int>
     *
     * @throws BusinessRuleViolation `invalid-interviewer`
     */
    public function checkedParticipants(int $companyId, int $jobId, int $interviewerId, array $participantIds): array
    {
        $ids = array_values(array_unique($participantIds));

        if (count($ids) > InterviewPanel::EXTRA_MAX) {
            $this->refuse('participant_ids', 'The panel can have at most '.InterviewPanel::EXTRA_MAX.' members besides the main interviewer.');
        }

        foreach ($ids as $userId) {
            if ($userId === $interviewerId) {
                $this->refuse('participant_ids', 'The main interviewer is already on the panel.');
            }

            $this->assertWorksOnJob($companyId, $jobId, $userId, 'participant_ids');
        }

        return $ids;
    }

    /** @throws EntityNotFound */
    private function chosenKit(int $companyId, ?int $kitId): ?InterviewKit
    {
        if ($kitId === null) {
            return null;
        }

        $kit = $this->kits->find($kitId);

        if (! $kit instanceof InterviewKit || $kit->companyId !== $companyId) {
            throw EntityNotFound::of('Interview kit', $kitId);
        }

        return $kit;
    }

    private function defaultKit(Application $application): ?InterviewKit
    {
        $kitId = $this->defaults->kitFor($application->jobId, $application->stageId);

        return $kitId === null ? null : $this->chosenKit($application->companyId, $kitId);
    }

    /** @throws BusinessRuleViolation `invalid-interviewer` */
    private function assertWorksOnJob(int $companyId, int $jobId, int $userId, string $field): void
    {
        $role = $this->team->roles($companyId)[$userId] ?? null;

        if ($role === null || $this->jobs->visibleToMember($jobId, $companyId, $userId, $role === 'company_admin') === null) {
            $this->refuse($field, 'Choose a member of your team who works on this job.');
        }
    }

    /** @throws BusinessRuleViolation */
    private function refuse(string $field, string $message): never
    {
        throw new BusinessRuleViolation($message, 'invalid-interviewer', extensions: ['errors' => [$field => [$message]]]);
    }
}
