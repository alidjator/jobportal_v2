<?php

namespace Src\LegacyImport\Application;

/** The result of matching one master: the new id of each legacy id that matched, and the legacy items that did not (or matched more than one new item). */
final readonly class MasterMatch
{
    /**
     * @param  array<int, int>  $matched  new id by legacy id
     * @param  list<array{legacy_id: int, names: list<string>, reason: string}>  $unmatched
     */
    public function __construct(public array $matched, public array $unmatched) {}
}
