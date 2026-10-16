<?php

namespace Src\Recruitment\Application;

/** Another application of the person that the member may see, with the title of its job. */
final readonly class OtherApplicationView
{
    public function __construct(public OtherApplicationRow $application, public ?string $jobTitle) {}
}
