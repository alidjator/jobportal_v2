<?php

namespace Src\Assessment\Domain;

/** A raw score range of one scale and the standard score and band it converts to. */
final readonly class Norm
{
    public function __construct(
        public string $scaleKey,
        public int $rawMin,
        public int $rawMax,
        public float $standardScore,
        public string $band,
    ) {}
}
