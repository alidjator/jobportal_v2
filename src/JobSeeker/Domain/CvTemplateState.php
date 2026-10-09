<?php

namespace Src\JobSeeker\Domain;

/** Whether a CV template is switched on and whether it is the default. */
final readonly class CvTemplateState
{
    public function __construct(public CvTemplate $template, public bool $enabled, public bool $isDefault) {}
}
