<?php

namespace Src\Recruitment\Domain;

/** A stage of one company's pipeline. The id is null until the stage is written. */
final readonly class Stage
{
    public function __construct(
        public int $companyId,
        public StageType $type,
        public string $name,
        public int $position,
        public bool $isDefault,
        public ?int $id = null,
    ) {}

    /**
     * The seven default stages in their starting order: the active types first, the terminal ones at the end.
     *
     * @return list<self>
     */
    public static function defaultsFor(int $companyId): array
    {
        $stages = [];

        foreach (StageType::cases() as $index => $type) {
            $stages[] = new self($companyId, $type, $type->defaultName(), $index + 1, true);
        }

        return $stages;
    }
}
