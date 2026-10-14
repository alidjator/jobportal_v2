<?php

namespace Src\Assessment\Domain;

/** Where the candidate takes the test: wherever they are (`online`), or at a place the company names (`onsite`, on a device at the office). The test runs in the engine either way. */
enum InvitationMode: string
{
    case Online = 'online';
    case Onsite = 'onsite';
}
