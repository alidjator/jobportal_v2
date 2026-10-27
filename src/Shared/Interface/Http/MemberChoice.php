<?php

namespace Src\Shared\Interface\Http;

/** A member offered in a picker (an interviewer, an assessor): the account id to send back and the name to show. */
final readonly class MemberChoice
{
    public function __construct(public int $id, public string $name) {}

    /**
     * @param  list<array{id: int, name: string}>  $rows
     * @return list<self>
     */
    public static function listOf(array $rows): array
    {
        return array_map(static fn (array $row): self => new self($row['id'], $row['name']), $rows);
    }
}
