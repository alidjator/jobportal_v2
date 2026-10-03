<?php

namespace Src\Identity\Application;

/** Merakit isi `data` respons login / user-info / social login. */
final class AuthPayload
{
    public function __construct(private readonly UserProfiles $profiles) {}

    /**
     * @param  array<string,mixed>  $user
     * @return array{token:string,message:string,user:array<string,mixed>|null}
     */
    public function build(array $user, string $token, string $message): array
    {
        return [
            'token' => $token,
            'message' => $message,
            'user' => $this->profiles->forUser($user),
        ];
    }
}
