<?php

namespace Src\Recruitment\Domain;

/** How a screening question is answered. The values match the check constraint of `recruitment_screening_questions.type`. */
enum QuestionType: string
{
    case Text = 'text';
    case YesNo = 'yes_no';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Number = 'number';

    /** Whether the question has a list of choices to pick from. */
    public function hasOptions(): bool
    {
        return $this === self::SingleChoice || $this === self::MultipleChoice;
    }

    /** A free text answer cannot be judged by a machine, so only the other types can be marked must-have. */
    public function supportsMustHave(): bool
    {
        return $this !== self::Text;
    }
}
