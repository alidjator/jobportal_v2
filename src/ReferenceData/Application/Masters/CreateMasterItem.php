<?php

namespace Src\ReferenceData\Application\Masters;

use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\TranslatedText;

/** Adds an item to a master. The slug comes from the English name (the Indonesian one if that is empty), with a number added when it is taken. */
final readonly class CreateMasterItem
{
    private const int MAX_SLUG_LENGTH = 150;

    public function __construct(
        private MasterRepository $masters,
        private SlugGenerator $slugs,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  array<array-key, mixed>  $name
     * @param  array<string, int|string|null>  $extras
     *
     * @throws StateConflict `reference-value-taken` when a unique extra column (a language code) is already used
     */
    public function execute(MasterDefinition $definition, array $name, int $sortOrder, bool $isActive, array $extras): MasterItem
    {
        $text = TranslatedText::fromArray($name);

        return $this->transactions->run(function () use ($definition, $text, $sortOrder, $isActive, $extras): MasterItem {
            foreach ($definition->extras as $field) {
                if ($field->unique && isset($extras[$field->name]) && $this->masters->extraValueTaken($definition, $field->name, (string) $extras[$field->name], null)) {
                    throw new StateConflict('The :field is already used.', 'reference-value-taken', parameters: ['field' => $field->name]);
                }
            }

            return $this->masters->create($definition, $this->uniqueSlug($definition, $text), $text, $sortOrder, $isActive, $extras);
        });
    }

    private function uniqueSlug(MasterDefinition $definition, TranslatedText $name): string
    {
        $base = $this->slugs->make($name->toArray()['en'] !== '' ? $name->toArray()['en'] : $name->toArray()['id']);
        $slug = $base;

        for ($number = 2; $this->masters->slugExists($definition, $slug); $number++) {
            $suffix = '-'.$number;
            $slug = mb_substr($base, 0, self::MAX_SLUG_LENGTH - mb_strlen($suffix)).$suffix;
        }

        return $slug;
    }
}
