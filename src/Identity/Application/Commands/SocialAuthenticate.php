<?php

namespace Src\Identity\Application\Commands;

use Src\Identity\Application\AuthPayload;
use Src\Identity\Domain\AccessTokens;
use Src\Identity\Domain\Exceptions\SocialAuthFailed;
use Src\Identity\Domain\NewAccount;
use Src\Identity\Domain\SocialIdentityVerifier;
use Src\Identity\Domain\UserRepository;

final class SocialAuthenticate
{
    public function __construct(
        private readonly SocialIdentityVerifier $verifier,
        private readonly UserRepository $users,
        private readonly AccessTokens $tokens,
        private readonly AuthPayload $payload,
    ) {}

    /** @throws SocialAuthFailed token provider tidak valid (HTTP 401) */
    public function __invoke(string $provider, string $firebaseToken, string $actionKey, ?string $role): SocialAuthenticationResult
    {
        $identity = $this->verifier->verify($provider, $firebaseToken);

        $user = $this->users->findByFirebaseUid($identity->uid);

        if ($user === null) {
            if ($actionKey === 'login') {
                return new SocialAuthenticationResult(['message' => 'User does not exist, please register an account first']);
            }

            if ($role === null) {
                return new SocialAuthenticationResult(['message' => 'Role is missing']);
            }

            if ($identity->email === null || $identity->email === '' || $this->users->emailExists($identity->email)) {
                return new SocialAuthenticationResult(['message' => 'Error creating user: email is missing or already registered'], 409);
            }

            $user = $this->users->create(new NewAccount(
                role: $role,
                name: $identity->name ?: strstr($identity->email, '@', true),
                email: $identity->email,
                firebaseUid: $identity->uid,
            ));
        }

        return new SocialAuthenticationResult(
            $this->payload->build($user, $this->tokens->issue($user['id']), 'User data retrieved successfully'),
        );
    }
}
