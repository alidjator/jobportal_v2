<?php

namespace Tests\Feature\DemoData;

use Database\Seeders\Company\CompanyDocumentTypeSeeder;
use Database\Seeders\Reference\ReferenceDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Src\DemoData\Application\DemoCast;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class SeedDemoDataTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    /** The review of jobs of unverified companies is on, as under the default settings (a tenant of a group counts as verified, so it is not asked). */
    protected bool $unverifiedReview = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
        Mail::fake();
        Artisan::call('identity:sync-permissions');
        $this->seed([ReferenceDataSeeder::class, CompanyDocumentTypeSeeder::class]);
        $this->locationChain();
    }

    /** Runs the command and returns the password it printed. */
    private function seedDemo(): string
    {
        $exit = Artisan::call('demo:seed');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertSame(1, preg_match('/Password of every demo account: (\S+)/', $output, $match), $output);

        return $match[1];
    }

    private function signIn(string $email, string $password): string
    {
        $this->app['auth']->forgetGuards();

        return (string) $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password])->assertOk()->json('data.token');
    }

    public function test_it_builds_the_group_with_two_companies_and_one_account_per_preset(): void
    {
        $password = $this->seedDemo();

        $sinar = DB::table('company_companies')->where('name', DemoCast::COMPANIES[DemoCast::SINAR]['name'])->first();
        $arunika = DB::table('company_companies')->where('name', DemoCast::COMPANIES[DemoCast::ARUNIKA]['name'])->first();
        $this->assertNotNull($sinar->verified_at);
        $this->assertNull($arunika->verified_at);
        $this->assertSame(1, DB::table('organization_groups')->where('name', DemoCast::GROUP_NAME)->count());
        $this->assertNotNull($sinar->legal_entity_id);
        $this->assertNotNull($arunika->legal_entity_id);
        $this->assertSame(
            DB::table('organization_legal_entities')->where('id', $sinar->legal_entity_id)->value('group_id'),
            DB::table('organization_legal_entities')->where('id', $arunika->legal_entity_id)->value('group_id'),
        );
        $this->assertSame(0, DB::table('company_documents')->where('company_id', $arunika->id)->whereNotNull('reviewed_at')->count());
        $this->assertGreaterThan(0, DB::table('company_documents')->where('company_id', $arunika->id)->count());

        // The group asks every member of its tenants for a second factor (a company admin always): each one enrols it at the first sign-in.
        foreach ([DemoCast::COMPANIES[DemoCast::SINAR]['admin_email'], ...array_column(DemoCast::sinarMembers(), 'email')] as $email) {
            $this->app['auth']->forgetGuards();
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password])->assertOk()->assertJsonPath('data.status', 'two_factor_enrolment_required');
        }

        $this->assertSame('company_admin', DB::table('company_members')->where('company_id', $sinar->id)->where('user_id', DB::table('identity_users')->where('email', DemoCast::COMPANIES[DemoCast::SINAR]['admin_email'])->value('id'))->value('role'));
        $this->assertSame(1, DB::table('organization_group_members')->where('user_id', DB::table('identity_users')->where('email', DemoCast::COMPANIES[DemoCast::SINAR]['admin_email'])->value('id'))->count());

        foreach (DemoCast::sinarMembers() as $member) {
            $row = DB::table('company_members')->where('company_id', $sinar->id)->where('user_id', DB::table('identity_users')->where('email', $member['email'])->value('id'))->first();
            $held = DB::table('company_member_permissions')->where('company_member_id', $row->id)->pluck('permission')->all();
            $expected = $member['preset']->permissionNames();
            sort($held);
            sort($expected);

            $this->assertSame('member', $row->role, $member['email']);
            $this->assertSame($expected, $held, $member['email']);
        }

        $this->assertSame(1, DB::table('identity_admins')->where('email', DemoCast::ADMIN_EMAIL)->count());
        $this->postJson('/api/v1/admin/auth/login', ['email' => DemoCast::ADMIN_EMAIL, 'password' => $password])->assertOk();
    }

    public function test_it_leaves_jobs_and_applications_at_several_stages(): void
    {
        $password = $this->seedDemo();

        $statuses = DB::table('job_jobs')->pluck('status', 'title')->all();
        $this->assertSame([
            'Backend Developer' => 'active', 'Customer Support Officer' => 'closed', 'Data Analyst' => 'active', 'Merchandiser' => 'active',
            'Mobile Developer' => 'draft', 'Store Supervisor' => 'active', 'UI/UX Designer' => 'active',
        ], collect($statuses)->sortKeys()->all());

        $stages = DB::table('recruitment_applications')
            ->join('recruitment_stages', 'recruitment_stages.id', '=', 'recruitment_applications.stage_id')
            ->selectRaw('recruitment_stages.type, count(*) as total')->groupBy('recruitment_stages.type')->pluck('total', 'type')->map(fn ($total): int => (int) $total)->all();
        ksort($stages);
        $this->assertSame(['applied' => 5, 'hired' => 1, 'interview' => 2, 'offer' => 1, 'rejected' => 1, 'screening' => 3, 'withdrawn' => 1], $stages);
        $this->assertSame(2, DB::table('recruitment_interviews')->count());

        $recruiterId = (int) DB::table('identity_users')->where('email', DemoCast::sinarMembers()[0]['email'])->value('id');
        $hiringManagerId = (int) DB::table('identity_users')->where('email', DemoCast::sinarMembers()[1]['email'])->value('id');
        $backend = (int) DB::table('job_jobs')->where('title', 'Backend Developer')->value('id');
        $this->assertSame(
            [$recruiterId => 'recruiter', $hiringManagerId => 'hiring_manager'],
            DB::table('job_assignments')->where('job_id', $backend)->orderBy('user_id')->pluck('role', 'user_id')->all(),
        );

        $seeker = DemoCast::jobSeekers()[4];
        $applications = $this->withToken($this->signIn($seeker['email'], $password))->getJson('/api/v1/me/applications')->assertOk();
        $this->assertCount(2, $applications->json('data'));
        $this->assertNotNull(DB::table('identity_users')->where('email', $seeker['email'])->value('email_verified_at'));

        $userId = (int) DB::table('identity_users')->where('email', $seeker['email'])->value('id');
        $this->assertSame(3, DB::table('jobseeker_skills')->where('user_id', $userId)->count());
        $this->assertSame(2, DB::table('jobseeker_languages')->where('user_id', $userId)->count());
        $this->assertSame(2, DB::table('jobseeker_links')->where('user_id', $userId)->count());
    }

    public function test_it_leaves_no_session_open(): void
    {
        $this->seedDemo();

        $ids = DB::table('identity_users')->whereIn('email', DemoCast::userEmails())->pluck('id');
        $this->assertCount(count(DemoCast::userEmails()), $ids);
        $this->assertSame(0, DB::table('personal_access_tokens')->whereIn('tokenable_id', $ids)->count());
    }

    public function test_a_second_run_is_refused_and_changes_nothing(): void
    {
        $this->seedDemo();
        $users = DB::table('identity_users')->count();

        $this->artisan('demo:seed')->expectsOutputToContain('The demo data already exists')->assertExitCode(1);

        $this->assertSame($users, DB::table('identity_users')->count());
        $this->assertSame(1, DB::table('identity_admins')->where('email', DemoCast::ADMIN_EMAIL)->count());
    }

    public function test_it_is_refused_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('demo:seed')->expectsOutputToContain('refused in production')->assertExitCode(1);

        $this->assertSame(0, DB::table('identity_admins')->where('email', DemoCast::ADMIN_EMAIL)->count());
        $this->assertSame(0, DB::table('identity_users')->whereIn('email', DemoCast::userEmails())->count());
    }
}
