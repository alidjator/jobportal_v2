<?php

namespace Tests\Feature\JobSeeker;

use Illuminate\Support\Facades\DB;
use Src\JobSeeker\Application\BlockCompany;
use Src\JobSeeker\Application\Export\ProfileDataExporter;
use Src\JobSeeker\Domain\JobSeekerDataEraser;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-jobseeker-privacy.md` section 3: a job seeker blocks companies from finding them (the marketplace only). */
final class BlockedCompaniesTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    /** @var array{id: int, slug: string, token: string} */
    private array $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->ana = $this->candidate('ana@example.test', 'Ana');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @return array{id: int, slug: string, token: string} a findable job seeker with a complete profile */
    private function candidate(string $email, string $name): array
    {
        $id = $this->seeker($email, name: $name);
        $token = $this->tokenFor($id);
        $this->completeProfile($token);
        DB::table('jobseeker_profiles')->where('user_id', $id)->update(['is_discoverable' => true]);

        return ['id' => $id, 'slug' => (string) DB::table('jobseeker_profiles')->where('user_id', $id)->value('slug'), 'token' => $token];
    }

    private function block(int $companyId, ?string $token = null): void
    {
        $this->api('PUT', "/me/blocked-companies/{$companyId}", token: $token ?? $this->ana['token'])->assertNoContent();
    }

    /** @return list<string> */
    private function found(?string $token = null): array
    {
        return $this->api('GET', '/company/candidates?per_page=100', token: $token ?? $this->recruiterToken)->assertOk()->json('data.*.slug');
    }

    /** A copy of the first company, so a job seeker has more companies to block. */
    private function anotherCompany(string $slug): int
    {
        $row = (array) DB::table('company_companies')->where('id', $this->companyId)->first();
        unset($row['id']);

        return (int) DB::table('company_companies')->insertGetId([...$row, 'slug' => $slug, 'name' => strtoupper($slug)]);
    }

    // ----- the list -----

    public function test_only_a_job_seeker_manages_the_list(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/me/blocked-companies')->assertUnauthorized();

        $this->api('GET', '/me/blocked-companies', token: $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
        $this->api('PUT', "/me/blocked-companies/{$this->companyId}", token: $this->adminToken)->assertForbidden();
        $this->api('DELETE', "/me/blocked-companies/{$this->companyId}", token: $this->adminToken)->assertForbidden();
    }

    public function test_a_company_is_blocked_listed_and_unblocked(): void
    {
        $this->api('GET', '/me/blocked-companies', token: $this->ana['token'])->assertOk()->assertJsonCount(0, 'data');

        $this->block($this->companyId);
        $this->block($this->companyId);
        $this->block($this->otherCompanyId);

        $response = $this->api('GET', '/me/blocked-companies', token: $this->ana['token'])->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([$this->otherCompanyId, $this->companyId], $response->json('data.*.company.id'));
        $this->assertSame(['id', 'name', 'slug'], array_keys($response->json('data.0.company')));
        $this->assertSame(1, DB::table('jobseeker_blocked_companies')->where('company_id', $this->companyId)->count());

        $this->api('DELETE', "/me/blocked-companies/{$this->otherCompanyId}", token: $this->ana['token'])->assertNoContent();
        $this->api('DELETE', "/me/blocked-companies/{$this->otherCompanyId}", token: $this->ana['token'])->assertNoContent();
        $this->assertSame([$this->companyId], $this->api('GET', '/me/blocked-companies', token: $this->ana['token'])->json('data.*.company.id'));
    }

    public function test_a_company_that_is_missing_suspended_or_hidden_cannot_be_blocked(): void
    {
        $this->api('PUT', '/me/blocked-companies/999999', token: $this->ana['token'])->assertNotFound();

        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);
        $this->api('PUT', "/me/blocked-companies/{$this->companyId}", token: $this->ana['token'])->assertNotFound();

        DB::table('company_companies')->where('id', $this->otherCompanyId)->update(['is_visible' => false]);
        $this->api('PUT', "/me/blocked-companies/{$this->otherCompanyId}", token: $this->ana['token'])->assertNotFound();

        $this->assertSame(0, DB::table('jobseeker_blocked_companies')->count());
    }

    public function test_a_job_seeker_blocks_at_most_twenty_companies(): void
    {
        foreach (range(1, BlockCompany::LIMIT) as $number) {
            $this->block($this->anotherCompany("blocked-{$number}"));
        }

        $this->api('PUT', "/me/blocked-companies/{$this->companyId}", token: $this->ana['token'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:blocked-companies-limit');
        $this->block(DB::table('jobseeker_blocked_companies')->orderBy('id')->value('company_id'));
        $this->assertSame(BlockCompany::LIMIT, DB::table('jobseeker_blocked_companies')->count());

        $this->api('DELETE', '/me/blocked-companies/'.DB::table('jobseeker_blocked_companies')->orderBy('id')->value('company_id'), token: $this->ana['token'])->assertNoContent();
        $this->block($this->companyId);
    }

    // ----- the effects, only for the blocked company -----

    public function test_a_blocked_company_no_longer_finds_the_person_and_another_company_still_does(): void
    {
        $this->assertSame([$this->ana['slug']], $this->found());

        $this->block($this->companyId);

        $this->assertSame([], $this->found());
        $this->api('GET', "/company/candidates/{$this->ana['slug']}", token: $this->recruiterToken)->assertNotFound();
        $this->api('GET', "/company/candidates/{$this->ana['slug']}/avatar", token: $this->recruiterToken)->assertNotFound();
        $this->assertSame([$this->ana['slug']], $this->found($this->otherAdminToken));
        $this->api('GET', "/company/candidates/{$this->ana['slug']}", token: $this->otherAdminToken)->assertOk();

        $this->api('DELETE', "/me/blocked-companies/{$this->companyId}", token: $this->ana['token'])->assertNoContent();

        $this->assertSame([$this->ana['slug']], $this->found());
    }

    public function test_a_blocked_company_cannot_invite_or_start_a_chat_by_the_slug_of_the_person(): void
    {
        $job = $this->liveJob();
        $this->block($this->companyId);

        $this->api('POST', "/company/jobs/{$job}/invitations", ['candidate' => $this->ana['slug']], $this->adminToken)->assertNotFound();
        $this->api('POST', '/company/conversations', ['candidate' => $this->ana['slug'], 'body' => 'Hello'], $this->recruiterToken)->assertNotFound();
        $this->api('POST', '/company/talent-pool', ['candidate' => $this->ana['slug']], $this->recruiterToken)->assertNotFound();
        $this->assertSame(0, DB::table('jobinvitation_invitations')->count() + DB::table('messaging_conversations')->count() + DB::table('talentpool_entries')->count());

        $this->api('DELETE', "/me/blocked-companies/{$this->companyId}", token: $this->ana['token'])->assertNoContent();

        $this->api('POST', '/company/conversations', ['candidate' => $this->ana['slug'], 'body' => 'Hello'], $this->recruiterToken)->assertSuccessful();
        $this->api('POST', '/company/talent-pool', ['candidate' => $this->ana['slug']], $this->recruiterToken)->assertSuccessful();
        $this->api('POST', "/company/jobs/{$job}/invitations", ['candidate' => $this->ana['slug']], $this->adminToken)->assertSuccessful();
    }

    public function test_a_talent_pool_entry_stays_but_is_not_available_to_the_company_that_was_blocked(): void
    {
        $entry = (int) $this->api('POST', '/company/talent-pool', ['candidate' => $this->ana['slug']], $this->recruiterToken)->assertSuccessful()->json('data.id');
        $this->api('GET', "/company/talent-pool/{$entry}", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.available', true);

        $this->block($this->companyId);

        $this->api('GET', "/company/talent-pool/{$entry}", token: $this->recruiterToken)->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.candidate', null);
        $this->assertSame(1, DB::table('talentpool_entries')->count());

        $job = $this->liveJob();
        $this->api('POST', "/company/jobs/{$job}/invitations", ['talent_pool_entry_id' => $entry], $this->adminToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:entry-unavailable');
    }

    // ----- the marketplace only -----

    public function test_blocking_exists_on_the_marketplace_only_and_a_stored_block_has_no_effect_elsewhere(): void
    {
        $this->block($this->companyId);
        $this->assertSame([], $this->found());

        DB::table('platform_portal_identity')->insert(['mode' => 'company', 'scope' => 'company', 'company_ids' => json_encode([$this->companyId]), 'updated_at' => now()]);

        $this->api('GET', '/me/blocked-companies', token: $this->ana['token'])->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:company-blocking-unavailable');
        $this->api('PUT', "/me/blocked-companies/{$this->otherCompanyId}", token: $this->ana['token'])->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:company-blocking-unavailable');
        $this->api('DELETE', "/me/blocked-companies/{$this->companyId}", token: $this->ana['token'])->assertNotFound()->assertJsonPath('type', 'urn:onesis:error:company-blocking-unavailable');
        $this->assertSame([$this->ana['slug']], $this->found());

        DB::table('platform_portal_identity')->delete();

        $this->assertSame([], $this->found());
        $this->api('GET', '/me/blocked-companies', token: $this->ana['token'])->assertOk()->assertJsonCount(1, 'data');
    }

    // ----- privacy -----

    public function test_the_personal_data_export_lists_the_blocked_companies_and_erasure_deletes_them(): void
    {
        $this->block($this->companyId);

        $blocked = app(ProfileDataExporter::class)->export($this->ana['id'])->data['blocked_companies'];

        $this->assertCount(1, $blocked);
        $this->assertSame(DB::table('company_companies')->where('id', $this->companyId)->value('name'), $blocked[0]['company']);

        app(JobSeekerDataEraser::class)->deleteRows($this->ana['id']);

        $this->assertSame(0, DB::table('jobseeker_blocked_companies')->count());
    }
}
