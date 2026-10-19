<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/company-locations.md` section 4: an onsite interview may point to a work location, whose address is copied into the interview. */
final class InterviewLocationTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private const string ADDRESS = 'Jl. Setiabudi 1, GEGERKALONG, SUKASARI, BANDUNG, JAWA BARAT';

    private int $job;

    private int $applicationId;

    private int $locationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
        $this->locationId = $this->location('Cabang Bandung', ['address_line' => 'Jl. Setiabudi 1', ...$this->locationChain()]);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $attributes */
    private function location(string $name, array $attributes = [], ?int $companyId = null): int
    {
        return (int) DB::table('company_locations')->insertGetId([
            'company_id' => $companyId ?? $this->companyId, 'name' => $name, 'type' => 'branch', 'is_main' => false, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(), ...$attributes,
        ]);
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    /** The application sits in the interview stage without an interview, so the schedule endpoint accepts one. */
    private function ready(): void
    {
        if (DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type') !== 'interview') {
            $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('interview'), 'interview' => ['schedule_later' => true]], $this->recruiterToken)->assertOk();
        }
    }

    /** @param  array<string, mixed>  $overrides */
    private function schedule(array $overrides): TestResponse
    {
        $this->ready();

        return $this->api('PUT', "/company/applications/{$this->applicationId}/interview", $this->interviewSchedule($overrides), $this->recruiterToken);
    }

    private function interview(): object
    {
        return DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->where('status', 'pending')->first();
    }

    public function test_an_onsite_interview_takes_the_address_of_the_location_and_ignores_a_sent_text(): void
    {
        $this->schedule(['format' => 'onsite', 'location_id' => $this->locationId, 'location' => 'Somewhere else'])->assertSuccessful()
            ->assertJsonPath('data.location', self::ADDRESS)
            ->assertJsonPath('data.location_id', $this->locationId);

        $row = $this->interview();
        $this->assertSame(self::ADDRESS, $row->location);
        $this->assertSame($this->locationId, (int) $row->location_id);
    }

    public function test_the_move_to_an_interview_stage_takes_a_location_too(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", [
            'stage_id' => $this->stage('interview'), 'interview' => $this->interviewSchedule(['format' => 'onsite', 'location_id' => $this->locationId]),
        ], $this->recruiterToken)->assertOk();

        $this->assertSame([self::ADDRESS, $this->locationId], [$this->interview()->location, (int) $this->interview()->location_id]);
    }

    public function test_free_text_without_a_location_stays_allowed(): void
    {
        $this->schedule(['format' => 'onsite', 'location' => 'Room 2'])->assertSuccessful()->assertJsonPath('data.location', 'Room 2')->assertJsonPath('data.location_id', null);

        $this->assertNull($this->interview()->location_id);
    }

    public function test_a_location_goes_only_with_an_onsite_interview(): void
    {
        $this->schedule(['format' => 'online', 'location_id' => $this->locationId])->assertSuccessful()->assertJsonPath('data.location', null)->assertJsonPath('data.location_id', null);

        $this->assertNull($this->interview()->location_id);
    }

    public function test_a_location_that_is_not_an_active_location_with_an_address_of_the_company_is_refused(): void
    {
        $inactive = $this->location('Tutup', ['address_line' => 'Jl. Lama 1', 'status' => 'inactive', 'deactivated_at' => now()]);
        $noAddress = $this->location('Belum ada alamat');
        $foreign = $this->location('Milik lain', ['address_line' => 'Jl. Lain 1'], $this->otherCompanyId);

        foreach ([$inactive, $noAddress, $foreign, 999999] as $id) {
            $this->schedule(['format' => 'onsite', 'location_id' => $id])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-location');
        }

        $this->schedule(['format' => 'onsite', 'location_id' => 'x'])->assertStatus(422);
        $this->assertSame(0, DB::table('recruitment_interviews')->count());
    }

    public function test_a_new_schedule_replaces_the_place_and_the_old_interview_keeps_its_own(): void
    {
        $this->schedule(['format' => 'onsite', 'location_id' => $this->locationId])->assertSuccessful();
        $second = $this->location('Cabang Medan', ['address_line' => 'Jl. Medan 2']);

        $this->schedule(['format' => 'onsite', 'location_id' => $second])->assertSuccessful();

        $rows = DB::table('recruitment_interviews')->where('application_id', $this->applicationId)->orderBy('id')->get(['status', 'location', 'location_id']);
        $this->assertSame(['cancelled', self::ADDRESS, $this->locationId], [$rows[0]->status, $rows[0]->location, (int) $rows[0]->location_id]);
        $this->assertSame(['pending', 'Jl. Medan 2', $second], [$rows[1]->status, $rows[1]->location, (int) $rows[1]->location_id]);
    }

    public function test_the_text_of_an_interview_stays_true_when_the_location_changes_later(): void
    {
        $this->schedule(['format' => 'onsite', 'location_id' => $this->locationId])->assertSuccessful();

        $this->api('PATCH', "/company/locations/{$this->locationId}", ['address_line' => 'Jl. Pindah 9'], $this->adminToken)->assertOk();

        $this->assertSame(self::ADDRESS, $this->interview()->location);
    }

    public function test_the_calendar_returns_the_location_id_beside_the_text(): void
    {
        $this->schedule(['format' => 'onsite', 'location_id' => $this->locationId])->assertSuccessful();

        $from = now()->subDay()->format('Y-m-d');
        $to = now()->addDays(10)->format('Y-m-d');
        $events = $this->api('GET', "/company/calendar?from={$from}&to={$to}", [], $this->recruiterToken)->assertOk()->json('data');
        $interviews = array_values(array_filter($events, static fn (array $event): bool => ($event['details']['interview_id'] ?? null) !== null));

        $this->assertCount(1, $interviews);
        $this->assertSame([self::ADDRESS, $this->locationId], [$interviews[0]['details']['location'], $interviews[0]['details']['location_id']]);
    }

    public function test_a_bulk_move_gives_every_interview_the_location(): void
    {
        $second = $this->seeker('budi.bulk@example.test', name: 'Budi Santoso');
        $token = $this->tokenFor($second);
        $secondId = (int) $this->api('POST', "/jobs/{$this->job}/applications", ['resume_id' => $this->completeProfile($token)], $token)->assertCreated()->json('data.id');

        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", [
            'application_ids' => [$this->applicationId, $secondId], 'stage_id' => $this->stage('interview'),
            'interview' => $this->interviewSchedule(['format' => 'onsite', 'location_id' => 999999]),
        ], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-location');
        $this->assertSame(0, DB::table('recruitment_interviews')->count());

        $this->api('POST', "/company/jobs/{$this->job}/applications/bulk-move", [
            'application_ids' => [$this->applicationId, $secondId], 'stage_id' => $this->stage('interview'),
            'interview' => $this->interviewSchedule(['format' => 'onsite', 'location_id' => $this->locationId]),
        ], $this->recruiterToken)->assertOk();

        $rows = DB::table('recruitment_interviews')->get(['location', 'location_id']);
        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertSame([self::ADDRESS, $this->locationId], [$row->location, (int) $row->location_id]);
        }
    }
}
