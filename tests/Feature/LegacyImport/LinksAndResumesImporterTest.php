<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\JobSeeker\Application\JobSeekerSettings;
use Src\LegacyImport\Application\ImportReport;
use Src\LegacyImport\Application\ImportRunner;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\Concerns\UsesLegacyDatabase;
use Tests\FeatureTestCase;

final class LinksAndResumesImporterTest extends FeatureTestCase
{
    use UsesLegacyDatabase;

    private int $user;

    private int $candidate;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 09:00:00');
        Storage::fake();
        $this->setUpLegacyDatabase();

        $this->root = sys_get_temp_dir().'/legacy-resumes-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/storage/app/resumes', 0777, true);
        config(['legacy_import.files_root' => $this->root]);

        $this->user = $this->legacyUser(['email' => 'siti@example.test']);
        $this->candidate = $this->legacyCandidateId($this->user);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function import(string $entity, bool $write = true): ImportReport
    {
        $runner = $this->app->make(ImportRunner::class);

        foreach (['masters', 'accounts', 'profiles'] as $parent) {
            $runner->run($parent, true);
        }

        return $runner->run($entity, $write)['report'];
    }

    /** @return array<string, mixed> */
    private function entity(ImportReport $report, string $name): array
    {
        return $report->toArray(true)['entities'][$name];
    }

    private function pdf(string $extra = ''): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n{$extra}\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    private function resume(string $name, ?string $contents, ?int $candidate = null, string $created = '2024-03-01 08:00:00'): int
    {
        $path = 'resumes/'.bin2hex(random_bytes(4)).'.pdf';

        if ($contents !== null) {
            file_put_contents($this->root.'/storage/app/'.$path, $contents);
        }

        return $this->legacyRow('candidate_resumes', ['candidate_id' => $candidate ?? $this->candidate, 'name' => $name, 'file' => $path, 'created_at' => $created, 'updated_at' => $created]);
    }

    // ----- links -----

    public function test_the_website_and_the_social_links_are_mapped_to_the_new_kinds(): void
    {
        DB::connection('legacy')->table('candidates')->where('id', $this->candidate)->update(['website' => 'https://siti.example.test']);
        $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => 'linkedin', 'url' => 'https://www.linkedin.com/in/siti']);
        $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => 'twitter', 'url' => 'https://twitter.com/siti']);
        $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => 'youtube', 'url' => 'https://youtube.com/@siti']);
        $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => 'github', 'url' => 'https://github.com/siti']);

        $report = $this->import('links');

        $this->assertSame(
            [['website', 'https://siti.example.test'], ['linkedin', 'https://www.linkedin.com/in/siti'], ['x', 'https://twitter.com/siti'], ['other', 'https://youtube.com/@siti'], ['github', 'https://github.com/siti']],
            DB::table('jobseeker_links')->orderBy('id')->get(['type', 'url'])->map(fn ($r): array => [$r->type, $r->url])->all(),
        );
        $this->assertSame(5, $this->entity($report, 'links')['imported']);
    }

    public function test_links_that_break_the_new_rules_are_skipped_with_their_reason(): void
    {
        $http = $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => 'github', 'url' => 'http://github.com/siti']);
        $wrongDomain = $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => 'linkedin', 'url' => 'https://evil.example.test/in/siti']);
        $shortener = $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => 'other', 'url' => 'https://bit.ly/abc']);
        $unknown = $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => 'myspace', 'url' => 'https://myspace.com/siti']);
        $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => 'github', 'url' => 'https://github.com/siti']);
        $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => 'github', 'url' => 'https://github.com/siti2']);

        $report = $this->entity($this->import('links'), 'links');

        $this->assertSame(
            [$http => 'link-invalid', $wrongDomain => 'link-invalid', $shortener => 'link-invalid', $unknown => 'link-type-unknown'],
            collect($report['skipped_rows'])->pluck('reason', 'legacy_id')->all(),
        );
        $this->assertSame(2, DB::table('jobseeker_links')->count());
    }

    public function test_every_link_is_kept_without_a_limit_per_kind_or_in_all(): void
    {
        foreach (['facebook', 'youtube', 'pinterest', 'reddit'] as $kind) {
            $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => $kind, 'url' => "https://{$kind}.com/siti"]);
        }

        $this->import('links');

        $this->assertSame(4, DB::table('jobseeker_links')->where('type', 'other')->count());
    }

    public function test_links_of_a_company_owner_are_not_a_job_seekers_links(): void
    {
        $owner = $this->legacyUser(['role' => 'company', 'email' => 'owner@example.test']);
        $link = $this->legacyRow('social_links', ['user_id' => $owner, 'social_media' => 'github', 'url' => 'https://github.com/company']);

        $report = $this->entity($this->import('links'), 'links');

        $this->assertSame([['legacy_id' => $link, 'reason' => 'not-a-job-seeker']], $report['skipped_rows']);
    }

    public function test_running_the_links_again_adds_nothing(): void
    {
        $this->legacyRow('social_links', ['user_id' => $this->user, 'social_media' => 'github', 'url' => 'https://github.com/siti']);
        $this->import('links');

        $report = $this->entity($this->import('links'), 'links');

        $this->assertSame(1, DB::table('jobseeker_links')->count());
        $this->assertSame(1, $report['notes']['already-imported']);
    }

    // ----- resumes -----

    public function test_a_pdf_is_copied_under_a_random_name_and_remembered(): void
    {
        $id = $this->resume('  My CV.pdf ', $this->pdf(), created: '2024-02-01 07:00:00');

        $report = $this->import('resumes');

        $row = DB::table('jobseeker_resumes')->first();
        $this->assertMatchesRegularExpression('#^jobseeker/resumes/[0-9a-f]{32}\.pdf$#', $row->path);
        $this->assertSame('My CV.pdf', $row->name);
        $this->assertSame(strlen($this->pdf()), (int) $row->size_bytes);
        $this->assertSame('2024-02-01 07:00:00', $row->created_at);
        $this->assertSame($this->pdf(), Storage::get($row->path));
        $this->assertSame((int) $row->id, (int) DB::table('legacyimport_id_map')->where('entity', 'resume')->where('legacy_id', $id)->value('new_id'));
        $this->assertSame(1, $this->entity($report, 'resumes')['imported']);
    }

    public function test_a_file_that_fails_the_new_rules_is_skipped_with_its_reason(): void
    {
        $word = $this->resume('cv.docx', 'PK this is a docx');
        $encrypted = $this->resume('enc.pdf', $this->pdf('/Encrypt 5 0 R'));
        $script = $this->resume('js.pdf', $this->pdf('/JavaScript (app.alert(1))'));
        $missing = $this->resume('gone.pdf', null);
        $large = $this->resume('big.pdf', $this->pdf(str_repeat('x', 600 * 1024)));
        $this->app->make(SettingsStore::class)->setGlobal(JobSeekerSettings::RESUME_MAX_SIZE_KB, 512, null);

        $report = $this->entity($this->import('resumes'), 'resumes');

        $this->assertEquals(
            [$word => 'not-a-pdf', $encrypted => 'unsafe-pdf', $script => 'unsafe-pdf', $missing => 'file-missing', $large => 'too-large'],
            collect($report['skipped_rows'])->pluck('reason', 'legacy_id')->all(),
        );
        $this->assertSame(0, DB::table('jobseeker_resumes')->count());
        $this->assertSame([], Storage::allFiles('jobseeker'));
    }

    public function test_a_path_that_leaves_the_legacy_folder_is_refused(): void
    {
        $id = $this->legacyRow('candidate_resumes', ['candidate_id' => $this->candidate, 'name' => 'x.pdf', 'file' => '../../../etc/passwd', 'created_at' => '2024-03-01 08:00:00']);

        $report = $this->entity($this->import('resumes'), 'resumes');

        $this->assertSame([['legacy_id' => $id, 'reason' => 'file-missing']], $report['skipped_rows']);
    }

    public function test_every_valid_resume_is_kept_without_a_limit(): void
    {
        $ids = [];

        for ($i = 1; $i <= 7; $i++) {
            $ids[$i] = $this->resume("cv{$i}.pdf", $this->pdf((string) $i), created: sprintf('2024-03-0%d 08:00:00', $i));
        }

        // The newest one is broken; the six others pass.
        file_put_contents($this->root.'/storage/app/'.DB::connection('legacy')->table('candidate_resumes')->where('id', $ids[7])->value('file'), 'not a pdf');

        $report = $this->entity($this->import('resumes'), 'resumes');

        $this->assertSame(['cv1.pdf', 'cv2.pdf', 'cv3.pdf', 'cv4.pdf', 'cv5.pdf', 'cv6.pdf'], DB::table('jobseeker_resumes')->orderBy('name')->pluck('name')->all());
        $this->assertSame([$ids[7] => 'not-a-pdf'], collect($report['skipped_rows'])->pluck('reason', 'legacy_id')->all());
    }

    public function test_without_a_legacy_folder_every_resume_is_skipped(): void
    {
        $id = $this->resume('cv.pdf', $this->pdf());
        config(['legacy_import.files_root' => '']);

        $report = $this->entity($this->import('resumes'), 'resumes');

        $this->assertSame([['legacy_id' => $id, 'reason' => 'files-root-not-configured']], $report['skipped_rows']);
    }

    public function test_a_resume_of_an_account_that_was_not_imported_is_skipped(): void
    {
        $demo = $this->legacyCandidateId($this->legacyUser(['email' => 'demo@example.test', 'is_demo_field' => 1]));
        $id = $this->resume('cv.pdf', $this->pdf(), $demo);

        $report = $this->entity($this->import('resumes'), 'resumes');

        $this->assertSame([['legacy_id' => $id, 'reason' => 'user-not-imported']], $report['skipped_rows']);
    }

    public function test_running_it_again_copies_nothing_twice_and_a_dry_run_stores_nothing(): void
    {
        $this->resume('cv.pdf', $this->pdf());
        $this->import('resumes');
        $this->resume('cv2.pdf', $this->pdf('2'));

        $dry = $this->entity($this->import('resumes', false), 'resumes');
        $this->assertSame(1, $dry['imported']);
        $this->assertSame(1, DB::table('jobseeker_resumes')->count());
        $this->assertCount(1, Storage::allFiles('jobseeker/resumes'));

        $report = $this->entity($this->import('resumes'), 'resumes');

        $this->assertSame(2, DB::table('jobseeker_resumes')->count());
        $this->assertSame(1, $report['notes']['already-imported']);
    }

    // ----- the result seen through the API -----

    public function test_the_job_seeker_can_list_and_download_the_imported_resume(): void
    {
        $this->resume('My CV.pdf', $this->pdf());
        $this->import('resumes');

        $user = UserModel::query()->firstOrFail();
        $token = $user->createToken('device', ['*'], now()->addDay())->plainTextToken;

        $list = $this->withToken($token)->getJson('/api/v1/me/profile')->assertOk()->assertJsonCount(1, 'data.resumes')->assertJsonPath('data.resumes.0.name', 'My CV.pdf');
        $id = $list->json('data.resumes.0.id');

        $this->withToken($token)->get("/api/v1/me/resumes/{$id}/download")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
