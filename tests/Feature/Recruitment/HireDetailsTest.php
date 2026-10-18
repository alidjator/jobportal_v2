<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Src\Recruitment\Application\EraseApplicantData;
use Src\Recruitment\Domain\Events\ApplicationStageChanged;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-hiring.md` section 1: the join date and the note of a hire. */
final class HireDetailsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function day(int $offset): string
    {
        return now('Asia/Jakarta')->addDays($offset)->format('Y-m-d');
    }

    /** @param  array<string, mixed>|null  $hire */
    private function hire(?array $hire, ?string $token = null): TestResponse
    {
        $body = ['stage_id' => $this->stage('hired')];

        if ($hire !== null) {
            $body['hire'] = $hire;
        }

        return $this->api('POST', "/company/applications/{$this->applicationId}/move", $body, $token ?? $this->recruiterToken);
    }

    private function changeHire(array $body, ?string $token = null): TestResponse
    {
        return $this->api('PATCH', "/company/applications/{$this->applicationId}/hire", $body, $token ?? $this->recruiterToken);
    }

    private function row(): object
    {
        return DB::table('recruitment_applications')->where('id', $this->applicationId)->first();
    }

    /** @return list<string> */
    private function types(): array
    {
        $types = DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $this->seekerId)->pluck('type')->all();
        sort($types);

        return $types;
    }

    private function auditCount(): int
    {
        return DB::table('platform_audit_logs')->where('action', 'recruitment.hire_updated')->count();
    }

    // ----- the move -----

    public function test_a_move_to_hired_stores_the_details_and_the_event_carries_them(): void
    {
        $events = [];
        Event::listen(ApplicationStageChanged::class, function (ApplicationStageChanged $event) use (&$events): void {
            $events[] = $event;
        });

        $this->hire(['join_date' => $this->day(30), 'note' => '  Bring your ID card.  '])->assertOk();

        $this->assertSame($this->day(30), $this->row()->join_date);
        $this->assertSame('Bring your ID card.', $this->row()->hire_note);
        $this->assertCount(1, $events);
        $this->assertSame($this->day(30), $events[0]->hire?->joinDate->format('Y-m-d'));

        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.hire', ['join_date' => $this->day(30), 'note' => 'Bring your ID card.']);
        $this->api('GET', "/me/applications/{$this->applicationId}", token: $this->seekerToken)->assertOk()
            ->assertJsonPath('data.hire', ['join_date' => $this->day(30), 'note' => 'Bring your ID card.']);
        $this->api('GET', "/company/jobs/{$this->job}/applications", token: $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.0.hire.join_date', $this->day(30));
    }

    public function test_the_join_date_is_required_and_within_the_window_and_nothing_changes_when_refused(): void
    {
        $this->hire(null)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:join-date-required')->assertJsonStructure(['errors' => ['hire.join_date']]);
        $this->hire([])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:join-date-required');
        $this->hire(['join_date' => $this->day(-91)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-join-date');
        $this->hire(['join_date' => now('Asia/Jakarta')->addYear()->addDay()->format('Y-m-d')])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-join-date');
        $this->hire(['join_date' => '02-11-2026'])->assertStatus(422)->assertJsonValidationErrors(['hire.join_date']);
        $this->hire(['join_date' => $this->day(5), 'note' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonValidationErrors(['hire.note']);
        $this->assertSame('applied', $this->row()->stage_type);

        $this->hire(['join_date' => $this->day(-90)])->assertOk();
        $this->assertSame($this->day(-90), $this->row()->join_date);
    }

    public function test_a_note_with_a_foreign_link_is_refused(): void
    {
        $this->hire(['join_date' => $this->day(5), 'note' => 'Sign at https://evil.example/form'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
        $this->assertSame('applied', $this->row()->stage_type);
    }

    public function test_hire_details_are_refused_for_another_stage_and_cleared_when_the_application_leaves_hired(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('screening'), 'hire' => ['join_date' => $this->day(5)]], $this->recruiterToken)
            ->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-hire-input');

        $this->hire(['join_date' => $this->day(5), 'note' => 'Welcome'])->assertOk();
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('screening')], $this->recruiterToken)->assertOk();

        $this->assertNull($this->row()->join_date);
        $this->assertNull($this->row()->hire_note);
        $this->api('GET', "/company/applications/{$this->applicationId}", token: $this->recruiterToken)->assertJsonPath('data.hire', null);
    }

    public function test_a_bulk_move_to_hired_needs_the_details_and_gives_them_to_every_application(): void
    {
        $url = "/company/jobs/{$this->job}/applications/bulk-move";
        $stage = $this->stage('hired');

        $this->api('POST', $url, ['application_ids' => [$this->applicationId], 'stage_id' => $stage], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:join-date-required');
        $this->assertSame('applied', $this->row()->stage_type);

        $this->api('POST', $url, ['application_ids' => [$this->applicationId], 'stage_id' => $stage, 'hire' => ['join_date' => $this->day(14), 'note' => 'See you']], $this->recruiterToken)->assertOk();

        $this->assertSame($this->day(14), $this->row()->join_date);
        $this->assertSame('See you', $this->row()->hire_note);
    }

    // ----- the offer -----

    public function test_an_accepted_offer_hires_with_the_start_date_and_the_default_text(): void
    {
        $this->api('PUT', '/company/message-defaults', ['hired' => 'Welcome to the team.'], $this->adminToken)->assertOk()->assertJsonPath('data.hired', 'Welcome to the team.');

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('offer'), 'offer' => $this->offerDetails(['start_date' => $this->day(21)])], $this->recruiterToken)->assertOk();
        $this->api('POST', "/me/applications/{$this->applicationId}/offer/accept", token: $this->seekerToken)->assertOk();

        $this->assertSame($this->day(21), $this->row()->join_date);
        $this->assertSame('Welcome to the team.', $this->row()->hire_note);
    }

    // ----- the change later -----

    public function test_the_details_change_later_tell_the_applicant_and_are_audited_without_the_note(): void
    {
        $this->hire(['join_date' => $this->day(30), 'note' => 'First note'])->assertOk();
        DB::table('notifications')->delete();

        $this->changeHire(['join_date' => $this->day(40)])->assertNoContent();

        $this->assertSame($this->day(40), $this->row()->join_date);
        $this->assertSame('First note', $this->row()->hire_note);
        $this->assertSame(['application.hire_updated'], $this->types());
        $this->assertSame(1, $this->auditCount());

        $metadata = json_decode((string) DB::table('platform_audit_logs')->where('action', 'recruitment.hire_updated')->value('metadata'), true);
        $this->assertSame(['join_date' => $this->day(30)], $metadata['old']);
        $this->assertSame(['join_date' => $this->day(40)], $metadata['new']);
        $this->assertFalse($metadata['note_changed']);
        $this->assertStringNotContainsString('First note', json_encode($metadata));

        $this->changeHire(['note' => 'Second note'])->assertNoContent();
        $this->assertSame('Second note', $this->row()->hire_note);
        $this->assertTrue(json_decode((string) DB::table('platform_audit_logs')->where('action', 'recruitment.hire_updated')->orderByDesc('id')->value('metadata'), true)['note_changed']);

        $this->changeHire(['note' => null])->assertNoContent();
        $this->assertNull($this->row()->hire_note);
        $this->assertSame(3, $this->auditCount());
    }

    public function test_a_change_that_changes_nothing_sends_and_audits_nothing(): void
    {
        $this->hire(['join_date' => $this->day(30), 'note' => 'Same'])->assertOk();
        DB::table('notifications')->delete();

        $this->changeHire(['join_date' => $this->day(30), 'note' => 'Same'])->assertNoContent();
        $this->changeHire([])->assertNoContent();

        $this->assertSame([], $this->types());
        $this->assertSame(0, $this->auditCount());
    }

    public function test_the_change_follows_the_same_rules_as_the_move(): void
    {
        $this->changeHire(['join_date' => $this->day(5)])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:application-not-hired');

        $this->hire(['join_date' => $this->day(30)])->assertOk();

        $this->changeHire(['join_date' => null])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:join-date-required');
        $this->changeHire(['join_date' => $this->day(-91)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-join-date');
        $this->changeHire(['note' => 'Pay at bit.ly/abc'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:message-link-not-allowed');
        $this->changeHire(['note' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonValidationErrors(['note']);

        $this->changeHire(['note' => 'Hello'], $this->seekerToken)->assertStatus(403);
        $this->changeHire(['note' => 'Hello'], $this->secondRecruiterToken)->assertStatus(404);
        $this->assertSame($this->day(30), $this->row()->join_date);
    }

    public function test_a_hire_from_before_the_details_existed_needs_a_date_first(): void
    {
        $this->hire(['join_date' => $this->day(30)])->assertOk();
        DB::table('recruitment_applications')->where('id', $this->applicationId)->update(['join_date' => null, 'hire_note' => null]);

        $this->changeHire(['note' => 'Hello'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:join-date-required');
        $this->changeHire(['join_date' => $this->day(10), 'note' => 'Hello'])->assertNoContent();
        $this->assertSame($this->day(10), $this->row()->join_date);
    }

    public function test_the_erasure_of_the_applicant_clears_the_note_and_keeps_the_date(): void
    {
        $this->hire(['join_date' => $this->day(30), 'note' => 'Hello Siti'])->assertOk();

        $this->app->make(EraseApplicantData::class)->execute($this->seekerId);

        $this->assertNull($this->row()->hire_note);
        $this->assertSame($this->day(30), $this->row()->join_date);
    }
}
