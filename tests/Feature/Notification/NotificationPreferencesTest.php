<?php

namespace Tests\Feature\Notification;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferenceCatalog;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Src\Shared\Application\Notifications\NotificationPreferences;
use Tests\FeatureTestCase;

final class NotificationPreferencesTest extends FeatureTestCase
{
    private UserModel $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 10:00:00');
        $this->app->make(NotificationPreferenceCatalog::class)->add(
            new NotificationPreferenceDefinition('probe_update', 'Updates on your job applications', ['email' => true, 'push' => true]),
            new NotificationPreferenceDefinition('newsletter', 'News from the portal', ['email' => false]),
        );
        $this->user = $this->makeUser('siti@example.test');
        $this->token = $this->user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeUser(string $email): UserModel
    {
        return UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => $email, 'status' => 'active', 'locale' => 'en']);
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token)->json($method, '/api/v1/me/notification-preferences'.$uri, $body);
    }

    public function test_both_routes_need_a_signed_in_user(): void
    {
        $this->getJson('/api/v1/me/notification-preferences')->assertStatus(401);
        $this->putJson('/api/v1/me/notification-preferences/newsletter/email', ['enabled' => true])->assertStatus(401);
    }

    public function test_the_list_shows_the_catalog_with_defaults_and_only_supported_channels(): void
    {
        $response = $this->api('GET', '')->assertOk();

        $this->assertSame(['application_update', 'chat_message', 'job_alert', 'newsletter', 'probe_update'], array_column($response->json('data'), 'key'));
        $this->assertSame('Updates on your job applications', $response->json('data.4.description'));
        $this->assertSame(['enabled' => true, 'is_default' => true], $response->json('data.4.channels.email'));
        $this->assertSame(['enabled' => true, 'is_default' => true], $response->json('data.4.channels.push'));
        $this->assertSame(['email' => ['enabled' => false, 'is_default' => true]], $response->json('data.3.channels'));
    }

    public function test_a_choice_is_stored_and_shown_and_can_be_changed_again(): void
    {
        $this->api('PUT', '/probe_update/email', ['enabled' => false])->assertNoContent();

        $channels = $this->api('GET', '')->json('data.4.channels');
        $this->assertSame(['enabled' => false, 'is_default' => false], $channels['email']);
        $this->assertSame(['enabled' => true, 'is_default' => true], $channels['push']);

        $this->api('PUT', '/probe_update/email', ['enabled' => true])->assertNoContent();

        $this->assertSame(['enabled' => true, 'is_default' => false], $this->api('GET', '')->json('data.4.channels.email'));
        $this->assertSame(1, DB::table('notification_preferences')->where('user_id', $this->user->id)->count());
    }

    public function test_choices_are_private_to_each_user(): void
    {
        $other = $this->makeUser('other@example.test');
        $this->api('PUT', '/probe_update/email', ['enabled' => false])->assertNoContent();

        $preferences = $this->app->make(NotificationPreferences::class);
        $this->assertFalse($preferences->isEnabled($this->user->id, 'probe_update', NotificationChannel::Email));
        $this->assertTrue($preferences->isEnabled($other->id, 'probe_update', NotificationChannel::Email));
    }

    public function test_unknown_keys_and_unsupported_channels_are_refused(): void
    {
        $this->api('PUT', '/nothing/email', ['enabled' => true])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:unknown-notification-preference');
        $this->api('PUT', '/newsletter/push', ['enabled' => true])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:unknown-notification-preference');
        $this->api('PUT', '/newsletter/sms', ['enabled' => true])->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:unknown-notification-preference');
        $this->assertSame(0, DB::table('notification_preferences')->count());
    }

    public function test_the_value_must_be_a_boolean(): void
    {
        $this->api('PUT', '/newsletter/email', [])->assertStatus(422);
        $this->api('PUT', '/newsletter/email', ['enabled' => 'maybe'])->assertStatus(422);
    }

    public function test_senders_get_the_choice_or_the_default_and_misuse_throws(): void
    {
        $preferences = $this->app->make(NotificationPreferences::class);

        $this->assertTrue($preferences->isEnabled($this->user->id, 'probe_update', NotificationChannel::Push));
        $this->assertFalse($preferences->isEnabled($this->user->id, 'newsletter', NotificationChannel::Email));

        $this->expectException(InvalidArgumentException::class);
        $preferences->isEnabled($this->user->id, 'newsletter', NotificationChannel::Push);
    }

    public function test_the_catalog_refuses_duplicates_bad_keys_and_bad_channels(): void
    {
        $catalog = $this->app->make(NotificationPreferenceCatalog::class);

        try {
            $catalog->add(new NotificationPreferenceDefinition('newsletter', 'Again', ['email' => true]));
            $this->fail('A duplicate key was accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        try {
            $catalog->add(new NotificationPreferenceDefinition('Bad-Key', 'Bad', ['email' => true]));
            $this->fail('A badly formed key was accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        new NotificationPreferenceDefinition('sms_only', 'Bad channel', ['sms' => true]);
    }
}
