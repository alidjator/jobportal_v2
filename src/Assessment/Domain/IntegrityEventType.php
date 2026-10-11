<?php

namespace Src\Assessment\Domain;

/** What the browser or the server noticed during an attempt. The first three are reported by the browser, `ip_change` only by the server. */
enum IntegrityEventType: string
{
    case TabSwitch = 'tab_switch';
    case FullscreenExit = 'fullscreen_exit';
    case Paste = 'paste';
    case IpChange = 'ip_change';

    public function isReportedByBrowser(): bool
    {
        return $this !== self::IpChange;
    }
}
