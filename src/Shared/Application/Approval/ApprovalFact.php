<?php

namespace Src\Shared\Application\Approval;

/** A fact about a subject that the conditions of an approval step may read, for example the number of hires of a requisition. `string` facts may list the values they take. */
final readonly class ApprovalFact
{
    public const string INTEGER = 'integer';

    public const string STRING = 'string';

    public const string BOOLEAN = 'boolean';

    /** @param  list<string>  $allowedValues  the values of a `string` fact; empty when any text is possible */
    public function __construct(public string $key, public string $type, public string $label, public array $allowedValues = []) {}
}
