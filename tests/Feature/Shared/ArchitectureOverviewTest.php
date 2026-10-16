<?php

namespace Tests\Feature\Shared;

use Tests\FeatureTestCase;

final class ArchitectureOverviewTest extends FeatureTestCase
{
    public function test_the_page_is_not_found_while_the_documentation_flag_is_off(): void
    {
        $this->get('/docs/architecture')->assertNotFound();
    }

    public function test_the_page_is_served_as_html_with_its_own_policy_when_the_flag_is_on(): void
    {
        config()->set('scramble.enabled', true);

        $response = $this->get('/docs/architecture');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString("script-src 'unsafe-inline'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('<title>ONESIS ERP architecture overview</title>', (string) $response->getContent());
    }
}
