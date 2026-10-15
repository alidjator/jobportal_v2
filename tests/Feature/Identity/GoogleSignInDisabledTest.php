<?php

namespace Tests\Feature\Identity;

use Tests\FeatureTestCase;

/** The real wiring, with no `GOOGLE_CLIENT_IDS` configured (the default): the feature is off and nothing is accepted. */
final class GoogleSignInDisabledTest extends FeatureTestCase
{
    public function test_without_configured_client_ids_google_sign_in_is_unavailable(): void
    {
        $this->assertSame([], config('services.google.client_ids'));

        $this->postJson('/api/v1/auth/google', ['id_token' => 'any.token.value'])
            ->assertStatus(503)
            ->assertJsonPath('type', 'urn:onesis:error:google-sign-in-unavailable');
    }
}
