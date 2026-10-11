<?php

namespace Src\Assessment\Domain;

/** How a section is answered: questions, timed Kraepelin-type columns, or drawings. */
enum SectionFormat: string
{
    case Questions = 'questions';
    case SpeedColumns = 'speed_columns';
    case Drawing = 'drawing';

    /** The formats that only a psychological battery may use. */
    public function isPsychologicalOnly(): bool
    {
        return $this !== self::Questions;
    }
}
