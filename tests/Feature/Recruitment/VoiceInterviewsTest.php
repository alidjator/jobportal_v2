<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\InterviewSettings;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/**
 * Tele-interview form 3, part 1: the `voice` interview format, the number the candidate confirms (and the optional fallback number of a video interview),
 * the Indonesian E.164 normalisation and the settings.
 */
final class VoiceInterviewsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job)->assertCreated()->json('data.id');
        $store = $this->app->make(SettingsStore::class);
        $store->setGlobal(InterviewSettings::VIDEO_ENABLED, true, null);
        $store->setGlobal(InterviewSettings::VOICE_ENABLED, true, null);
        config(['services.asterisk.caller_id' => '+622150000000']);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function schedule(string $format, array $overrides = []): TestResponse
    {
        $interview = $this->interviewSchedule(['format' => $format, 'meeting_url' => null, ...$overrides]);

        // The first interview comes with the move to the interview stage; a later one is scheduled on the application that is already there.
        if (DB::table('recruitment_interviews')->exists()) {
            return $this->api('PUT', "/company/applications/{$this->applicationId}/interview", $interview, $this->recruiterToken);
        }

        $stage = (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', 'interview')->where('is_default', 1)->value('id');

        return $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $stage, 'interview' => $interview], $this->recruiterToken);
    }

    private function mine(string $action, array $body = [], string $method = 'POST'): TestResponse
    {
        return $this->api($method, "/me/applications/{$this->applicationId}/interview".($action === '' ? '' : "/{$action}"), $body, $this->seekerToken);
    }

    private function storedPhone(): ?string
    {
        return DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->orderByDesc('id')->value('candidate_phone');
    }

    // ----- scheduling -----

    public function test_a_voice_interview_needs_neither_a_place_nor_a_link(): void
    {
        $this->schedule('voice', ['location' => 'Somewhere', 'meeting_url' => 'https://x.test/a'])->assertSuccessful();

        $this->mine('', method: 'GET')->assertOk()->assertJsonPath('data.format', 'voice')->assertJsonPath('data.location', null)->assertJsonPath('data.meeting_url', null)
            ->assertJsonPath('data.caller_number', '+622150000000')->assertJsonPath('data.candidate_phone', null);
    }

    public function test_voice_interviews_must_be_switched_on(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal(InterviewSettings::VOICE_ENABLED, false, null);

        $this->schedule('voice')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:voice-not-enabled')->assertJsonStructure(['errors' => ['format']]);
        $this->assertSame(0, DB::table('recruitment_interviews')->count());
    }

    public function test_a_voice_interview_can_be_recorded_and_a_phone_or_onsite_one_cannot_reveal_a_caller_number(): void
    {
        $this->schedule('voice', ['recorded' => true])->assertSuccessful();
        $this->mine('', method: 'GET')->assertOk()->assertJsonPath('data.recorded', true);

        $this->mine('decline')->assertOk();
        $this->schedule('online', ['meeting_url' => 'https://meet.example.test/a', 'recorded' => true])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-interview');
        $this->schedule('onsite', ['location' => 'Jakarta'])->assertSuccessful();
        $this->mine('', method: 'GET')->assertOk()->assertJsonPath('data.caller_number', null);
    }

    // ----- the number to call -----

    public function test_the_candidate_confirms_the_number_to_call_and_it_is_stored_as_e164(): void
    {
        $this->schedule('voice')->assertSuccessful();

        $this->mine('confirm', [])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-phone')->assertJsonStructure(['errors' => ['phone']]);
        $this->mine('confirm', ['phone' => '+1 415 555 0123'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-phone');
        $this->mine('confirm', ['phone' => '81234567890'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-phone');
        $this->assertNull($this->storedPhone());

        $this->mine('confirm', ['phone' => '0812-3456 7890'])->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.candidate_phone', '+6281234567890');
        $this->assertSame('+6281234567890', $this->storedPhone());
    }

    public function test_a_proposal_carries_the_number_so_the_team_can_accept_it(): void
    {
        $this->schedule('voice')->assertSuccessful();
        $later = now()->addDays(5)->utc()->format('Y-m-d\TH:i:s\Z');

        $this->mine('propose', ['starts_at' => $later])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-phone');
        $this->mine('propose', ['starts_at' => $later, 'phone' => '6281234567890'])->assertOk()->assertJsonPath('data.candidate_phone', '+6281234567890');

        $this->api('POST', "/company/applications/{$this->applicationId}/interview/accept-proposal", [], $this->recruiterToken)->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertSame('+6281234567890', $this->storedPhone());
    }

    public function test_a_video_interview_takes_an_optional_fallback_number(): void
    {
        $this->schedule('video')->assertSuccessful();
        $this->mine('confirm', ['fallback_phone' => '12345'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-phone')->assertJsonStructure(['errors' => ['fallback_phone']]);
        $this->mine('confirm', ['fallback_phone' => '08123456789'])->assertOk()->assertJsonPath('data.candidate_phone', '+628123456789');

        $this->mine('decline')->assertOk();
        $this->schedule('video')->assertSuccessful();
        $this->mine('confirm', ['phone' => '08123456789'])->assertOk()->assertJsonPath('data.candidate_phone', null);
        $this->assertNull($this->storedPhone());
    }

    public function test_other_formats_ignore_the_numbers(): void
    {
        $this->schedule('online', ['meeting_url' => 'https://meet.example.test/a'])->assertSuccessful();

        $this->mine('confirm', ['phone' => 'garbage', 'fallback_phone' => 'garbage'])->assertOk()->assertJsonPath('data.candidate_phone', null);
    }

    public function test_the_number_is_not_longer_than_the_request_allows(): void
    {
        $this->schedule('voice')->assertSuccessful();

        $this->mine('confirm', ['phone' => str_repeat('1', 31)])->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }
}
