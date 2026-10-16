<?php

namespace Src\Approval\Domain;

/** A slot is a named account, or any holder of a group permission. */
enum SlotKind: string
{
    case User = 'user';
    case Role = 'role';
}
