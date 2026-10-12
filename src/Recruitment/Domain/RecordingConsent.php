<?php

namespace Src\Recruitment\Domain;

/** The job seeker's answer to the recording of an interview: given or refused when they answer the schedule, and withdrawn later until recording starts. */
enum RecordingConsent: string
{
    case Granted = 'granted';
    case Refused = 'refused';
    case Withdrawn = 'withdrawn';
}
