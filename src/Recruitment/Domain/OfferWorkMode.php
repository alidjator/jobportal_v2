<?php

namespace Src\Recruitment\Domain;

/** Where the work is done. */
enum OfferWorkMode: string
{
    case Onsite = 'onsite';
    case Hybrid = 'hybrid';
    case Remote = 'remote';
}
