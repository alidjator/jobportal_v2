<?php

namespace Src\Assessment\Domain;

/**
 * What the browser or the server noticed during an attempt. Tab switch, full-screen exit, paste and idle are reported by the browser; `ip_change` and `device_switch`
 * (the attempt was taken over by another device) only by the server.
 */
enum IntegrityEventType: string
{
    case TabSwitch = 'tab_switch';
    case FullscreenExit = 'fullscreen_exit';
    case Paste = 'paste';
    case IpChange = 'ip_change';
    case Idle = 'idle';
    case DeviceSwitch = 'device_switch';

    public function isReportedByBrowser(): bool
    {
        return $this !== self::IpChange && $this !== self::DeviceSwitch;
    }
}
