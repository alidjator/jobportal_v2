<?php

namespace Src\Dashboard\Interface\Http\Payloads;

/** The task list of a company member as the answer carries it. */
final readonly class CompanyTasksPayload
{
    /** @param  list<array{key: string, count: int, filter: string}>  $items */
    public function __construct(public string $generatedAt, public array $items) {}

    /** @param  array{generated_at: string, items: list<array{key: string, count: int, filter: string}>}  $tasks */
    public static function of(array $tasks): self
    {
        return new self($tasks['generated_at'], $tasks['items']);
    }
}
