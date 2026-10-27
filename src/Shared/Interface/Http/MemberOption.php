<?php

namespace Src\Shared\Interface\Http;

/** A member offered in a picker with the company role they hold (`company_admin` or `member`). */
final readonly class MemberOption
{
    public function __construct(public int $id, public string $name, public string $role) {}

    /**
     * @param  list<array{id: int, name: string, role: string}>  $rows
     * @return list<self>
     */
    public static function listOf(array $rows): array
    {
        return array_map(static fn (array $row): self => new self($row['id'], $row['name'], $row['role']), $rows);
    }
}
