<?php

namespace Src\ReferenceData\Application\Masters;

/** How one master list is exposed: its URL name, table, extra columns and whether it is paginated or managed by admins. */
final readonly class MasterDefinition
{
    /**
     * @param  list<ExtraField>  $extras
     * @param  array<string, string>  $fixedColumns  columns that always have this value (for example benefits are listed only while `status` is `active`)
     */
    public function __construct(
        public string $key,
        public string $table,
        public array $extras = [],
        public bool $paginated = false,
        public bool $adminManaged = true,
        public array $fixedColumns = [],
    ) {}

    /** @return list<string> the `filter[...]` names this list accepts: the choice columns */
    public function filterNames(): array
    {
        return array_values(array_map(
            fn (ExtraField $field): string => $field->name,
            array_filter($this->extras, fn (ExtraField $field): bool => $field->type === FieldType::Choice),
        ));
    }

    public function extra(string $name): ?ExtraField
    {
        foreach ($this->extras as $extra) {
            if ($extra->name === $name) {
                return $extra;
            }
        }

        return null;
    }
}
