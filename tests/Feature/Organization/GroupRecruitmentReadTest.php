<?php

namespace Tests\Feature\Organization;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Shared\Application\Recruitment\DefaultStageProvisioner;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/erp-organization.md` sections 4 and 5: the read rule `group.recruitment.view` in the recruitment contexts. */
final class GroupRecruitmentReadTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $group;

    private int $sister;

    private int $sisterJob;

    private int $sisterApplication;

    private int $ownApplication;

    private ?string $operatorToken = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        Carbon::setTestNow('2026-10-05 09:00:00');

        // The fixture company is the holding's tenant; a second tenant of the group has a job and an applicant of its own.
        $this->sister = (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Sister', 'slug' => 'pt-sister', 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->app->make(DefaultStageProvisioner::class)->provision($this->sister);
        $this->sisterJob = $this->insertJob('Finance Analyst', 'active', [], $this->sister, ['deadline' => '2026-12-31']);
        $this->sisterApplication = (int) $this->apply($this->sisterJob)->assertCreated()->json('data.id');
        $this->ownApplication = (int) $this->apply($this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']))->assertCreated()->json('data.id');

        $created = $this->operator('POST', 'groups', ['name' => 'Grup Nusantara', 'legal_entity' => ['legal_name' => 'PT Holding', 'legal_form' => 'pt']])->assertCreated();
        $this->group = (int) $created->json('data.id');
        $holdingEntity = (int) $created->json('data.legal_entities.0.id');
        $this->operator('PUT', "groups/{$this->group}/legal-entities/{$holdingEntity}/tenant", ['company_id' => $this->companyId])->assertOk();
        // Linking the tenant ended every session of its members (`erp-sso.md` section 5): they sign in again.
        $this->adminToken = $this->tokenFor($this->adminId);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        $this->operator('PUT', "groups/{$this->group}/members/{$this->adminId}", ['permissions' => ['group.structure.manage', 'group.recruitment.view', 'group.audit.view']])->assertOk();
        $sisterEntity = (int) $this->api('POST', '/organization/legal-entities', ['legal_name' => 'PT Sister', 'legal_form' => 'pt'])->assertCreated()->json('data.id');
        $this->operator('PUT', "groups/{$this->group}/legal-entities/{$sisterEntity}/tenant", ['company_id' => $this->sister])->assertOk();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $body */
    private function operator(string $method, string $uri, array $body = []): TestResponse
    {
        if ($this->operatorToken === null) {
            $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
            Artisan::call('identity:sync-permissions');
            $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
            $admin->givePermissionTo(['companies.manage']);
            $this->operatorToken = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
        }

        $this->app['auth']->forgetGuards();

        return $this->withToken($this->operatorToken)->json($method, "/api/v1/admin/{$uri}", $body);
    }

    private function read(string $path, ?int $tenant = null, ?string $token = null): TestResponse
    {
        $tenant ??= $this->sister;

        return $this->api('GET', "/organization/read/{$path}".(str_contains($path, '?') ? '&' : '?')."tenant_id={$tenant}", token: $token ?? $this->adminToken);
    }

    public function test_the_group_lists_its_internal_tenants(): void
    {
        $response = $this->api('GET', '/organization/tenants')->assertOk();

        $this->assertSame([$this->companyId, $this->sister], array_column($response->json('data'), 'company_id'));
        $response->assertJsonPath('data.1.name', 'PT Sister')->assertJsonPath('data.1.legal_name', 'PT Sister')->assertJsonPath('data.1.status', 'active');

        $this->api('GET', '/organization/tenants', token: $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-group-member');
    }

    public function test_a_group_member_reads_the_jobs_and_reports_of_another_tenant(): void
    {
        $this->read('jobs')->assertOk()->assertJsonPath('data.0.title', 'Finance Analyst');
        $this->read("jobs/{$this->sisterJob}")->assertOk()->assertJsonPath('data.title', 'Finance Analyst');
        $this->read("jobs/{$this->sisterJob}/performance")->assertOk();
        $this->read('analytics/funnel?from=2026-10-01&to=2026-10-04')->assertOk();

        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'group.recruitment_read')->count(), 'jobs and reports are not applicant reads');
    }

    public function test_applicants_are_read_without_marking_them_viewed_and_every_read_is_audited(): void
    {
        $this->read("jobs/{$this->sisterJob}/applications")->assertOk()->assertJsonCount(1, 'data');
        $detail = $this->read("applications/{$this->sisterApplication}")->assertOk();
        $detail->assertJsonPath('data.applicant.name', 'Siti Rahma')->assertJsonPath('data.applicant.contact_hidden', false);
        $this->assertNull(DB::table('recruitment_applications')->where('id', $this->sisterApplication)->value('viewed_at'), 'the applicant is not told someone looked');
        $this->read("applications/{$this->sisterApplication}/resume")->assertOk();

        $entries = DB::table('platform_audit_logs')->where('action', 'group.recruitment_read')->orderBy('id')->get();
        $this->assertCount(3, $entries);
        $this->assertSame($this->adminId, (int) $entries[1]->actor_id);
        $this->assertSame($this->group, (int) $entries[1]->group_id);
        $this->assertSame($this->sister, (int) $entries[1]->company_id);
        $this->assertSame("GET /api/v1/organization/read/applications/{$this->sisterApplication}", json_decode((string) $entries[1]->metadata, true)['request']);

        $log = $this->api('GET', '/organization/activity?filter[action]=group.recruitment_read')->assertOk();
        $this->assertCount(3, $log->json('data'));
        $this->assertSame('PT Sister', $log->json('data.0.company_name'));
    }

    public function test_the_tenant_must_belong_to_the_group_and_the_member_must_hold_the_permission(): void
    {
        $this->read('jobs', $this->otherCompanyId)->assertNotFound();
        $this->api('GET', "/organization/read/jobs/{$this->sisterJob}")->assertStatus(422)->assertJsonValidationErrors(['tenant_id']);

        $this->read('jobs', token: $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-group-member');

        $this->operator('PUT', "groups/{$this->group}/members/{$this->adminId}", ['permissions' => ['group.structure.manage']])->assertOk();
        $this->read('jobs')->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:group-permission-required');
        $this->api('GET', '/organization/tenants')->assertForbidden();
    }

    public function test_a_suspended_group_cannot_be_read_through_its_roles(): void
    {
        $this->operator('POST', "groups/{$this->group}/suspend", ['reason' => 'Contract ended'])->assertOk();

        $this->read('jobs')->assertForbidden();
    }

    public function test_nothing_changes_through_the_read_routes_and_the_recruiters_own_routes_stay_closed(): void
    {
        $this->api('POST', "/organization/read/applications/{$this->sisterApplication}/move?tenant_id={$this->sister}", ['stage_id' => 1])->assertNotFound();
        $this->api('PATCH', "/organization/read/jobs/{$this->sisterJob}?tenant_id={$this->sister}", ['title' => 'Hacked'])->assertStatus(405);

        $this->api('GET', "/company/applications/{$this->sisterApplication}")->assertNotFound();
        $this->api('POST', "/company/applications/{$this->sisterApplication}/reject", ['reason' => 'qualifications_mismatch'])->assertNotFound();
        $this->assertSame('Finance Analyst', DB::table('job_jobs')->where('id', $this->sisterJob)->value('title'));
    }

    public function test_the_group_reads_its_own_holding_tenant_the_same_way(): void
    {
        $this->read("applications/{$this->ownApplication}", $this->companyId)->assertOk();
        $this->assertNotNull(DB::table('recruitment_applications')->where('id', $this->ownApplication)->value('id'));
    }
}
