<?php

namespace Src\Assessment\Application\Video;

use Src\Assessment\Domain\VideoTake;

/** Where a candidate stands with a video question: the takes used, what is left and the take that is the answer or is being recorded. */
final readonly class VideoState
{
    public function __construct(
        public int $takesUsed,
        public int $takesAllowed,
        public ?VideoTake $answer,
        public ?VideoTake $recording,
    ) {}
}
