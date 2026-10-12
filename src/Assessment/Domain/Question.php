<?php

namespace Src\Assessment\Domain;

final readonly class Question
{
    /**
     * `thinkSeconds`, `answerSeconds` and `maxRetakes` belong to a video answer question only; a null think time means it is not limited.
     *
     * @param  list<QuestionOption>  $options
     */
    public function __construct(
        public ?int $id,
        public int $sectionId,
        public int $position,
        public QuestionType $type,
        public string $text,
        public int $points,
        public ?string $scaleKey,
        public bool $isSample,
        public array $options,
        public ?int $thinkSeconds = null,
        public ?int $answerSeconds = null,
        public ?int $maxRetakes = null,
    ) {}

    /** The least time a section needs for this question: the limited think time (none counted when it is unlimited) plus every take at full length. */
    public function minimumSeconds(): int
    {
        if ($this->type !== QuestionType::VideoAnswer) {
            return 0;
        }

        return (int) $this->thinkSeconds + (int) $this->answerSeconds * ((int) $this->maxRetakes + 1);
    }
}
