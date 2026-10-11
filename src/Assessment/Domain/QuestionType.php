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

    /** The types a psychological battery alone may use. */
    public function isPsychologicalOnly(): bool
    {
        return in_array($this, [self::Likert, self::ForcedChoice, self::Drawing], true);
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
