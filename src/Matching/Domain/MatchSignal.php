<?php

namespace Src\Matching\Domain;

/** The signals of a match and what each weighs (the weights add up to 100). The value is the key in the API. */
enum MatchSignal: string
{
    case Skills = 'skills';
    case Location = 'location';
    case Experience = 'experience';
    case Education = 'education';
    case Salary = 'salary';
    case Role = 'role';

    public function weight(): int
    {
        return match ($this) {
            self::Skills => 40,
            self::Location => 20,
            self::Experience => 15,
            self::Education => 10,
            self::Salary => 10,
            self::Role => 5,
        };
    }
}
