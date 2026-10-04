<?php

namespace Src\Notification\Application\DeviceTokens;

/** The kind of client a push token belongs to. */
enum DevicePlatform: string
{
    case Android = 'android';
    case Ios = 'ios';
    case Web = 'web';
}
