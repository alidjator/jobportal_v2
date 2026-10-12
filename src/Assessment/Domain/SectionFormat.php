<?php

namespace Src\Assessment\Domain;

/** How a section is answered: questions, timed Kraepelin-type columns, drawings, or recorded video answers. */
enum SectionFormat: string
{
    case Questions = 'questions';
    case SpeedColumns = 'speed_columns';
    case Drawing = 'drawing';
    case Video = 'video';

    /** The formats that only a psychological battery may use. */
    public function isPsychologicalOnly(): bool
    {
        return $this === self::SpeedColumns || $this === self::Drawing;
    }

    /** The format that only a skill test may use: the video answers are scored by the company's assessors, never by a psychologist. */
    public function isSkillOnly(): bool
    {
        return $this === self::Video;
    }
}
