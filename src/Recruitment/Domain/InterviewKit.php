<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A company's interview kit: a named list of 1 to {@see KitQuestion::MAX_PER_LIST} questions the team picks when it schedules an interview. The content is
 * copied into the interview, so a later change here never touches a past interview.
 */
final readonly class InterviewKit
{
    public const int NAME_MAX = 100;

    /** @param  list<KitQuestion>  $questions */
    public function __construct(
        public int $companyId,
        public string $name,
        public array $questions,
        public int $createdBy,
        public int $updatedBy,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?int $id = null,
    ) {}

    /**
     * @param  list<array{text: string, guidance: ?string, weight: int, anchor_1?: ?string, anchor_3?: ?string, anchor_5?: ?string}>  $questions
     *
     * @throws BusinessRuleViolation `invalid-kit`
     */
    public static function create(int $companyId, string $name, array $questions, int $userId, DateTimeImmutable $now): self
    {
        [$name, $questions] = self::checked($name, $questions);

        return new self($companyId, $name, $questions, $userId, $userId, $now, $now);
    }

    /**
     * The kit with a new name and a whole new list of questions.
     *
     * @param  list<array{text: string, guidance: ?string, weight: int, anchor_1?: ?string, anchor_3?: ?string, anchor_5?: ?string}>  $questions
     *
     * @throws BusinessRuleViolation `invalid-kit`
     */
    public function revise(string $name, array $questions, int $userId, DateTimeImmutable $now): self
    {
        [$name, $questions] = self::checked($name, $questions);

        return new self($this->companyId, $name, $questions, $this->createdBy, $userId, $this->createdAt, $now, $this->id);
    }

    /**
     * A copy with the same questions, guidance, weights and anchors, named "{name} (salinan)", then "(salinan 2)" and so on until no name of the company
     * matches (case does not count), with the original name cut so the whole name stays within {@see self::NAME_MAX} characters.
     *
     * @param  list<string>  $takenNames  the names of the company's kits
     */
    public function duplicate(array $takenNames, int $userId, DateTimeImmutable $now): self
    {
        $taken = array_map(mb_strtolower(...), $takenNames);

        for ($number = 1; ; $number++) {
            $suffix = $number === 1 ? ' (salinan)' : " (salinan {$number})";
            $name = mb_substr($this->name, 0, self::NAME_MAX - mb_strlen($suffix)).$suffix;

            if (! in_array(mb_strtolower($name), $taken, true)) {
                return new self($this->companyId, $name, $this->questions, $userId, $userId, $now, $now);
            }
        }
    }

    /**
     * @param  list<array{text: string, guidance: ?string, weight: int, anchor_1?: ?string, anchor_3?: ?string, anchor_5?: ?string}>  $raw
     * @return array{0: string, 1: list<KitQuestion>}
     */
    private static function checked(string $name, array $raw): array
    {
        [$questions, $errors] = KitQuestion::check($raw, 1);
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
            $errors = ['name' => ['The name must have 1 to '.self::NAME_MAX.' characters.'], ...$errors];
        }

        if ($errors !== []) {
            throw KitQuestion::violation($errors);
        }

        return [$name, $questions];
    }
}
