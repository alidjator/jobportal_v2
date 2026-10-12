<?php

namespace Src\Recruitment\Application\Video;

use Src\Recruitment\Domain\Video\VideoRoomRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/**
 * Closes the rooms whose interview was cancelled, declined, replaced or has run out of its join window, removes the tokens that were handed out for
 * them and stops a recording that is still running. A room that cannot be closed now (the video server is down) stays open and is tried again on the next run.
 */
final readonly class CloseVideoRooms
{
    public function __construct(
        private VideoRoomRepository $rooms,
        private VideoRoomGateway $gateway,
        private JoinTokenFactory $tokens,
        private RecordingStopper $stopper,
        private Clock $clock,
    ) {}

    /** @return int the number of rooms closed */
    public function execute(): int
    {
        $closed = 0;

        foreach ($this->rooms->dueForClosing($this->clock->now()) as $interviewId) {
            $room = $this->rooms->find($interviewId);

            if ($room === null || ! $room->isOpen()) {
                continue;
            }

            try {
                // A recording that is still running when the room closes is stopped first, so what was recorded is kept.
                $stopped = $this->stopper->stop($interviewId, $room);
                $this->gateway->destroyRoom((int) $room->roomId, (string) $room->secret);

                foreach ($this->rooms->tokenUserIds($interviewId) as $userId) {
                    $this->gateway->removeToken($this->tokens->make($interviewId, (int) $room->roomId, $userId));
                }
            } catch (ExternalServiceUnavailable) {
                continue;
            }

            $this->rooms->forgetTokens($interviewId);
            $this->rooms->close($interviewId, $this->clock->now());

            if ($stopped !== null) {
                $this->stopper->announce($stopped);
            }

            $closed++;
        }

        return $closed;
    }
}
