<?php

namespace Src\Assessment\Domain;

enum QuestionType: string
{
    case SingleChoice = 'single_choice';
    case MultipleResponse = 'multiple_response';
    case TrueFalse = 'true_false';
    case Number = 'number';
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Likert = 'likert';
    case ForcedChoice = 'forced_choice';
    case Drawing = 'drawing';
    case VideoAnswer = 'video_answer';

    /** The types a psychological battery alone may use. */
    public function isPsychologicalOnly(): bool
    {
        return in_array($this, [self::Likert, self::ForcedChoice, self::Drawing], true);
    }

    /** The type only a skill test may use, and only in a video section. */
    public function isSkillOnly(): bool
    {
        return $this === self::VideoAnswer;
    }

    /** True when the options are choices the candidate picks from (as opposed to accepted answers, or none). */
    public function hasChoices(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultipleResponse, self::TrueFalse, self::Likert, self::ForcedChoice], true);
    }

    /** True when the answer is checked against the options, with no person involved. */
    public function isAutoScored(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultipleResponse, self::TrueFalse, self::Number, self::ShortText], true);
    }
}
