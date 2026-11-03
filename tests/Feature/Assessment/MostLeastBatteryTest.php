<?php

namespace Tests\Feature\Assessment;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Assessment\Application\Reports\ReportDocument;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\Concerns\BuildsAssessmentFixtures;
use Tests\FeatureTestCase;

/**
 * The most/least question and the DISC profile (`docs/design/phase-8-disc-profile.md`, #47, part 1): the rules of the question, the dimensions of a battery, the answer of a candidate, the twelve derived scales
 * (the count of the statement picked as most, as least and their difference) and the profile placeholders of the report. The statements here are obviously fictitious.
 */
final class MostLeastBatteryTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;
    use BuildsAssessmentFixtures;

    private int $applicationId;

    private int $invitationId = 0;

    private ?string $attemptSession = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->addAssessmentFixtures();
        $job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    // ----- helpers -----

    /** @return array<string, mixed> a group of four statements, one per dimension, in the order d, i, s, c */
    private function group(string $text, array $dimensions = ['d', 'i', 's', 'c'], array $extra = []): array
    {
        return [
            'type' => 'most_least', 'text' => $text,
            'options' => array_map(static fn (string $dimension): array => ['label' => "Statement {$dimension} of ".$text, 'scale_key' => $dimension], $dimensions),
            ...$extra,
        ];
    }

    private function emptyBattery(): array
    {
        $id = $this->newTest('provider', ['title' => 'Behaviour profile']);
        $section = (int) $this->provider('POST', "tests/{$id}/sections", $this->section('Groups', 600))->assertCreated()->json('data.id');

        return [$id, $section];
    }

    /** @return list<array<string, mixed>> the norm rows of the twelve scales of d, i, s and c */
    private function norms(): array
    {
        $rows = [];

        foreach (['d', 'i', 's', 'c'] as $dimension) {
            foreach (['most', 'least'] as $graph) {
                $rows[] = ['scale_key' => "{$dimension}_{$graph}", 'raw_min' => 0, 'raw_max' => 1, 'standard_score' => 40, 'band' => 'Low'];
                $rows[] = ['scale_key' => "{$dimension}_{$graph}", 'raw_min' => 2, 'raw_max' => 10, 'standard_score' => 60, 'band' => 'High'];
            }

            $rows[] = ['scale_key' => "{$dimension}_change", 'raw_min' => -10, 'raw_max' => -1, 'standard_score' => 40, 'band' => 'Low'];
            $rows[] = ['scale_key' => "{$dimension}_change", 'raw_min' => 0, 'raw_max' => 10, 'standard_score' => 60, 'band' => 'High'];
        }

        return $rows;
    }

    /** A published battery of three groups, with norms for the twelve scales and a template that uses the profile placeholders; the company may use it. */
    private function discBattery(): int
    {
        [$id, $section] = $this->emptyBattery();

        foreach (['One', 'Two', 'Three'] as $name) {
            $this->provider('POST', "sections/{$section}/questions", $this->group("Group {$name}"))->assertCreated();
        }

        $this->provider('PUT', "tests/{$id}/norms", ['norms' => $this->norms()])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Most {{profile:most}}, least {{profile:least}}, change {{profile:change}}. D most {{scale:d_most:raw}}, C change {{scale:c_change:raw}}.'])->assertOk();
        $this->provider('POST', "tests/{$id}/publish")->assertOk();

        $this->assessmentAdmin('POST', "companies/{$this->companyId}/batteries", ['test_id' => $id])->assertNoContent();
        $this->overrideSetting('assessment.psychological_enabled', true);

        return $id;
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function invite(int $testId): void
    {
        $due = Carbon::now()->addDays(3)->utc()->format('Y-m-d\TH:i:s\Z');

        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $this->stage('assessment'), 'assessment' => ['test_id' => $testId, 'due_at' => $due]], $this->recruiterToken)->assertOk();
        $this->invitationId = (int) DB::table('assessment_invitations')->orderByDesc('id')->value('id');
    }

    /** @param  array<string, mixed>  $body */
    private function take(string $method, string $uri, array $body = []): TestResponse
    {
        $response = $this->api($method, "/me/assessment/invitations/{$this->invitationId}/{$uri}", $body, $this->seekerToken, $this->attemptSession === null ? [] : ['X-Attempt-Session' => $this->attemptSession]);

        if ($uri === 'start' && is_string($session = $response->json('data.attempt_session'))) {
            $this->attemptSession = $session;
        }

        return $response;
    }

    private function questionId(string $text): int
    {
        return (int) DB::table('assessment_questions')->where('text', $text)->value('id');
    }

    // ----- the question -----

    public function test_a_most_least_question_has_three_to_six_statements_each_with_a_dimension_and_no_score(): void
    {
        [, $section] = $this->emptyBattery();
        $post = fn (array $question): TestResponse => $this->provider('POST', "sections/{$section}/questions", $question);

        $post($this->group('Too few', ['d', 'i']))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-question');
        $post($this->group('Too many', ['d', 'i', 's', 'c', 'a', 'b', 'e']))->assertStatus(422);
        $post($this->group('Uppercase', ['D', 'I', 'S', 'C']))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-question');
        $post($this->group('Underscore', ['d_x', 'i', 's', 'c']))->assertStatus(422);
        $post([...$this->group('Scored'), 'options' => [['label' => 'A', 'scale_key' => 'd', 'score_value' => 1], ['label' => 'B', 'scale_key' => 'i'], ['label' => 'C', 'scale_key' => 's']]])->assertStatus(422);
        $post([...$this->group('Correct'), 'options' => [['label' => 'A', 'scale_key' => 'd', 'is_correct' => true], ['label' => 'B', 'scale_key' => 'i'], ['label' => 'C', 'scale_key' => 's']]])->assertStatus(422);
        $post([...$this->group('Nodim'), 'options' => [['label' => 'A', 'scale_key' => 'd'], ['label' => 'B'], ['label' => 'C', 'scale_key' => 's']]])->assertStatus(422);
        $post($this->group('With a scale', extra: ['scale_key' => 'd']))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-question');

        // Points are not asked for: the question adds to no total.
        $created = $post($this->group('Fine'))->assertCreated();
        $this->assertSame(0, (int) DB::table('assessment_questions')->where('id', $created->json('data.id'))->value('points'));
    }

    public function test_only_a_psychological_battery_can_use_the_most_least_question(): void
    {
        $skill = $this->newTest('company');
        $section = (int) $this->company('POST', "tests/{$skill}/sections", $this->section())->assertCreated()->json('data.id');

        $this->company('POST', "sections/{$section}/questions", $this->group('Not here'))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-question');
    }

    public function test_a_battery_publishes_only_with_one_set_of_at_least_two_dimensions(): void
    {
        [$id, $section] = $this->emptyBattery();
        $this->provider('POST', "sections/{$section}/questions", $this->group('First'))->assertCreated();
        $this->provider('POST', "sections/{$section}/questions", $this->group('Second', ['d', 'i', 's']))->assertCreated();
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => $this->norms()])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Most {{profile:most}}'])->assertOk();

        $this->provider('POST', "tests/{$id}/publish")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:test-incomplete');

        // The same set everywhere publishes; one dimension alone does not.
        DB::table('assessment_questions')->where('text', 'Second')->delete();
        $this->provider('POST', "sections/{$section}/questions", $this->group('Second', ['d', 'i', 's', 'c']))->assertCreated();
        $this->provider('POST', "tests/{$id}/publish")->assertOk();
    }

    // ----- the scales and the report template -----

    public function test_the_scales_are_the_three_graphs_of_each_dimension_never_the_bare_dimension(): void
    {
        [$id, $section] = $this->emptyBattery();
        $this->provider('POST', "sections/{$section}/questions", $this->group('First'))->assertCreated();

        $this->provider('PUT', "tests/{$id}/norms", ['norms' => [['scale_key' => 'd', 'raw_min' => 0, 'raw_max' => 3, 'standard_score' => 50, 'band' => 'Mid']]])->assertStatus(422);
        // A negative range is fine for the change.
        $this->provider('PUT', "tests/{$id}/norms", ['norms' => $this->norms()])->assertOk();
        $this->provider('GET', "tests/{$id}/norms")->assertOk()->assertJsonCount(count($this->norms()), 'data');

        $this->provider('PUT', "tests/{$id}/report-template", ['body' => '{{profile:most}} {{profile:least}} {{profile:change}} {{scale:i_change:standard}}'])->assertOk();
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => '{{profile:other}}'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-report-template');
        $this->provider('PUT', "tests/{$id}/report-template", ['body' => '{{scale:d:raw}}'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-report-template');
    }

    public function test_a_profile_placeholder_needs_a_battery_of_most_least_questions(): void
    {
        $id = $this->newTest('provider', ['title' => 'Personality']);
        $section = (int) $this->provider('POST', "tests/{$id}/sections", $this->section('Statements', 300))->assertCreated()->json('data.id');
        $this->provider('POST', "sections/{$section}/questions", [
            'type' => 'likert', 'text' => 'I enjoy meeting new people.', 'scale_key' => 'extraversion',
            'options' => [['label' => 'Never', 'score_value' => 1], ['label' => 'Always', 'score_value' => 3]],
        ])->assertCreated();

        $this->provider('PUT', "tests/{$id}/report-template", ['body' => 'Profile {{profile:most}}'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-report-template');
    }

    // ----- taking it -----

    public function test_an_answer_names_two_different_statements_most_first_then_least(): void
    {
        $this->invite($this->discBattery());
        $this->take('POST', 'start', ['consent' => true])->assertCreated();
        $one = $this->questionId('Group One');
        $answer = fn (array $positions) => $this->take('PUT', "attempt/questions/{$one}/answer", ['option_positions' => $positions]);

        $answer([1])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-answer');
        $answer([1, 1])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-answer');
        $answer([1, 5])->assertStatus(422);
        $answer([0, 2])->assertStatus(422);
        $answer([1, 2, 3])->assertStatus(422);
        $this->take('PUT', "attempt/questions/{$one}/answer", ['text' => 'D'])->assertStatus(422);

        $answer([1, 4])->assertNoContent();
        $this->assertSame([1, 4], json_decode((string) DB::table('assessment_answers')->where('question_id', $one)->value('option_positions'), true), 'The order is kept: the most statement first.');
        // Answering again replaces it.
        $answer([4, 1])->assertNoContent();
        $this->assertSame([4, 1], json_decode((string) DB::table('assessment_answers')->where('question_id', $one)->value('option_positions'), true));
    }

    public function test_the_answers_give_the_twelve_scales_and_the_profile_letters_of_the_report(): void
    {
        $this->invite($this->discBattery());
        $this->take('POST', 'start', ['consent' => true])->assertCreated();

        // Positions 1 to 4 are the dimensions d, i, s, c. Group One: most d, least c. Group Two: most d, least s. Group Three: most i, least c.
        foreach ([['Group One', [1, 4]], ['Group Two', [1, 3]], ['Group Three', [2, 4]]] as [$text, $positions]) {
            $this->take('PUT', 'attempt/questions/'.$this->questionId($text).'/answer', ['option_positions' => $positions])->assertNoContent();
        }

        $this->take('POST', 'attempt/submit')->assertOk();

        $scales = json_decode((string) DB::table('assessment_results')->value('scale_scores'), true);
        ksort($scales);
        $this->assertEquals([
            'c_change' => -2, 'c_least' => 2, 'c_most' => 0,
            'd_change' => 2, 'd_least' => 0, 'd_most' => 2,
            'i_change' => 1, 'i_least' => 0, 'i_most' => 1,
            's_change' => -1, 's_least' => 1, 's_most' => 0,
        ], $scales);

        // The standard score of each graph (60 from a count of 2, change from 0) gives the high dimensions: most D, least C, change D and I.
        $this->assertSame('Most D, least C, change DI. D most 2, C change -2.', (string) DB::table('assessment_reports')->value('body'));
    }

    public function test_a_battery_with_a_group_left_unanswered_counts_only_the_answered_ones(): void
    {
        $this->invite($this->discBattery());
        $this->take('POST', 'start', ['consent' => true])->assertCreated();
        $this->take('PUT', 'attempt/questions/'.$this->questionId('Group One').'/answer', ['option_positions' => [2, 3]])->assertNoContent();
        $this->take('POST', 'attempt/submit')->assertOk();

        $scales = json_decode((string) DB::table('assessment_results')->value('scale_scores'), true);

        $this->assertEquals(1, $scales['i_most']);
        $this->assertEquals(1, $scales['s_least']);
        $this->assertEquals(0, $scales['d_most']);
        $this->assertEquals(-1, $scales['s_change']);
        $this->assertCount(12, $scales);
    }

    // ----- the charts of the report (D5) -----

    /** The candidate answers the three groups (most d / least c, most d / least s, most i / least c) and hands in; returns the report id. */
    private function submitted(): int
    {
        $this->invite($this->discBattery());
        $this->take('POST', 'start', ['consent' => true])->assertCreated();

        foreach ([['Group One', [1, 4]], ['Group Two', [1, 3]], ['Group Three', [2, 4]]] as [$text, $positions]) {
            $this->take('PUT', 'attempt/questions/'.$this->questionId($text).'/answer', ['option_positions' => $positions])->assertNoContent();
        }

        $this->take('POST', 'attempt/submit')->assertOk();

        return (int) DB::table('assessment_reports')->value('id');
    }

    public function test_the_review_carries_the_standard_scores_the_charts_are_drawn_from(): void
    {
        $reportId = $this->submitted();

        $scales = collect($this->provider('GET', "reviews/{$reportId}")->assertOk()->json('data.scales'))->keyBy('scale');

        $this->assertCount(12, $scales);
        $this->assertEquals(60, $scales['d_most']['standard']);
        $this->assertEquals(40, $scales['c_most']['standard']);
        $this->assertEquals(-2, $scales['c_change']['raw']);
        $this->assertEquals(40, $scales['c_change']['standard']);
    }

    public function test_the_signed_pdf_draws_the_profile_chart(): void
    {
        $reportId = $this->submitted();
        $this->provider('PUT', "reviews/{$reportId}", ['body' => (string) DB::table('assessment_reports')->value('body'), 'explanation' => 'You act at once and press on.', 'review_notes' => null, 'recommendation' => 'consider'])->assertOk();
        $this->provider('POST', "reviews/{$reportId}/sign")->assertOk();

        $pdf = (string) Storage::get((string) DB::table('assessment_reports')->value('pdf_path'));
        $this->assertStringStartsWith('%PDF', $pdf);

        $document = new ReportDocument(
            'Lembaga', 'PT Lembaga', 'LEMB/2026/00001', 'Behaviour profile', 'Siti', 'Text', [
                ['scale' => 'd_most', 'raw' => 2.0, 'standard' => 60.0, 'band' => 'High', 'norm_group' => null],
                ['scale' => 'd_least', 'raw' => 0.0, 'standard' => 40.0, 'band' => 'Low', 'norm_group' => null],
                ['scale' => 'd_change', 'raw' => 2.0, 'standard' => 60.0, 'band' => 'High', 'norm_group' => null],
                ['scale' => 'i_most', 'raw' => 1.0, 'standard' => 40.0, 'band' => 'Low', 'norm_group' => null],
                ['scale' => 'i_least', 'raw' => 0.0, 'standard' => 40.0, 'band' => 'Low', 'norm_group' => null],
                ['scale' => 'i_change', 'raw' => 1.0, 'standard' => 60.0, 'band' => 'High', 'norm_group' => null],
            ], 'Dewi', 'STR-1', 'SIPP-1', new DateTimeImmutable('2026-11-03'),
        );
        $html = view('assessment.report', ['report' => $document])->render();

        $this->assertStringContainsString('<h2>Profile</h2>', $html);
        $this->assertSame(3, substr_count($html, '<polyline'));
        $this->assertStringContainsString('Paling tidak', $html);

        // A battery that is not of this family has no chart.
        $plain = new ReportDocument('Lembaga', 'PT Lembaga', 'LEMB/2026/00002', 'Personality', 'Siti', 'Text', [['scale' => 'extraversion', 'raw' => 3.0, 'standard' => 60.0, 'band' => 'High', 'norm_group' => null]], 'Dewi', 'STR-1', 'SIPP-1', new DateTimeImmutable('2026-11-03'));
        $this->assertStringNotContainsString('<h2>Profile</h2>', view('assessment.report', ['report' => $plain])->render());
    }
}
