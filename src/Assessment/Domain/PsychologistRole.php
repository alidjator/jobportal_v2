<?php

namespace Src\Assessment\Domain;

/** What a psychologist may do in the institution: a lead also manages the team (`docs/design/phase-8-provider-institutions.md` section 1). */
enum PsychologistRole: string
{
    case Member = 'member';
    case Lead = 'lead';

    public function isLead(): bool
    {
        return $this === self::Lead;
    }
}
