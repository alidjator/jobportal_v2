<?php

namespace Src\Shared\Application\Email;

use InvalidArgumentException;

/**
 * Every email template of the application. Each context registers its own from its service provider (like permissions and settings),
 * so contexts do not know each other and Platform only provides the mechanism.
 */
final class EmailTemplateCatalog
{
    /** @var array<string, EmailTemplateDefinition> */
    private array $definitions = [];

    public function add(EmailTemplateDefinition ...$definitions): void
    {
        foreach ($definitions as $definition) {
            if (! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $definition->key)) {
                throw new InvalidArgumentException("Template key {$definition->key} must look like area.name in lower snake case.");
            }

            if (isset($this->definitions[$definition->key])) {
                throw new InvalidArgumentException("Template {$definition->key} is already registered.");
            }

            $this->assertDefaultsAreConsistent($definition);

            $this->definitions[$definition->key] = $definition;
        }
    }

    public function find(string $key): ?EmailTemplateDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /** @return list<EmailTemplateDefinition> sorted by key */
    public function all(): array
    {
        $definitions = $this->definitions;
        ksort($definitions);

        return array_values($definitions);
    }

    /** The defaults must obey the rules admins are held to: only declared variables, and every required one present in the body. */
    private function assertDefaultsAreConsistent(EmailTemplateDefinition $definition): void
    {
        foreach ($definition->defaultSubject->toArray() as $language => $subject) {
            $this->assertKnownVariables($definition, EmailPlaceholders::in($subject), "subject ({$language})");
        }

        foreach ($definition->defaultBody->toArray() as $language => $body) {
            $used = EmailPlaceholders::in($body);
            $this->assertKnownVariables($definition, $used, "body ({$language})");

            $missing = array_diff($definition->requiredVariableNames(), $used);
            if ($missing !== []) {
                throw new InvalidArgumentException("The default body ({$language}) of {$definition->key} lacks the required variable(s): ".implode(', ', $missing).'.');
            }
        }
    }

    /** @param  list<string>  $used */
    private function assertKnownVariables(EmailTemplateDefinition $definition, array $used, string $where): void
    {
        $unknown = array_diff($used, $definition->variableNames());

        if ($unknown !== []) {
            throw new InvalidArgumentException("The default {$where} of {$definition->key} uses undeclared variable(s): ".implode(', ', $unknown).'.');
        }
    }
}
