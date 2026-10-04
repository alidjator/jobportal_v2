<?php

use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| The API is stateless: both guards authenticate Sanctum personal access tokens. Public users (`identity_users`) and
| administrators (`identity_admins`) have separate guards and providers, so a token issued to one kind of account is
| never accepted on the other's routes. Password resets use verification codes (`identity_verification_codes`), not
| the framework password broker.
|
*/

return [

    'defaults' => [
        'guard' => 'user',
    ],

    'guards' => [
        'user' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
        'admin' => [
            'driver' => 'sanctum',
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => UserModel::class,
        ],
        'admins' => [
            'driver' => 'eloquent',
            'model' => AdminModel::class,
        ],
    ],

];
