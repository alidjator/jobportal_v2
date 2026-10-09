<?php

namespace Src\JobSeeker\Domain;

interface CvTemplateRepository
{
    /** The stored states; a template with no row is missing (it counts as on and not the default). @return array<string, CvTemplateState> by template key */
    public function states(): array;

    /** Stores the state of the template, and when it is the default, takes the flag off every other one. */
    public function save(CvTemplateState $state): void;

    /** Locks the stored states until the transaction ends, so two changes run one after the other. */
    public function lock(): void;
}
