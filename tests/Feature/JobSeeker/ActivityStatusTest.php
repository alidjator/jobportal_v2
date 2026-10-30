<?php

namespace Tests\Feature\JobSeeker;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Src\Identity\Infrastructure\Privacy\AccountDataExporter;
use Src\JobSeeker\Application\Export\ProfileDataExporter;
use Src\JobSeeker\Domain\ActiveWithin;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-jobseeker-privacy.md` section 4: the last active bucket and "open to offers". */
final class ActivityStatusTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @return array{id: int, slug: string, token: string} a findable job seeker with a complete profile */
    private function candidate(string $email, string $name, array $profile = []): array
    {
        $id = $this->seeker($email, name: $name);
        $token = $this->tokenFor($id);
        $this->completeProfile($token);
        DB::table('jobseeker_profiles')->where('user_id', $id)->update(['is_discoverable' => true, ...$profile]);

        return ['id' => $id, 'slug' => (string) DB::table('jobseeker_profiles')->where('user_id', $id)->value('slug'), 'token' => $token];
    }

    private function lastActive(int $userId): ?string
    {
        $day = DB::table('identity_users')->where('id', $userId)->value('last_active_on');

        return $day === null ? null : substr((string) $day, 0, 10);
    }

    /** @return array<string, mixed> the list row of a candidate */
    private function row(string $slug, string $query = ''): array
    {
        return collect($this->api('GET', '/company/candidates?per_page=100'.$query)->assertOk()->json('data'))->firstWhere('slug', $slug);
    }

    // ----- last active -----

    public function test_a_job_seeker_request_marks_the_jakarta_day_once_a_day(): void
    {
        // 20:00 UTC is already the next day in Jakarta.
        Carbon::setTestNow('2026-10-05 20:00:00');
        $ana = $this->candidate('ana@example.test', 'Ana');
        DB::table('identity_users')->where('id', $ana['id'])->update(['last_active_on' => null]);

        $this->api('GET', '/me/profile', token: $ana['token'])->assertOk();
        $this->assertSame('2026-10-06', $this->lastActive($ana['id']));

        Carbon::setTestNow('2026-10-06 05:00:00');
        $this->api('GET', '/me/profile', token: $ana['token'])->assertOk();
        $this->assertSame('2026-10-06', $this->lastActive($ana['id']));

        Carbon::setTestNow('2026-10-06 18:00:00');
        $this->api('GET', '/me/profile', token: $ana['token'])->assertOk();
        $this->assertSame('2026-10-07', $this->lastActive($ana['id']));
    }

    public function test_only_a_signed_in_job_seeker_is_marked(): void
    {
        $ana = $this->candidate('ana@example.test', 'Ana');
        DB::table('identity_users')->update(['last_active_on' => null]);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/me/profile')->assertUnauthorized();
        $this->api('GET', '/company/candidates', token: $this->recruiterToken)->assertOk();
        $this->api('GET', '/jobs')->assertOk();

        $this->assertSame(0, DB::table('identity_users')->whereNotNull('last_active_on')->count());

        $this->api('GET', '/me/profile', token: $ana['token'])->assertOk();

        $this->assertSame(1, DB::table('identity_users')->whereNotNull('last_active_on')->count());
        $this->assertNotNull($this->lastActive($ana['id']));
    }

    public function test_the_bucket_rounds_the_day_up_and_an_account_never_seen_has_none(): void
    {
        // The fixtures freeze the clock at 2026-10-05 and their company tokens last a day, so the clock stays there.
        $today = new DateTimeImmutable('2026-10-05', new DateTimeZone('UTC'));
        $expected = [0 => '7d', 7 => '7d', 8 => '30d', 30 => '30d', 31 => '90d', 90 => '90d', 91 => 'older', 400 => 'older'];

        foreach ($expected as $days => $bucket) {
            $this->assertSame($bucket, ActiveWithin::of($today->modify("-{$days} days"), $today)?->value, "{$days} days ago");
        }

        $this->assertNull(ActiveWithin::of(null, $today));

        $ana = $this->candidate('ana@example.test', 'Ana');
        $budi = $this->candidate('bayu.k@example.test', 'Budi');
        DB::table('identity_users')->where('id', $ana['id'])->update(['last_active_on' => '2026-09-25']);
        DB::table('identity_users')->where('id', $budi['id'])->update(['last_active_on' => null]);

        $this->assertSame('30d', $this->row($ana['slug'])['active_within']);
        $this->assertNull($this->row($budi['slug'])['active_within']);
        $this->assertArrayNotHasKey('last_active_on', $this->row($ana['slug']));
        $this->api('GET', "/company/candidates/{$ana['slug']}")->assertOk()->assertJsonPath('data.active_within', '30d')->assertJsonMissingPath('data.last_active_on');
    }

    public function test_the_job_seeker_sees_the_bucket_on_their_own_profile(): void
    {
        Carbon::setTestNow('2026-10-20 03:00:00');
        $ana = $this->candidate('ana@example.test', 'Ana');
        DB::table('identity_users')->where('id', $ana['id'])->update(['last_active_on' => '2026-09-01']);

        // The request itself marks today, so the bucket is the one of a job seeker who is here now.
        $this->api('GET', '/me/profile', token: $ana['token'])->assertOk()->assertJsonPath('data.active_within', '7d')->assertJsonMissingPath('data.last_active_on');
    }

    public function test_the_search_can_put_the_most_recently_active_first_and_never_seen_accounts_last(): void
    {
        $ana = $this->candidate('ana@example.test', 'Ana', ['updated_at' => '2026-10-03 00:00:00']);
        $budi = $this->candidate('bayu.k@example.test', 'Budi', ['updated_at' => '2026-10-04 00:00:00']);
        $citra = $this->candidate('citra@example.test', 'Citra', ['updated_at' => '2026-10-02 00:00:00']);
        DB::table('identity_users')->where('id', $ana['id'])->update(['last_active_on' => '2026-10-04']);
        DB::table('identity_users')->where('id', $budi['id'])->update(['last_active_on' => null]);
        DB::table('identity_users')->where('id', $citra['id'])->update(['last_active_on' => '2026-10-05']);

        $slugs = fn (string $query): array => $this->api('GET', '/company/candidates?per_page=100'.$query)->assertOk()->json('data.*.slug');

        $this->assertSame([$budi['slug'], $ana['slug'], $citra['slug']], $slugs(''));
        $this->assertSame([$budi['slug'], $ana['slug'], $citra['slug']], $slugs('&sort=-updated_at'));
        $this->assertSame([$citra['slug'], $ana['slug'], $budi['slug']], $slugs('&sort=-last_active'));
        $this->api('GET', '/company/candidates?sort=last_active')->assertStatus(422);
    }

    // ----- open to offers -----

    public function test_open_to_offers_is_off_until_the_job_seeker_switches_it_on(): void
    {
        $ana = $this->candidate('ana@example.test', 'Ana');

        $this->api('GET', '/me/profile', token: $ana['token'])->assertOk()->assertJsonPath('data.open_to_offers', false);
        $this->assertFalse($this->row($ana['slug'])['open_to_offers']);

        $this->api('PATCH', '/me/profile', ['open_to_offers' => true], $ana['token'])->assertOk()->assertJsonPath('data.open_to_offers', true);
        $this->assertTrue($this->row($ana['slug'])['open_to_offers']);
        $this->api('GET', "/company/candidates/{$ana['slug']}")->assertOk()->assertJsonPath('data.open_to_offers', true);

        $this->api('PATCH', '/me/profile', ['headline' => 'Developer'], $ana['token'])->assertOk()->assertJsonPath('data.open_to_offers', true);
        $this->api('PATCH', '/me/profile', ['open_to_offers' => null], $ana['token'])->assertStatus(422);
        $this->api('PATCH', '/me/profile', ['open_to_offers' => false], $ana['token'])->assertOk()->assertJsonPath('data.open_to_offers', false);
    }

    public function test_the_search_filters_on_open_to_offers(): void
    {
        $ana = $this->candidate('ana@example.test', 'Ana', ['open_to_offers' => true]);
        $budi = $this->candidate('bayu.k@example.test', 'Budi');

        $found = fn (string $query): array => $this->api('GET', '/company/candidates?per_page=100'.$query)->assertOk()->json('data.*.slug');

        $this->assertEqualsCanonicalizing([$ana['slug'], $budi['slug']], $found(''));
        $this->assertEqualsCanonicalizing([$ana['slug'], $budi['slug']], $found('&filter[open_to_offers]=0'));
        $this->assertSame([$ana['slug']], $found('&filter[open_to_offers]=1'));
        $this->api('GET', '/company/candidates?filter[open_to_offers]=maybe')->assertStatus(422);
    }

    public function test_the_personal_data_export_holds_the_activity_status(): void
    {
        Carbon::setTestNow('2026-10-20 03:00:00');
        $ana = $this->candidate('ana@example.test', 'Ana', ['open_to_offers' => true]);

        $this->api('GET', '/me/profile', token: $ana['token'])->assertOk();
        $account = app(AccountDataExporter::class)->export($ana['id']);
        $profile = app(ProfileDataExporter::class)->export($ana['id']);

        $this->assertSame('2026-10-20', substr((string) $account->data['last_active_on'], 0, 10));
        $this->assertTrue($profile->data['details']['visibility']['open_to_offers']);
    }
}
