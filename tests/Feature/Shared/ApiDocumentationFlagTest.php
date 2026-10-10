<?php

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\Route;
use Tests\FeatureTestCase;

final class ApiDocumentationFlagTest extends FeatureTestCase
{
    public function test_the_documentation_is_off_by_default_in_any_environment(): void
    {
        $this->assertFalse((bool) config('scramble.enabled'));
        $this->assertSame([], collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->filter(fn (string $uri) => str_starts_with($uri, 'docs/api'))->values()->all());
        $this->get('/docs/api')->assertNotFound();
        $this->get('/docs/api.json')->assertNotFound();
    }

    public function test_the_flag_reads_api_docs_enabled_and_defaults_to_false(): void
    {
        $this->assertStringContainsString("env('API_DOCS_ENABLED', false)", (string) file_get_contents(base_path('config/scramble.php')));
    }
}
