<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Platform\Application\Settings\SettingsStore;
use Src\Recruitment\Application\RecruitmentSettings;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Application\Settings\SettingsCatalog;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class ScreeningSchemaTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private function question(string $type = 'single_choice', ?int $companyId = null): int
    {
        return (int) DB::table('recruitment_screening_questions')->insertGetId([
            'company_id' => $companyId ?? $this->companyRowId(), 'type' => $type, 'text' => 'Can you relocate?', 'created_by_user_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function option(int $questionId, string $label, int $position = 1): int
    {
        return (int) DB::table('recruitment_screening_question_options')->insertGetId(['question_id' => $questionId, 'label' => $label, 'position' => $position]);
    }

    private function jobQuestion(int $jobId, int $questionId, array $extra = []): int
    {
        return (int) DB::table('recruitment_job_questions')->insertGetId([
            'job_id' => $jobId, 'question_id' => $questionId, 'position' => 1, 'created_at' => now(), 'updated_at' => now(), ...$extra,
        ]);
    }

    public function test_the_four_tables_start_empty(): void
    {
        foreach (['recruitment_screening_questions', 'recruitment_screening_question_options', 'recruitment_job_questions', 'recruitment_job_question_ideal_options'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    public function test_the_question_type_is_checked_by_the_database(): void
    {
        foreach (['text', 'yes_no', 'single_choice', 'multiple_choice', 'number'] as $type) {
            $this->question($type);
        }

        $this->expectException(QueryException::class);
        $this->question('essay');
    }

    public function test_a_question_needs_a_real_company(): void
    {
        $this->expectException(QueryException::class);
        $this->question('text', 999_999);
    }

    public function test_the_options_of_a_question_are_unique_ignoring_letter_case(): void
    {
        $question = $this->question();
        $this->option($question, 'Remote');
        $this->option($this->question(), 'remote');

        $this->expectException(QueryException::class);
        $this->option($question, 'REMOTE', 2);
    }

    public function test_deleting_a_question_deletes_its_options_but_not_while_a_job_uses_it(): void
    {
        $free = $this->question();
        $this->option($free, 'A');
        DB::table('recruitment_screening_questions')->where('id', $free)->delete();
        $this->assertSame(0, DB::table('recruitment_screening_question_options')->count());

        $used = $this->question();
        $this->jobQuestion(10, $used);

        $this->expectException(QueryException::class);
        DB::table('recruitment_screening_questions')->where('id', $used)->delete();
    }

    public function test_a_question_is_attached_to_a_job_once(): void
    {
        $question = $this->question();
        $this->jobQuestion(10, $question);
        $this->jobQuestion(11, $question);

        $this->expectException(QueryException::class);
        $this->jobQuestion(10, $question);
    }

    public function test_ideal_options_follow_the_attachment_and_protect_the_option(): void
    {
        $question = $this->question();
        $option = $this->option($question, 'A');
        $other = $this->option($question, 'B', 2);
        $attached = $this->jobQuestion(10, $question, ['is_required' => true, 'is_must_have' => true]);

        DB::table('recruitment_job_question_ideal_options')->insert(['job_question_id' => $attached, 'option_id' => $option]);

        try {
            DB::table('recruitment_job_question_ideal_options')->insert(['job_question_id' => $attached, 'option_id' => $option]);
            $this->fail('The same ideal option was stored twice.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        try {
            DB::table('recruitment_screening_question_options')->where('id', $option)->delete();
            $this->fail('An ideal option was deleted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        DB::table('recruitment_screening_question_options')->where('id', $other)->delete();
        DB::table('recruitment_job_questions')->where('id', $attached)->delete();
        $this->assertSame(0, DB::table('recruitment_job_question_ideal_options')->count());
    }

    public function test_the_screening_settings_have_their_defaults_and_overrides(): void
    {
        $settings = $this->app->make(Settings::class);
        $this->assertTrue($settings->boolean(RecruitmentSettings::SCREENING_ENABLED));
        $this->assertSame(5, $settings->integer(RecruitmentSettings::MAX_SCREENING_QUESTIONS));

        $store = $this->app->make(SettingsStore::class);
        $store->setCompanyOverride(RecruitmentSettings::SCREENING_ENABLED, 42, false, null);
        $store->setCompanyOverride(RecruitmentSettings::MAX_SCREENING_QUESTIONS, 42, 8, null);

        $settings = $this->app->make(Settings::class);
        $this->assertFalse($settings->boolean(RecruitmentSettings::SCREENING_ENABLED, 42));
        $this->assertSame(8, $settings->integer(RecruitmentSettings::MAX_SCREENING_QUESTIONS, 42));
        $this->assertTrue($settings->boolean(RecruitmentSettings::SCREENING_ENABLED, 43));
        $this->assertSame(5, $settings->integer(RecruitmentSettings::MAX_SCREENING_QUESTIONS, 43));
    }

    public function test_the_question_limit_stays_between_1_and_20(): void
    {
        $definition = $this->app->make(SettingsCatalog::class)->find(RecruitmentSettings::MAX_SCREENING_QUESTIONS);

        $this->assertSame(1, $definition->normalize(1));
        $this->assertSame(20, $definition->normalize(20));

        foreach ([0, 21, -1] as $bad) {
            try {
                $definition->normalize($bad);
                $this->fail("{$bad} was accepted");
            } catch (BusinessRuleViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
