<?php

namespace Tests\Feature\Notification;

use InvalidArgumentException;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Notifications\NotificationPreferenceDefinition;
use Tests\Concerns\BuildsJobFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-recruiter-workday.md` section 3: a preference is listed and changed only by the kinds of account it is meant for. */
final class NotificationPreferenceAudienceTest extends FeatureTestCase
{
    use BuildsJobFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJobFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownJobFixtures();

        parent::tearDown();
    }

    /** @return list<string> */
    private function keysFor(string $token): array
    {
        return array_column($this->api('GET', '/me/notification-preferences', token: $token)->assertOk()->json('data'), 'key');
    }

    public function test_a_company_member_sees_the_team_preferences_and_not_the_job_seeker_ones(): void
    {
        $keys = $this->keysFor($this->recruiterToken);

        $this->assertContains('team_daily_digest', $keys);
        $this->assertContains('approval_task', $keys);
        $this->assertContains('approval_update', $keys);
        $this->assertNotContains('application_update', $keys);
        $this->assertNotContains('job_alert', $keys);
    }

    public function test_a_job_seeker_does_not_see_the_team_preferences(): void
    {
        $keys = $this->keysFor($this->tokenFor($this->seekerId()));

        $this->assertNotContains('team_daily_digest', $keys);
        $this->assertNotContains('approval_task', $keys);
        $this->assertContains('application_update', $keys);
    }

    public function test_the_list_names_the_audiences_of_each_preference(): void
    {
        $item = collect($this->api('GET', '/me/notification-preferences', token: $this->recruiterToken)->json('data'))->firstWhere('key', 'team_daily_digest');

        $this->assertSame(['company_member'], $item['audiences']);
        $this->assertSame(['email' => ['enabled' => true, 'is_default' => true]], (array) $item['channels']);
    }

    public function test_a_preference_of_another_audience_cannot_be_changed(): void
    {
        $user = $this->tokenFor($this->seekerId());

        $this->api('PUT', '/me/notification-preferences/team_daily_digest/email', ['enabled' => false], $user)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:unknown-notification-preference');
        $this->api('PUT', '/me/notification-preferences/application_update/email', ['enabled' => false], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:unknown-notification-preference');
        $this->api('PUT', '/me/notification-preferences/team_daily_digest/email', ['enabled' => false], $this->recruiterToken)->assertNoContent();
    }

    public function test_a_definition_needs_a_known_audience(): void
    {
        $this->assertTrue((new NotificationPreferenceDefinition('probe', 'Probe', ['email' => true]))->isFor(ActorKind::JobSeeker));
        $this->assertFalse((new NotificationPreferenceDefinition('probe', 'Probe', ['email' => true], ['psychologist']))->isFor(ActorKind::CompanyMember));

        $this->expectException(InvalidArgumentException::class);
        new NotificationPreferenceDefinition('probe', 'Probe', ['email' => true], ['admin']);
    }

    private function seekerId(): int
    {
        return (int) UserModel::query()->firstOrCreate(['email' => 'siti@example.test'], ['type' => 'job_seeker', 'name' => 'Siti', 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00'])->id;
    }
}
