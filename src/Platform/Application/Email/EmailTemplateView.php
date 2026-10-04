<?php

namespace Src\Platform\Application\Email;

use Src\Shared\Application\Email\EmailTemplateDefinition;

/** A template as an admin sees it: the definition and, per language, the default and the override if there is one. */
final readonly class EmailTemplateView
{
    /** @param  array<string, EmailTemplateOverride>  $overrides keyed by language */
    public function __construct(
        public EmailTemplateDefinition $definition,
        public array $overrides,
    ) {}
}
