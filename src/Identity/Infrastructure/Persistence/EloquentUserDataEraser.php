<?php

namespace Src\Identity\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Identity\Application\Account\UserDataEraser;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;

final readonly class EloquentUserDataEraser implements UserDataEraser
{
    public function __construct(private ConnectionInterface $connection) {}

    public function erase(int $userId): void
    {
        $this->connection->table('identity_social_accounts')->where('user_id', $userId)->delete();

        $this->connection->table('identity_verification_codes')
            ->where('subject_type', AccountScope::User->value)
            ->where('subject_id', $userId)
            ->delete();

        foreach (['identity_user_recovery_codes', 'identity_user_two_factor_challenges', 'identity_user_two_factor', 'identity_email_changes'] as $table) {
            $this->connection->table($table)->where('user_id', $userId)->delete();
        }

        $this->connection->table('identity_terms_acceptances')->where('user_id', $userId)->update(['ip_address' => null]);

        UserModel::query()->whereKey($userId)->first()?->tokens()->delete();
    }
}
