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
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Company\CompanyLocations;
use Src\Shared\Application\Company\CompanyTeam;
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
        private HiringManagerVisibility $visibility,
        private CompanyTeam $team,
        private CandidateMessageRules $messageRules,
        private CompanyLocations $places,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /**
     * @throws BusinessRuleViolation `invalid-interview`, `invalid-interviewer`, `invalid-location`, `video-not-enabled`, `voice-not-enabled` or `message-link-not-allowed`
     * @throws EntityNotFound the named kit is not one of the company's
     */
    public function schedule(RecruitmentMember $member, Application $application, InterviewInput $input): InterviewChanged
    {
        $now = $this->clock->now();
        $applicationId = (int) $application->id;
        $this->assertInterviewer($application->companyId, $input->interviewerId);
        $this->assertFormatAvailable($application->companyId, $input->format);
        [$location, $locationId] = $this->place($application->companyId, $input->format, $input->location, $input->locationId);

        if ($input->noteGiven) {
            $this->messageRules->assertAllowed($application->companyId, $input->note, 'interview.note');
        }

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

        // One calendar event for the whole chain of an application: its UID is kept and every new interview raises the sequence past what was used.
        $chain = $this->interviews->calendarChain($applicationId);
        // Without a note in the request, a new time keeps the note of the interview it replaces; `note: null` clears it.
        $note = $input->noteGiven ? $input->note : ($previous instanceof Interview ? $previous->note : null);

        $interview = Interview::schedule(
            $applicationId,
            $input->startsAt,
            $input->durationMinutes,
            $input->format,
            $location,
            $input->meetingUrl,
            $input->interviewerId,
            $member->userId,
            $now,
            $source instanceof Interview ? $source->kitId : $kit?->id,
            $source instanceof Interview ? $source->kitName : $kit?->name,
            $recorded,
            $note,
            $chain === null ? self::newUid() : $chain['uid'],
            $chain === null ? 0 : $chain['sequence'] + 2,
            $locationId,
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

        $before = $previous instanceof Interview ? [$previous->interviewerId, ...($this->panel->forInterviews([(int) $previous->id])[(int) $previous->id] ?? [])] : [];

        if ($participants !== null) {
            $this->panel->replace($savedId, $participants);
        } elseif ($previous instanceof Interview) {
            $this->panel->copy((int) $previous->id, $savedId);
        }

        $after = [$input->interviewerId, ...($this->panel->forInterviews([$savedId])[$savedId] ?? [])];

        // A hiring manager on the panel sees the application, whatever its stage.
        $this->visibility->showToPanel($applicationId, $application->jobId, [$input->interviewerId, ...($participants ?? [])], $now);

        return InterviewChanged::of($previous instanceof Interview ? InterviewChange::Rescheduled : InterviewChange::Scheduled, $application, $saved, $now, removedUserIds: array_values(array_diff($before, $after)));
    }

    /** A random UUID (version 4) for the calendar event of a new chain. */
    private static function newUid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
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
     * The place of an onsite interview. With a work location the text is its address (cut to the length of an interview location) and the free text is ignored; without one the
     * free text stays. Another format keeps neither (the interview drops them).
     *
     * @return array{0: ?string, 1: ?int} the text and the id of the work location
     *
     * @throws BusinessRuleViolation `invalid-location` when the location is not an active location of the company or has no address yet
     */
    public function place(int $companyId, InterviewFormat $format, ?string $location, ?int $locationId): array
    {
        if ($format !== InterviewFormat::Onsite || $locationId === null) {
            return [$location, null];
        }

        $place = $this->places->activeLocation($companyId, $locationId);

        if ($place === null) {
            throw $this->invalidPlace('The location is not an active location of this company.');
        }

        if ($place->addressText === '') {
            throw $this->invalidPlace('The location has no address yet.');
        }

        return [mb_substr($place->addressText, 0, Interview::LOCATION_MAX), $place->id];
    }

    private function invalidPlace(string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-location', extensions: ['errors' => ['location_id' => [$message]]]);
    }

    /**
     * The interviewer is a member of the company who may sit on a panel (`interviews.participate`, or a company admin); working on the job is not required.
     *
     * @throws BusinessRuleViolation `invalid-interviewer`
     */
    public function assertInterviewer(int $companyId, int $interviewerId): void
    {
        $this->assertMayParticipate($companyId, $interviewerId, 'interviewer_id');
    }

    /**
     * The extra panel members of the request: at most {@see InterviewPanel::EXTRA_MAX}, each a member who may sit on a panel, none of them the main interviewer.
     * Null when the request named none.
     *
     * @return list<int>|null
     *
     * @throws BusinessRuleViolation `invalid-interviewer`
     */
    private function participants(Application $application, InterviewInput $input): ?array
    {
        return $input->participantIds === null ? null : $this->checkedParticipants($application->companyId, $input->interviewerId, $input->participantIds);
    }

    /**
     * The extra members of a panel without duplicates, checked: at most {@see InterviewPanel::EXTRA_MAX}, each a member who may sit on a panel, none of them the
     * main interviewer.
     *
     * @param  list<int>  $participantIds
     * @return list<int>
     *
     * @throws BusinessRuleViolation `invalid-interviewer`
     */
    public function checkedParticipants(int $companyId, int $interviewerId, array $participantIds): array
    {
        $ids = array_values(array_unique($participantIds));

        if (count($ids) > InterviewPanel::EXTRA_MAX) {
            $this->refuse('participant_ids', 'The panel can have at most '.InterviewPanel::EXTRA_MAX.' members besides the main interviewer.');
        }

        foreach ($ids as $userId) {
            if ($userId === $interviewerId) {
                $this->refuse('participant_ids', 'The main interviewer is already on the panel.');
            }

            $this->assertMayParticipate($companyId, $userId, 'participant_ids');
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
    private function assertMayParticipate(int $companyId, int $userId, string $field): void
    {
        if (! in_array($userId, $this->team->interviewerUserIds($companyId), true)) {
            $this->refuse($field, 'Choose a member of your team who may take part in interviews.');
        }
    }

    /** @throws BusinessRuleViolation */
    private function refuse(string $field, string $message): never
    {
        throw new BusinessRuleViolation($message, 'invalid-interviewer', extensions: ['errors' => [$field => [$message]]]);
    }
}
