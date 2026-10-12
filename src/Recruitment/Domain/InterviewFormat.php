<?php

namespace Src\Recruitment\Domain;

enum InterviewFormat: string
{
    case Online = 'online';
    case Onsite = 'onsite';
    // Held inside the platform on the self-hosted video server; needs neither a place nor a link.
    case Video = 'video';
    // A phone call: the team joins from the browser, the candidate is called on the number they confirmed. Needs neither a place nor a link.
    case Voice = 'voice';

    /** Held inside the platform (video or voice): no place and no link, and it can be recorded. */
    public function isHeldInPlatform(): bool
    {
        return $this === self::Video || $this === self::Voice;
    }
}
