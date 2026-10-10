<?php

namespace Src\Identity\Infrastructure\Auth;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum stamps `last_used_at` on every authenticated request, which is one UPDATE per API call. The stamp is a
 * convenience for the device list, so it is written at most once per interval; every other change saves as usual.
 */
final class ThrottledPersonalAccessToken extends PersonalAccessToken
{
    public const int STAMP_INTERVAL_MINUTES = 5;

    protected $table = 'personal_access_tokens';

    /** @param  array<string, mixed>  $options */
    public function save(array $options = []): bool
    {
        if ($this->exists && array_keys($this->getDirty()) === ['last_used_at'] && $this->stampedRecently()) {
            return true;
        }

        return parent::save($options);
    }

    private function stampedRecently(): bool
    {
        $stamped = $this->getOriginal('last_used_at');

        return $stamped instanceof Carbon && $stamped->greaterThan(Carbon::now()->subMinutes(self::STAMP_INTERVAL_MINUTES));
    }
}
