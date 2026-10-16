<?php

namespace Src\Shared\Application\Tasks;

use InvalidArgumentException;

/** The items of the task list. Each context registers its own from its service provider. */
final class TaskProviders
{
    /** @var array<string, TaskProvider> */
    private array $providers = [];

    public function add(TaskProvider ...$providers): void
    {
        foreach ($providers as $provider) {
            if (isset($this->providers[$provider->key()]) && ! $this->providers[$provider->key()] instanceof $provider) {
                throw new InvalidArgumentException("The task {$provider->key()} is already registered.");
            }

            $this->providers[$provider->key()] = $provider;
        }
    }

    /** @return list<TaskProvider> in the order of the list */
    public function all(): array
    {
        $providers = array_values($this->providers);
        usort($providers, static fn (TaskProvider $a, TaskProvider $b): int => [$a->order(), $a->key()] <=> [$b->order(), $b->key()]);

        return $providers;
    }
}
