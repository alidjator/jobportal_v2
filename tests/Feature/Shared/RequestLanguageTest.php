<?php

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Application\RequestContext;
use Tests\FeatureTestCase;

final class RequestLanguageTest extends FeatureTestCase
{
    /** The test client adds `Accept-Language: en-us,en;q=0.5` to every request; real clients without the header do not. */
    private const array NO_LANGUAGE_HEADER = ['Accept-Language' => ''];

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/probe')->get('language', fn (RequestContext $context) => [
            'locale' => App::getLocale(),
            'context' => $context->language,
        ]);
    }

    public function test_the_default_language_is_indonesian(): void
    {
        $this->getJson('/api/v1/probe/language', self::NO_LANGUAGE_HEADER)
            ->assertOk()
            ->assertHeader('Content-Language', 'id')
            ->assertExactJson(['locale' => 'id', 'context' => 'id']);
    }

    public function test_the_accept_language_header_wins_and_regions_are_ignored(): void
    {
        $this->getJson('/api/v1/probe/language', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertHeader('Content-Language', 'en')
            ->assertExactJson(['locale' => 'en', 'context' => 'en']);
    }

    public function test_unsupported_languages_fall_through_to_the_next_supported_one(): void
    {
        $this->getJson('/api/v1/probe/language', ['Accept-Language' => 'fr, en;q=0.8'])
            ->assertHeader('Content-Language', 'en');

        $this->getJson('/api/v1/probe/language', ['Accept-Language' => 'fr, de'])
            ->assertHeader('Content-Language', 'id');
    }

    public function test_the_account_locale_is_used_without_a_header_and_loses_to_the_header(): void
    {
        $user = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'en']);
        $token = $user->createToken('device', ['*'], now()->addDay())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/probe/language', self::NO_LANGUAGE_HEADER)->assertHeader('Content-Language', 'en');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/probe/language', ['Accept-Language' => 'id'])->assertHeader('Content-Language', 'id');
    }
}
