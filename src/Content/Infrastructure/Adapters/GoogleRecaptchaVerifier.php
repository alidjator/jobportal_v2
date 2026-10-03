<?php

namespace Src\Content\Infrastructure\Adapters;

use Illuminate\Support\Facades\Http;
use Src\Content\Domain\CaptchaVerifier;
use Throwable;

final class GoogleRecaptchaVerifier implements CaptchaVerifier
{
    public function isActive(): bool
    {
        return (bool) config('content.captcha.active');
    }

    public function verify(?string $response, ?string $ip = null): bool
    {
        if (! $response) {
            return false;
        }

        try {
            return (bool) Http::asForm()->timeout(30)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => config('content.captcha.secret'),
                'response' => $response,
                'remoteip' => $ip,
            ])->json('success');
        } catch (Throwable) {
            return false;
        }
    }
}
