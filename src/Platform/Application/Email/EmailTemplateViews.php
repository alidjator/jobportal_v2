<?php

namespace Src\Platform\Application\Email;

use Src\Shared\Application\Email\EmailTemplateCatalog;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Builds the admin view of templates (definition plus overrides). */
final readonly class EmailTemplateViews
{
    public function __construct(
        private EmailTemplateCatalog $catalog,
        private EmailTemplateOverrideRepository $overrides,
    ) {}

    /** @return list<EmailTemplateView> sorted by key */
    public function all(): array
    {
        return array_map(fn ($definition): EmailTemplateView => $this->one($definition->key), $this->catalog->all());
    }

    /** @throws EntityNotFound when no template has this key */
    public function one(string $key): EmailTemplateView
    {
        $definition = $this->catalog->find($key) ?? throw EntityNotFound::of('Email template', $key);

        return new EmailTemplateView($definition, $this->overrides->forTemplate($key));
    }
}
