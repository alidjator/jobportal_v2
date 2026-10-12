<?php

namespace Src\Assessment\Application\Video;

use Src\Assessment\Application\Attempts\AttemptContext;
use Src\Assessment\Application\Attempts\AttemptGate;
use Src\Assessment\Domain\VideoTakeRepository;
use Src\Assessment\Domain\VideoTakeStatus;
use Src\Shared\Application\Actor;

/** Lets the browser pick up after a reload: how many takes are used and which one is the answer or still being recorded. */
final readonly class GetVideoState
{
    public function __construct(
        private AttemptGate $gate,
        private VideoTakes $videos,
        private VideoTakeRepository $takes,
    ) {}

    public function execute(Actor $actor, int $invitationId, int $questionId, ?string $ip): VideoState
    {
        return $this->gate->run($actor, $invitationId, $ip, function (AttemptContext $context) use ($questionId): VideoState {
            $question = $this->videos->question($context, $questionId);
            $used = $this->takes->ofQuestion((int) $context->attempt->id, $questionId);
            $answer = null;
            $recording = null;

            foreach ($used as $take) {
                $answer = $take->status === VideoTakeStatus::Complete ? $take : $answer;
                $recording = $take->isRecording() && ! $take->isPastDeadline($context->now) ? $take : $recording;
            }

            return new VideoState(count($used), ($question->maxRetakes ?? 0) + 1, $answer, $recording);
        });
    }
}
