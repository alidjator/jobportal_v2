<?php

namespace Tests\Feature\Assessment;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Src\Assessment\Application\Library\Figural\FiguralReasoningContent;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\FeatureTestCase;

/**
 * `docs/design/phase-8-figural-reasoning-test.md`: `assessment:install-figural-test` writes the 40 items and the sample into the system library and publishes the test, with a picture per option and one per
 * stem; only an admin who manages the library runs it, and only once. The disk is faked, so nothing is left behind.
 */
final class InstallFiguralReasoningTestTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
    }

    private function libraryAdmin(): int
    {
        $admin = AdminModel::query()->create(['name' => 'Library', 'email' => 'library@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo(['assessment_library.manage']);

        return (int) $admin->id;
    }

    public function test_it_publishes_the_test_with_its_sections_questions_and_pictures(): void
    {
        $this->artisan('assessment:install-figural-test', ['--admin' => $this->libraryAdmin()])->assertSuccessful();

        $test = DB::table('assessment_tests')->where('title', FiguralReasoningContent::TITLE)->sole();
        $this->assertSame('published', $test->status);
        $this->assertNull($test->company_id);
        $this->assertNull($test->provider_id);

        $sections = DB::table('assessment_sections')->where('test_id', $test->id)->orderBy('position')->pluck('id');
        $this->assertSame(['Gambar yang Berbeda', 'Deret Gambar', 'Analogi Gambar', 'Matriks Gambar'], DB::table('assessment_sections')->whereIn('id', $sections)->orderBy('position')->pluck('title')->all());

        $questions = DB::table('assessment_questions')->whereIn('section_id', $sections);
        $this->assertSame(41, (clone $questions)->count());
        $this->assertSame(1, (clone $questions)->where('is_sample', true)->count());

        $images = DB::table('assessment_question_images')->where('test_id', $test->id);
        $this->assertSame(41 * 5 + 30, (clone $images)->whereNotNull('question_id')->count());
        $this->assertSame(30, (clone $images)->whereNull('option_position')->count());
        $this->assertSame(0, (clone $images)->whereNull('question_id')->count());
    }

    public function test_it_runs_once_and_only_for_an_admin_who_manages_the_library(): void
    {
        $other = AdminModel::query()->create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'hash', 'status' => 'active']);

        $this->artisan('assessment:install-figural-test', ['--admin' => (int) $other->id])->assertFailed();
        $this->artisan('assessment:install-figural-test', ['--admin' => 999_999])->assertFailed();
        $this->assertSame(0, DB::table('assessment_tests')->where('title', FiguralReasoningContent::TITLE)->count());

        $admin = $this->libraryAdmin();
        $this->artisan('assessment:install-figural-test', ['--admin' => $admin])->assertSuccessful();
        $this->artisan('assessment:install-figural-test', ['--admin' => $admin])
            ->expectsOutputToContain('already in the library')
            ->assertFailed();
        $this->assertSame(1, DB::table('assessment_tests')->where('title', FiguralReasoningContent::TITLE)->count());
    }
}
