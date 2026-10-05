<?php

namespace Tests\Feature\Shared;

use Tests\FeatureTestCase;

final class ApiRootTest extends FeatureTestCase
{
    public function test_the_root_answers_with_a_minimal_status(): void
    {
        $this->getJson('/')->assertOk()->assertExactJson(['name' => 'Job Portal API', 'version' => 'v1', 'status' => 'ok']);
    }

    public function test_the_root_sets_no_cookie(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertEmpty($response->headers->getCookies());
    }

    public function test_an_unknown_path_is_still_404(): void
    {
        $this->get('/nothing-here')->assertNotFound();
    }
}
