<?php

namespace Src\ReferenceData\Application\Queries;

use Src\ReferenceData\Application\CurrentPreferences;

final class GetCurrentSession
{
    public function __construct(private CurrentPreferences $current) {}

    public function __invoke(): array
    {
        return [
            'current_country' => $this->current->country(),
            'current_currency' => $this->current->currency(),
            'current_language' => $this->current->language(),
        ];
    }
}
