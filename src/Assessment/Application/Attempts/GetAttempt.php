<?php

namespace Src\Assessment\Application\Attempts;

use Src\Shared\Application\Actor;

/** The attempt as it stands now: the open section, the questions the section lets the candidate see and the answers saved so far. Resuming a test is this call. */
final readonly class GetAttempt
{
    public function __construct(
        private AttemptGate $gate,
        private AttemptViews $views,
    ) {}

    public function execute(Actor $actor, int $invitationId, ?string $ip): AttemptView
    {
        return $this->gate->run($actor, $invitationId, $ip, fn (AttemptContext $context): AttemptView => $this->views->build($context), readOnly: true);
    }
}
