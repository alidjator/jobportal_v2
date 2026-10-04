<?php

namespace Src\Identity\Domain;

/** Which kind of account a sign-in targets: public users (`identity_users`) or administrators (`identity_admins`). */
enum AccountScope: string
{
    case User = 'user';
    case Admin = 'admin';
}
