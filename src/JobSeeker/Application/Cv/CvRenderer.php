<?php

namespace Src\JobSeeker\Application\Cv;

/** Turns a CV document into the bytes of a PDF. Implemented in Infrastructure. */
interface CvRenderer
{
    public function render(CvDocument $document): string;
}
