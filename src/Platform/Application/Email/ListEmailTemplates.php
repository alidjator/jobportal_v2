<?php

namespace Src\Platform\Application\Email;

/** Every email template with its overrides. The catalog is fixed by the code, so the list is not paginated. */
final readonly class ListEmailTemplates
{
    public function __construct(private EmailTemplateViews $views) {}

    /** @return list<EmailTemplateView> */
    public function execute(): array
    {
        return $this->views->all();
    }
}
