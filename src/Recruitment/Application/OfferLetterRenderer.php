<?php

namespace Src\Recruitment\Application;

/** Turns a generated offer letter into the bytes of a PDF. Implemented in Infrastructure. */
interface OfferLetterRenderer
{
    public function render(OfferLetterDocument $document): string;
}
