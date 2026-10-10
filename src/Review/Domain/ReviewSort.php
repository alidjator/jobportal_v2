<?php

namespace Src\Review\Domain;

/** The order of the public list of reviews. */
enum ReviewSort: string
{
    case Newest = 'newest';
    case Highest = 'highest';
    case Lowest = 'lowest';
}
