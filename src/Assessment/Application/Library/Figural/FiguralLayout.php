<?php

namespace Src\Assessment\Application\Library\Figural;

/** How the stem of a figural item is laid out: none (the five options are the whole item), a row ending in a question cell, two pairs, or a 3 by 3 grid whose last cell is the question. */
enum FiguralLayout: string
{
    case OptionsOnly = 'options_only';
    case Series = 'series';
    case Analogy = 'analogy';
    case Matrix = 'matrix';
}
