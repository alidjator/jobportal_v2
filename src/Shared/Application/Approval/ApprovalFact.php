<?php

namespace Src\Shared\Application\Approval;

/** A fact about a subject that the conditions of an approval step may read, for example the number of hires of a requisition. `string` facts may list the values they take. */
final readonly class ApprovalFact
{
    public const string INTEGER = 'integer';

    public const string STRING = 'string';

    public const string BOOLEAN = 'boolean';

    /** The unit of an amount of money: Indonesian rupiah. */
    public const string RUPIAH = 'idr';

    /**
     * @param  list<string>  $allowedValues  the values of a `string` fact; empty when any text is possible
     * @param  ?string  $unit  what an `integer` fact counts when it is not a plain number (`RUPIAH`), so a client can show it as such; null for a plain number
     */
    public function __construct(public string $key, public string $type, public string $label, public array $allowedValues = [], public ?string $unit = null) {}
}
