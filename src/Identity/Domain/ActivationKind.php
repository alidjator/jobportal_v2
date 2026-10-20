<?php

namespace Src\Identity\Domain;

/** Why an activation email was sent: the first one, the single reminder, or a one-off sent by support or for a staff test (any number). */
enum ActivationKind: string
{
    case Activation = 'activation';
    case Reminder = 'reminder';
    case Manual = 'manual';
}
