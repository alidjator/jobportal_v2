<?php

namespace Src\Identity\Infrastructure\Auth;

use Src\Identity\Application\TwoFactor\TwoFactorTokens;

final class RandomTwoFactorTokens implements TwoFactorTokens
{
    private const string ALPHABET = 'abcdefghijklmnopqrstuvwxyz234567';

    private const int GROUP_LENGTH = 5;

    public function challengeToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function recoveryCodes(int $count): array
    {
        $codes = [];

        while (count($codes) < $count) {
            $codes[$this->randomGroup().'-'.$this->randomGroup()] = true;
        }

        return array_keys($codes);
    }

    public function hash(string $value): string
    {
        return hash('sha256', strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $value)));
    }

    private function randomGroup(): string
    {
        $group = '';

        for ($i = 0; $i < self::GROUP_LENGTH; $i++) {
            $group .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $group;
    }
}
