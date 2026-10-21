<?php

namespace Src\ReferenceData\Application\Masters;

use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\TranslatedText;

/** Changes an item. The slug never changes (links, filters and migrated data refer to it); to retire an item set `is_active` to false. */
final readonly class UpdateMasterItem
{
    public function __construct(private MasterRepository $masters) {}

    /**
     * @param  array<array-key, mixed>  $name
     * @param  array<string, int|string|null>  $extras
     *
     * @throws EntityNotFound
     * @throws StateConflict `reference-value-taken` when a unique extra column is already used by another item
     */
    public function execute(MasterDefinition $definition, int $id, array $name, int $sortOrder, bool $isActive, array $extras): MasterItem
    {
        $this->masters->find($definition, $id) ?? throw EntityNotFound::of('Reference item', $id);

        foreach ($definition->extras as $field) {
            if ($field->unique && isset($extras[$field->name]) && $this->masters->extraValueTaken($definition, $field->name, (string) $extras[$field->name], $id)) {
                throw new StateConflict('The :field is already used.', 'reference-value-taken', parameters: ['field' => $field->name]);
            }
        }

        return $this->masters->update($definition, $id, TranslatedText::fromArray($name), $sortOrder, $isActive, $extras);
    }
}
