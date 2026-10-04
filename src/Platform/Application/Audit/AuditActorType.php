<?php

namespace Src\Platform\Application\Audit;

enum AuditActorType: string
{
    case User = 'user';
    case Admin = 'admin';
    case System = 'system';
}
