<?php

namespace Src\Shared\Application\Email;

use InvalidArgumentException;
use Src\Shared\Domain\TranslatedText;

/**
 * An email the application sends: its key (`area.name`), the variables it may use and its default subject and body in every
 * language. The body is Markdown with `{{variable}}` placeholders. Admins can override subject and body per language; the
 * defaults here are always the fallback.
 */
final readonly class EmailTemplateDefinition
{
    /** @param  list<EmailVariable>  $variables */
    public function __construct(
        public string $key,
        public string $description,
        public array $variables,
        public TranslatedText $defaultSubject,
        public TranslatedText $defaultBody,
    ) {
        if (! $defaultSubject->isCompleteInAllLanguages() || ! $defaultBody->isCompleteInAllLanguages()) {
            throw new InvalidArgumentException("The default subject and body of {$key} must exist in every language.");
        }
    }

    public function variable(string $name): ?EmailVariable
    {
        foreach ($this->variables as $variable) {
            if ($variable->name === $name) {
                return $variable;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function variableNames(): array
    {
        return array_map(fn (EmailVariable $variable): string => $variable->name, $this->variables);
    }

    /** @return list<string> */
    public function requiredVariableNames(): array
    {
        return array_values(array_map(
            fn (EmailVariable $variable): string => $variable->name,
            array_filter($this->variables, fn (EmailVariable $variable): bool => $variable->required),
        ));
    }
}
