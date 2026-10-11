<?php

namespace Src\Assessment\Application\Attempts;

use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\IntegrityEventType;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The browser reports a tab switch, a full-screen exit or a paste. The IP change is noticed by the server, never reported. A flood of reports stops being recorded. */
final readonly class ReportIntegrityEvent
{
    private const int MAX_EVENTS = 500;

    public function __construct(
        private AttemptGate $gate,
        private AttemptOutcomeRepository $outcomes,
    ) {}

    /**
     * @throws BusinessRuleViolation `invalid-integrity-event`
     * @throws StateConflict `attempt-not-started` or `attempt-closed`
     */
    public function execute(Actor $actor, int $invitationId, IntegrityEventType $type, ?string $ip): void
    {
        if (! $type->isReportedByBrowser()) {
            throw new BusinessRuleViolation('This signal is noticed by the server, not reported.', 'invalid-integrity-event', extensions: ['errors' => ['type' => ['This signal is noticed by the server, not reported.']]]);
        }

        $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use ($type): void {
            $attemptId = (int) $context->attempt->id;

            if ($this->outcomes->eventCount($attemptId) < self::MAX_EVENTS) {
                $this->outcomes->addEvent($attemptId, $type, $context->now);
            }
        });
    }
}
