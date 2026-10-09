<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\Exceptions\TooManyAttempts;
use Throwable;

/**
 * One message to the applicants of several applications of one job (at most the `messaging.max_bulk_recipients` setting). Each applicant gets it in their own
 * conversation with the company, created when missing. The files are stored once and shared by the messages. One recipient failing never stops the others: the
 * answer reports `sent` or `skipped` (with the reason) for every application. Each send is its own transaction, so what was sent stays sent.
 */
final readonly class BulkSendMessage
{
    public function __construct(
        private ConversationAccess $access,
        private ConversationOpener $opener,
        private MessageComposer $composer,
        private DailyMessageQuota $quota,
        private JobDirectory $jobs,
        private ApplicantRelations $applicants,
        private ApplicantContacts $contacts,
        private Settings $settings,
        private TransactionManager $transactions,
        private EventDispatcher $events,
    ) {}

    /**
     * @param  list<int>  $applicationIds
     * @return list<BulkMessageResult> in the order of the given ids, repeats removed
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound the job
     * @throws BusinessRuleViolation `too-many-recipients`, or a message check of `MessageComposer::prepare`
     * @throws TooManyAttempts `daily-limit` when the company has no room left today
     */
    public function execute(Actor $actor, int $jobId, array $applicationIds, MessageInput $input): array
    {
        $sender = $this->access->companyMember($actor);
        $companyId = (int) $sender->companyId;

        if ($this->jobs->visibleToMember($jobId, $companyId, $sender->userId, $sender->isCompanyAdmin) === null) {
            throw EntityNotFound::of('Job', $jobId);
        }

        $applicationIds = array_values(array_unique($applicationIds));
        $max = $this->settings->integer(MessagingSettings::MAX_BULK_RECIPIENTS, $companyId);

        if (count($applicationIds) > $max) {
            throw new BusinessRuleViolation("A bulk message can go to at most {$max} applications.", 'too-many-recipients', extensions: ['max_recipients' => $max]);
        }

        $this->quota->assertRoom($companyId);
        $prepared = $this->composer->prepare($sender->userId, $input);

        try {
            return $this->sendAll($sender, $jobId, $applicationIds, $prepared);
        } finally {
            // A no-op when at least one message uses the files: only files nobody uses are removed.
            $this->composer->discard($prepared);
        }
    }

    /**
     * @param  list<int>  $applicationIds
     * @return list<BulkMessageResult>
     */
    private function sendAll(Participant $sender, int $jobId, array $applicationIds, PreparedMessage $prepared): array
    {
        $applicantOf = $this->applicants->applicantsOfApplications($jobId, $applicationIds);
        $room = $this->quota->remaining((int) $sender->companyId);
        $reached = [];
        $results = [];

        foreach ($applicationIds as $applicationId) {
            $applicantId = $applicantOf[$applicationId] ?? null;

            if ($applicantId === null) {
                $results[] = BulkMessageResult::skipped($applicationId, 'application-not-found');
            } elseif (isset($reached[$applicantId])) {
                $results[] = BulkMessageResult::skipped($applicationId, 'duplicate-recipient');
            } elseif ($room < 1) {
                $results[] = BulkMessageResult::skipped($applicationId, 'daily-limit');
            } elseif (! $this->contacts->isActiveJobSeeker($applicantId)) {
                $results[] = BulkMessageResult::skipped($applicationId, 'recipient-unavailable');
            } else {
                $reached[$applicantId] = true;
                $result = $this->sendOne($sender, $applicationId, $applicantId, $prepared);
                $room -= $result->status === 'sent' ? 1 : 0;
                $results[] = $result;
            }
        }

        return $results;
    }

    private function sendOne(Participant $sender, int $applicationId, int $applicantId, PreparedMessage $prepared): BulkMessageResult
    {
        try {
            $event = $this->transactions->run(function () use ($sender, $applicantId, $prepared) {
                [$conversation] = $this->opener->openPair((int) $sender->companyId, $applicantId, $sender->userId);
                $this->composer->assertMayWrite($sender, $conversation);

                return $this->composer->deliver($conversation, $sender, $prepared, bulk: true);
            });
        } catch (StateConflict|TooManyAttempts $e) {
            return BulkMessageResult::skipped($applicationId, $e->errorCode());
        } catch (Throwable $e) {
            report($e);

            return BulkMessageResult::skipped($applicationId, 'failed');
        }

        $this->events->dispatch([$event]);

        return BulkMessageResult::sent($applicationId, $event->conversationId);
    }
}
