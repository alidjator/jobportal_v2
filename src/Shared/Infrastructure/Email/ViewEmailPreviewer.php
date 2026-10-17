<?php

namespace Src\Shared\Infrastructure\Email;

use Illuminate\Contracts\View\Factory;
use Src\Shared\Application\Email\CompanyEmailFrame;
use Src\Shared\Application\Email\EmailPreviewer;
use Src\Shared\Application\Email\EmailTemplates;
use Src\Shared\Application\Email\RenderedEmail;

/** Renders the template and puts it through the same views the mailables use, so the preview is the email. */
final readonly class ViewEmailPreviewer implements EmailPreviewer
{
    public function __construct(private EmailTemplates $templates, private CompanyEmailFrame $frames, private Factory $views) {}

    public function render(string $templateKey, string $locale, array $variables): RenderedEmail
    {
        $rendered = $this->templates->render($templateKey, $locale, $variables);
        $frame = isset($variables['company_id']) ? $this->frames->for((int) $variables['company_id'], $variables['company_name'] ?? '', $locale) : null;

        return new RenderedEmail(
            $rendered->subject,
            $this->views->make('emails.rendered-html', ['html' => $rendered->html, 'frame' => $frame])->render(),
            trim($this->views->make('emails.rendered-text', ['text' => $rendered->text, 'frame' => $frame])->render()),
        );
    }
}
