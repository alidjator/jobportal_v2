<?php

namespace Src\Identity\Infrastructure\Firebase;

use Kreait\Firebase\Contract\Auth;
use Src\Identity\Domain\Exceptions\SocialAuthFailed;
use Src\Identity\Domain\SocialIdentityVerifier;
use Src\Identity\Domain\VerifiedSocialIdentity;
use Throwable;

/** Adaptor kreait/firebase-php (kredensial dari config/firebase.php). */
class FirebaseSocialIdentityVerifier implements SocialIdentityVerifier
{
    private const PROVIDERS = ['google' => 'google.com', 'facebook' => 'facebook.com'];

    public function verify(string $provider, string $accessToken): VerifiedSocialIdentity
    {
        try {
            /** @var Auth $auth */
            $auth = app('firebase.auth');

            $signIn = $auth->signInWithIdpAccessToken(self::PROVIDERS[$provider], $accessToken);
            $claims = $auth->verifyIdToken((string) $signIn->idToken())->claims();

            return new VerifiedSocialIdentity(
                uid: (string) $claims->get('sub'),
                name: $claims->has('name') ? (string) $claims->get('name') : null,
                email: $claims->has('email') ? (string) $claims->get('email') : null,
            );
        } catch (Throwable $e) {
            report($e);

            throw new SocialAuthFailed('Unauthorized - Token is invalide: '.$e->getMessage());
        }
    }
}
