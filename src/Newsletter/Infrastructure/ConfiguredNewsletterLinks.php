<?php

namespace Src\Newsletter\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Src\Newsletter\Application\NewsletterLinks;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;
use Src\Shared\Infrastructure\ContextServiceProvider;

/**
 * Builds the links from the client URL templates `app.newsletter_confirm_url` and `app.newsletter_unsubscribe_url` (env `NEWSLETTER_CONFIRM_URL` and
 * `NEWSLETTER_UNSUBSCRIBE_URL`), where `{token}` stands for the token. The one-click address is the API route itself.
 */
final readonly class ConfiguredNewsletterLinks implements NewsletterLinks
{
    private const string PLACEHOLDER = '{token}';

    public function __construct(private Repository $config, private UrlGenerator $url) {}

    public function assertAvailable(): void
    {
        if ($this->template('confirm') === null || $this->template('unsubscribe') === null) {
            throw new ExternalServiceUnavailable('The newsletter is not available.', 'newsletter-unavailable');
        }
    }

    public function confirmation(string $token): string
    {
        return $this->fill($this->template('confirm'), $token);
    }

    public function unsubscription(string $token): string
    {
        return $this->fill($this->template('unsubscribe'), $token);
    }

    public function oneClickUnsubscription(string $token): string
    {
        return $this->url->to('/'.ContextServiceProvider::API_PREFIX.'/newsletter/unsubscribe/'.rawurlencode($token));
    }

    private function template(string $name): ?string
    {
        $template = $this->config->get('app.newsletter_'.$name.'_url');

        return is_string($template) && str_contains($template, self::PLACEHOLDER) ? $template : null;
    }

    private function fill(?string $template, string $token): string
    {
        return str_replace(self::PLACEHOLDER, rawurlencode($token), (string) $template);
    }
}
