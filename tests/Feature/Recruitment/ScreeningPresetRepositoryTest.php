<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Recruitment\Domain\IdealAnswer;
use Src\Recruitment\Domain\JobQuestionDraft;
use Src\Recruitment\Domain\QuestionType;
use Src\Recruitment\Domain\ScreeningPresetRepository;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class ScreeningPresetRepositoryTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private function repository(): ScreeningPresetRepository
    {
        return $this->app->make(ScreeningPresetRepository::class);
    }

    private function question(string $type = 'yes_no', ?int $companyId = null): int
    {
        return (int) DB::table('recruitment_screening_questions')->insertGetId([
            'company_id' => $companyId ?? $this->companyRowId(), 'type' => $type, 'text' => 'Can you relocate?', 'created_by_user_id' => $this->userRowId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function option(int $questionId, string $label, int $position = 1): int
    {
        return (int) DB::table('recruitment_screening_question_options')->insertGetId(['question_id' => $questionId, 'label' => $label, 'position' => $position]);
    }

    public function test_the_three_tables_start_empty(): void
    {
        foreach (['recruitment_screening_presets', 'recruitment_screening_preset_questions', 'recruitment_screening_preset_ideal_options'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    public function test_a_preset_is_stored_and_read_back_with_its_questions_in_order(): void
    {
        $yesNo = $this->question('yes_no');
        $choice = $this->question('multiple_choice');
        $php = $this->option($choice, 'PHP');
        $go = $this->option($choice, 'Go', 2);
        $level = $this->referenceId('reference_job_levels', 'junior');

        $id = $this->repository()->create($this->companyRowId(), 'Junior developer', $level, $this->userRowId(), [
            new JobQuestionDraft($choice, 1, true, true, IdealAnswer::from(QuestionType::MultipleChoice, ['option_ids' => [$php, $go]], [$php, $go])),
            new JobQuestionDraft($yesNo, 2, false, false, null),
        ], now()->toDateTimeImmutable());

        $preset = $this->repository()->find($id);

        $this->assertNotNull($preset);
        $this->assertSame('Junior developer', $preset->name);
        $this->assertSame($level, $preset->jobLevelId);
        $this->assertSame([$choice, $yesNo], array_map(static fn ($item): int => $item->question->id, $preset->questions));
        $this->assertTrue($preset->questions[0]->isMustHave);
        $this->assertSame([$php, $go], $preset->questions[0]->ideal?->optionIds);
        $this->assertNull($preset->questions[1]->ideal);
    }

    public function test_an_update_replaces_the_name_the_level_and_the_whole_list(): void
    {
        $first = $this->question();
        $second = $this->question();
        $id = $this->repository()->create($this->companyRowId(), 'Old', $this->referenceId('reference_job_levels', 'junior'), $this->userRowId(), [
            new JobQuestionDraft($first, 1, true, false, null),
        ], now()->toDateTimeImmutable());

        $this->repository()->update($id, 'New', null, [new JobQuestionDraft($second, 1, false, false, null)], now()->toDateTimeImmutable());

        $preset = $this->repository()->find($id);

        $this->assertNotNull($preset);
        $this->assertSame('New', $preset->name);
        $this->assertNull($preset->jobLevelId);
        $this->assertSame([$second], array_map(static fn ($item): int => $item->question->id, $preset->questions));
    }

    public function test_a_name_is_unique_per_company_ignoring_letter_case(): void
    {
        $companyId = $this->companyRowId();
        $id = $this->repository()->create($companyId, 'Senior', null, $this->userRowId(), [], now()->toDateTimeImmutable());

        $this->assertTrue($this->repository()->nameTaken($companyId, 'SENIOR'));
        $this->assertFalse($this->repository()->nameTaken($companyId, 'SENIOR', $id));
        $this->assertFalse($this->repository()->nameTaken($this->companyRowId('pt-lain'), 'Senior'));

        $this->expectException(QueryException::class);
        $this->repository()->create($companyId, 'senior', null, $this->userRowId(), [], now()->toDateTimeImmutable());
    }

    public function test_deleting_a_preset_removes_its_rows_but_keeps_the_library(): void
    {
        $choice = $this->question('single_choice');
        $option = $this->option($choice, 'PHP');
        $id = $this->repository()->create($this->companyRowId(), 'Backend', null, $this->userRowId(), [
            new JobQuestionDraft($choice, 1, true, true, IdealAnswer::from(QuestionType::SingleChoice, ['option_ids' => [$option]], [$option])),
        ], now()->toDateTimeImmutable());

        $this->repository()->delete($id);

        $this->assertNull($this->repository()->find($id));
        $this->assertSame(0, DB::table('recruitment_screening_preset_questions')->count());
        $this->assertSame(0, DB::table('recruitment_screening_preset_ideal_options')->count());
        $this->assertSame(1, DB::table('recruitment_screening_questions')->where('id', $choice)->count());
    }

    public function test_a_library_question_or_choice_used_by_a_preset_cannot_be_deleted_underneath_it(): void
    {
        $choice = $this->question('single_choice');
        $option = $this->option($choice, 'PHP');
        $this->repository()->create($this->companyRowId(), 'Backend', null, $this->userRowId(), [
            new JobQuestionDraft($choice, 1, true, true, IdealAnswer::from(QuestionType::SingleChoice, ['option_ids' => [$option]], [$option])),
        ], now()->toDateTimeImmutable());

        $this->expectException(QueryException::class);
        DB::table('recruitment_screening_question_options')->where('id', $option)->delete();
    }
}
