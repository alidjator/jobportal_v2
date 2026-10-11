<?php

namespace Src\Assessment\Domain;

enum ProviderStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
