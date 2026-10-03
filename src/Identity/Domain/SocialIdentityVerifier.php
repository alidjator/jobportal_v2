<?php

namespace Src\Identity\Domain;

use Src\Identity\Domain\Exceptions\SocialAuthFailed;

interface SocialIdentityVerifier
{
    /**
     * @param  string  $provider  `google` atau `facebook`
     * @param  string  $accessToken  access token dari provider
     *
     * @throws SocialAuthFailed
     */
    public function verify(string $provider, string $accessToken): VerifiedSocialIdentity;
}
