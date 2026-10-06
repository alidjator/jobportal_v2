<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Recruitment\Application\EraseApplicantData;
use Src\Shared\Application\FileStorage;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class ApplicantErasureTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $otherId;

    private string $otherToken;

    private int $otherResumeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->otherId = $this->seeker('other@example.test');
        $this->otherToken = $this->tokenFor($this->otherId);
        $this->otherResumeId = $this->completeProfile($this->otherToken);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /**
     * Both job seekers apply to a job with a text, a yes/no and a choice question; the first one also withdraws a second application with a note.
     *
     * @return array{job: int, text: int, yesNo: int, choice: int}
     */
    private function applyBoth(): array
    {
        $job = $this->insertJob('Backend Developer', 'active', attributes: ['deadline' => '2026-12-31']);
        $text = $this->question('text', 'Why this job?');
        $yesNo = $this->question('yes_no', 'Can you relocate?');
        $choice = $this->question('single_choice', 'Where do you live?', ['Jakarta', 'Bandung']);
        $this->setQuestions($job, array_map(fn (int $id): array => ['question_id' => $id], [$text, $yesNo, $choice]));

        foreach ([[$this->seekerToken, $this->resumeId], [$this->otherToken, $this->otherResumeId]] as [$token, $resumeId]) {
            $this->api('POST', "/jobs/{$job}/applications", ['resume_id' => $resumeId, 'cover_letter' => 'Please hire me.', 'answers' => [
                ['question_id' => $text, 'value' => 'Because I like it.'],
                ['question_id' => $yesNo, 'value' => true],
                ['question_id' => $choice, 'value' => $this->optionId($choice, 'Bandung')],
            ]], $token)->assertCreated();
        }

        $second = $this->insertJob('Second job', 'active', attributes: ['deadline' => '2026-12-31']);
        $id = (int) $this->api('POST', "/jobs/{$second}/applications", ['resume_id' => $this->resumeId], $this->seekerToken)->assertCreated()->json('data.id');
        $this->api('POST', "/me/applications/{$id}/withdraw", ['note' => 'Moved to Surabaya'], $this->seekerToken)->assertNoContent();

        return ['job' => $job, 'text' => $text, 'yesNo' => $yesNo, 'choice' => $choice];
    }

    public function test_the_anonymisation_event_erases_the_personal_content_of_that_users_applications_only(): void
    {
        $fixture = $this->applyBoth();
        $mine = DB::table('recruitment_applications')->where('user_id', $this->seekerId)->get();
        $paths = $mine->pluck('resume_path')->all();
        $this->assertCount(2, $paths);

        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        foreach (DB::table('recruitment_applications')->where('user_id', $this->seekerId)->get() as $row) {
            $this->assertNull($row->cover_letter);
            $this->assertNull($row->withdrawn_note);
            $this->assertNull($row->resume_name);
            $this->assertNull($row->resume_path);
            $this->assertNull($row->resume_size_bytes);
            $this->assertNull($row->profile_snapshot);
        }

        foreach ($paths as $path) {
            Storage::assertMissing($path);
        }

        // What stays: the rows, their stage, the history and the answers that are not free text.
        $this->assertSame(2, DB::table('recruitment_applications')->where('user_id', $this->seekerId)->count());
        $this->assertSame(['applied', 'withdrawn'], DB::table('recruitment_applications')->where('user_id', $this->seekerId)->orderBy('id')->pluck('stage_type')->all());
        $this->assertSame(3, DB::table('recruitment_application_stage_history')->whereIn('application_id', $mine->pluck('id'))->count());

        $answers = DB::table('recruitment_application_answers')->where('application_id', $mine[0]->id)->get()->keyBy('question_id');
        $this->assertCount(3, $answers);
        $this->assertNull($answers[$fixture['text']]->answer_text);
        $this->assertSame(1, (int) $answers[$fixture['yesNo']]->answer_bool);
        $this->assertSame(1, DB::table('recruitment_application_answer_options')->where('answer_id', $answers[$fixture['choice']]->id)->count());

        // The other job seeker's application is untouched.
        $other = DB::table('recruitment_applications')->where('user_id', $this->otherId)->first();
        $this->assertSame('Please hire me.', $other->cover_letter);
        $this->assertNotNull($other->resume_path);
        $this->assertNotNull($other->profile_snapshot);
        Storage::assertExists($other->resume_path);
        $this->assertSame('Because I like it.', DB::table('recruitment_application_answers')->where('application_id', $other->id)->where('question_id', $fixture['text'])->value('answer_text'));
    }

    public function test_running_it_again_or_for_a_user_without_applications_does_nothing(): void
    {
        $this->applyBoth();
        $erase = $this->app->make(EraseApplicantData::class);

        $erase->execute($this->seekerId);
        $erase->execute($this->seekerId);
        $erase->execute(999999);

        $this->assertSame(2, DB::table('recruitment_applications')->where('user_id', $this->seekerId)->count());
        $this->assertNotNull(DB::table('recruitment_applications')->where('user_id', $this->otherId)->value('profile_snapshot'));
    }

    public function test_a_failing_file_deletion_keeps_the_content_so_a_retry_can_finish(): void
    {
        $this->applyBoth();
        $real = $this->app->make(FileStorage::class);
        $this->app->instance(FileStorage::class, new class($real) implements FileStorage
        {
            public function __construct(private readonly FileStorage $real) {}

            public function put(string $path, string $contents): void
            {
                $this->real->put($path, $contents);
            }

            public function get(string $path): ?string
            {
                return $this->real->get($path);
            }

            public function delete(string $path): void
            {
                throw new RuntimeException('storage unavailable');
            }
        });

        try {
            $this->app->make(EraseApplicantData::class)->execute($this->seekerId);
            $this->fail('The erasure did not fail.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(2, DB::table('recruitment_applications')->where('user_id', $this->seekerId)->whereNotNull('resume_path')->count());
        $this->assertSame(2, DB::table('recruitment_applications')->where('user_id', $this->seekerId)->whereNotNull('profile_snapshot')->count());

        $this->app->instance(FileStorage::class, $real);
        $this->app->make(EraseApplicantData::class)->execute($this->seekerId);

        $this->assertSame(0, DB::table('recruitment_applications')->where('user_id', $this->seekerId)->whereNotNull('resume_path')->count());
        $this->assertCount(1, Storage::allFiles('recruitment/applications'));
    }

    public function test_an_erased_application_still_lists_and_shows_without_the_personal_content(): void
    {
        $this->applyBoth();
        $id = (int) DB::table('recruitment_applications')->where('user_id', $this->seekerId)->orderBy('id')->value('id');

        Event::dispatch(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        $detail = $this->api('GET', "/me/applications/{$id}", token: $this->seekerToken)->assertOk();
        $detail->assertJsonPath('data.cover_letter', null)->assertJsonPath('data.resume', null)->assertJsonPath('data.answers.0.text', null);
        $this->api('GET', "/me/applications/{$id}/resume", token: $this->seekerToken)->assertStatus(404);
    }
}
