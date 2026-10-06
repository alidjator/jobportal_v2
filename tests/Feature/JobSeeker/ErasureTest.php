<?php

namespace Tests\Feature\JobSeeker;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\JobSeeker\Application\EraseJobSeekerData;
use Src\Shared\Application\FileStorage;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class ErasureTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    /** Fills every JobSeeker table for the user, with two resume files. */
    private function fill(int $userId): void
    {
        $now = now();
        DB::table('jobseeker_profiles')->insert(['user_id' => $userId, 'phone' => '081234567890', 'headline' => 'Developer', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('jobseeker_experiences')->insert(['user_id' => $userId, 'job_title' => 'Developer', 'company_name' => 'PT Maju', 'start_date' => '2020-01-01', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('jobseeker_educations')->insert([
            'user_id' => $userId, 'education_level_id' => $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]), 'institution' => 'ITB', 'start_year' => 2014, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('jobseeker_skills')->insert(['user_id' => $userId, 'skill_id' => $this->skillId('php'), 'position' => 1]);
        DB::table('jobseeker_languages')->insert(['user_id' => $userId, 'language_id' => $this->referenceId('reference_languages', 'english', ['code' => 'en']), 'proficiency' => 'fluent']);
        DB::table('jobseeker_links')->insert(['user_id' => $userId, 'type' => 'github', 'url' => 'https://github.com/siti']);

        foreach (['a', 'b'] as $name) {
            Storage::put("jobseeker/resumes/{$userId}-{$name}.pdf", '%PDF-1.4');
            DB::table('jobseeker_resumes')->insert(['user_id' => $userId, 'name' => "{$name}.pdf", 'path' => "jobseeker/resumes/{$userId}-{$name}.pdf", 'size_bytes' => 8, 'created_at' => $now]);
        }
    }

    /** @return array<string, int> */
    private function counts(int $userId): array
    {
        $counts = [];

        foreach (['jobseeker_profiles', 'jobseeker_experiences', 'jobseeker_educations', 'jobseeker_skills', 'jobseeker_languages', 'jobseeker_links', 'jobseeker_resumes'] as $table) {
            $counts[$table] = DB::table($table)->where('user_id', $userId)->count();
        }

        return $counts;
    }

    public function test_the_anonymisation_event_erases_every_row_and_file_of_that_user_only(): void
    {
        $this->fill(1);
        $this->fill(2);

        Event::dispatch(new UserAnonymized(1, now()->toDateTimeImmutable()));

        $this->assertSame(array_fill_keys(array_keys($this->counts(1)), 0), $this->counts(1));
        $this->assertSame([], array_values(array_filter(Storage::allFiles('jobseeker/resumes'), fn (string $file): bool => str_contains($file, '/1-'))));
        $this->assertSame([...array_fill_keys(array_keys($this->counts(2)), 1), 'jobseeker_resumes' => 2], $this->counts(2));
        $this->assertCount(2, Storage::allFiles('jobseeker/resumes'));
    }

    public function test_running_it_again_or_for_a_user_without_data_does_nothing(): void
    {
        $this->fill(1);
        $erase = $this->app->make(EraseJobSeekerData::class);

        $erase->execute(1);
        $erase->execute(1);
        $erase->execute(999);

        $this->assertSame(0, array_sum($this->counts(1)));
    }

    public function test_a_failing_file_deletion_keeps_the_rows_so_a_retry_can_finish(): void
    {
        $this->fill(1);
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
            $this->app->make(EraseJobSeekerData::class)->execute(1);
            $this->fail('The erasure did not fail.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(2, $this->counts(1)['jobseeker_resumes']);
        $this->assertSame(1, $this->counts(1)['jobseeker_profiles']);

        $this->app->instance(FileStorage::class, $real);
        $this->app->make(EraseJobSeekerData::class)->execute(1);

        $this->assertSame(0, array_sum($this->counts(1)));
        $this->assertSame([], Storage::allFiles('jobseeker/resumes'));
    }
}
