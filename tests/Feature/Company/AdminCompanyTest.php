<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class AdminCompanyTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $this->token = $this->adminToken(['companies.manage']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  list<string>  $permissions */
    private function seededAdminId(): int
    {
        return (int) (AdminModel::query()->where('email', 'seeded-admin@example.test')->value('id')
            ?? AdminModel::query()->create(['name' => 'Seeded', 'email' => 'seeded-admin@example.test', 'password' => 'hash', 'status' => 'active'])->id);
    }

    private function adminToken(array $permissions, string $email = 'ops@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;
    }

    /** @param  array<string, mixed>  $attributes */
    private function company(string $name, string $slug, array $attributes = []): int
    {
        return (int) DB::table('company_companies')->insertGetId([
            'name' => $name, 'slug' => $slug, 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'created_at' => now(), 'updated_at' => now(), ...$attributes,
        ]);
    }

    private function member(int $companyId, string $email, string $role = 'recruiter', string $status = 'active'): UserModel
    {
        $user = UserModel::query()->create(['type' => 'company_member', 'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => $status, 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00']);
        DB::table('company_members')->insert(['company_id' => $companyId, 'user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/admin/companies'.$uri, $body);
    }

    private function userToken(UserModel $user): string
    {
        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    // ----- access -----

    public function test_every_admin_company_route_needs_an_admin_with_the_permission(): void
    {
        $weak = $this->adminToken(['faqs.manage'], 'weak@example.test');
        $id = $this->company('PT Maju', 'pt-maju');

        foreach ([['GET', ''], ['GET', "/{$id}"], ['POST', "/{$id}/suspend"], ['POST', "/{$id}/restore"]] as [$method, $uri]) {
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
            $this->json($method, '/api/v1/admin/companies'.$uri)->assertStatus(401);
            $this->api($method, $uri, token: $weak)->assertStatus(403);
        }

        $member = $this->member($id, 'andi@example.test', 'company_admin');
        $this->api('GET', '', token: $this->userToken($member))->assertStatus(401);
    }

    // ----- the list -----

    public function test_the_list_shows_every_company_newest_first_with_its_row_fields(): void
    {
        $old = $this->company('PT Lama', 'pt-lama', ['created_at' => '2026-01-01 10:00:00']);
        $new = $this->company('PT Baru', 'pt-baru', ['created_at' => '2026-09-01 10:00:00', 'verified_at' => '2026-09-02 10:00:00']);
        $this->member($new, 'andi@example.test', 'company_admin');
        $this->member($new, 'rina@example.test');
        $this->member($old, 'budi@example.test', 'company_admin');
        $this->company('PT Tangguh', 'pt-tangguh', ['created_at' => '2026-05-01 10:00:00', 'status' => 'suspended']);

        $response = $this->api('GET', '')->assertOk();

        $this->assertSame(['pt-baru', 'pt-tangguh', 'pt-lama'], array_column($response->json('data'), 'slug'));
        $this->assertSame([
            'id' => $new, 'name' => 'PT Baru', 'slug' => 'pt-baru', 'status' => 'active', 'verified' => true, 'members_count' => 2,
            'created_at' => '2026-09-01T10:00:00+00:00',
        ], $response->json('data.0'));
        $this->assertSame(['suspended', false, 0], [$response->json('data.1.status'), $response->json('data.1.verified'), $response->json('data.1.members_count')]);
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_the_list_is_paginated_with_a_total(): void
    {
        foreach (range(1, 5) as $number) {
            $this->company("PT Nomor {$number}", "pt-nomor-{$number}", ['created_at' => "2026-09-0{$number} 10:00:00"]);
        }

        $first = $this->api('GET', '?per_page=2')->assertOk();
        $this->assertSame(['pt-nomor-5', 'pt-nomor-4'], array_column($first->json('data'), 'slug'));
        $this->assertSame(5, $first->json('meta.total'));
        $this->assertSame(3, $first->json('meta.last_page'));
        $this->assertNotNull($first->json('links.next'));

        $third = $this->api('GET', '?per_page=2&page=3')->assertOk();
        $this->assertSame(['pt-nomor-1'], array_column($third->json('data'), 'slug'));
        $this->assertNull($third->json('links.next'));

        $this->api('GET', '?per_page=2&page=9')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_invalid_paging_filters_and_sorts_are_refused(): void
    {
        $this->api('GET', '?page=0')->assertStatus(422);
        $this->api('GET', '?per_page=101')->assertStatus(422);
        $this->api('GET', '?per_page=0')->assertStatus(422);
        $this->api('GET', '?filter[status]=pending')->assertStatus(422);
        $this->api('GET', '?filter[verified]=maybe')->assertStatus(422);
        $this->api('GET', '?filter[industry]=1')->assertStatus(422);
        $this->api('GET', '?sort=members')->assertStatus(422);
        $this->api('GET', '?filter[q]='.str_repeat('a', 101))->assertStatus(422);
    }

    public function test_the_search_matches_name_or_slug_without_regard_to_case(): void
    {
        $this->company('PT Maju Bersama', 'maju-bersama');
        $this->company('CV Sentosa', 'sentosa-makmur');
        $this->company('Toko Abadi', 'abadi-jaya');

        $this->assertSame(['maju-bersama'], array_column($this->api('GET', '?filter[q]=MAJU')->json('data'), 'slug'));
        $this->assertSame(['sentosa-makmur'], array_column($this->api('GET', '?filter[q]=sentosa')->json('data'), 'slug'));
        $this->assertSame(['abadi-jaya'], array_column($this->api('GET', '?filter[q]=toko%20abadi')->json('data'), 'slug'));
        $this->assertSame(['sentosa-makmur'], array_column($this->api('GET', '?filter[q]=makmur')->json('data'), 'slug'));
        $this->assertSame([], $this->api('GET', '?filter[q]=tidak-ada')->json('data'));
    }

    public function test_search_characters_are_taken_literally(): void
    {
        $this->company('Diskon 100% Jaya', 'diskon-jaya');
        $this->company('Diskon 1000 Jaya', 'diskon-seribu');
        $this->company('Under_score Co', 'underscore-co');
        $this->company('Underxscore Co', 'underxscore-co');

        $this->assertSame(['diskon-jaya'], array_column($this->api('GET', '?filter[q]=100%25')->json('data'), 'slug'));
        $this->assertSame(['underscore-co'], array_column($this->api('GET', '?filter[q]=under_score')->json('data'), 'slug'));
        // A lone % is not a wildcard that matches everything: only the name that really contains one matches.
        $this->assertSame(['diskon-jaya'], array_column($this->api('GET', '?filter[q]=%25')->json('data'), 'slug'));
    }

    public function test_the_list_can_be_filtered_by_status_and_verification_and_combined(): void
    {
        $this->company('Aktif Terverifikasi', 'aktif-v', ['verified_at' => '2026-09-01 00:00:00']);
        $this->company('Aktif Biasa', 'aktif-b');
        $this->company('Tangguh Terverifikasi', 'tangguh-v', ['status' => 'suspended', 'verified_at' => '2026-09-01 00:00:00']);
        $this->company('Tangguh Biasa', 'tangguh-b', ['status' => 'suspended']);

        $slugs = fn (string $query): array => collect($this->api('GET', $query)->json('data'))->pluck('slug')->sort()->values()->all();

        $this->assertSame(['tangguh-b', 'tangguh-v'], $slugs('?filter[status]=suspended'));
        $this->assertSame(['aktif-b', 'aktif-v'], $slugs('?filter[status]=active'));
        $this->assertSame(['aktif-v', 'tangguh-v'], $slugs('?filter[verified]=true'));
        $this->assertSame(['aktif-b', 'tangguh-b'], $slugs('?filter[verified]=false'));
        $this->assertSame(['tangguh-v'], $slugs('?filter[status]=suspended&filter[verified]=1'));
        $this->assertSame(['aktif-v'], $slugs('?filter[status]=active&filter[verified]=true&filter[q]=terverifikasi'));
    }

    public function test_the_list_can_be_sorted(): void
    {
        $this->company('Bravo', 'bravo', ['created_at' => '2026-03-01 00:00:00']);
        $this->company('Charlie', 'charlie', ['created_at' => '2026-01-01 00:00:00']);
        $this->company('Alpha', 'alpha', ['created_at' => '2026-02-01 00:00:00']);

        $order = fn (string $sort): array => array_column($this->api('GET', "?sort={$sort}")->json('data'), 'slug');

        $this->assertSame(['alpha', 'bravo', 'charlie'], $order('name'));
        $this->assertSame(['charlie', 'bravo', 'alpha'], $order('-name'));
        $this->assertSame(['charlie', 'alpha', 'bravo'], $order('created_at'));
        $this->assertSame(['bravo', 'alpha', 'charlie'], $order('-created_at'));
    }

    public function test_companies_created_at_the_same_moment_keep_a_stable_order_across_pages(): void
    {
        foreach (range(1, 5) as $number) {
            $this->company("PT Kembar {$number}", "kembar-{$number}", ['created_at' => '2026-09-01 10:00:00']);
        }

        $seen = [];
        foreach ([1, 2, 3] as $page) {
            $seen = [...$seen, ...array_column($this->api('GET', "?per_page=2&page={$page}")->json('data'), 'slug')];
        }

        $this->assertCount(5, array_unique($seen));
    }

    // ----- the detail -----

    public function test_the_detail_holds_the_whole_profile_the_counts_and_no_suspension_while_active(): void
    {
        $id = $this->company('PT Maju Bersama', 'pt-maju-bersama', [
            'website' => 'https://maju.example', 'bio' => 'Bio', 'contact_email' => 'hr@maju.example', 'verified_at' => '2026-10-01 08:30:00',
            'logo_path' => 'company/logos/abc.jpg', 'created_by_admin_id' => $this->seededAdminId(), 'created_at' => '2026-09-01 10:00:00',
        ]);
        $this->member($id, 'andi@example.test', 'company_admin');
        $invitation = fn (string $email, string $hash, string $sent, string $expires, array $state = []) => DB::table('company_invitations')->insert([
            'company_id' => $id, 'email' => $email, 'role' => 'recruiter', 'token_hash' => str_repeat($hash, 64), 'sent_at' => $sent, 'expires_at' => $expires,
            'accepted_at' => null, 'revoked_at' => null, 'created_at' => now(), 'updated_at' => now(), ...$state,
        ]);
        $invitation('pending@example.test', 'a', '2026-10-05 08:00:00', '2026-10-12 08:00:00');
        $invitation('accepted@example.test', 'b', '2026-10-05 08:00:00', '2026-10-12 08:00:00', ['accepted_at' => '2026-10-05 08:30:00']);
        $invitation('revoked@example.test', 'c', '2026-10-05 08:00:00', '2026-10-12 08:00:00', ['revoked_at' => '2026-10-05 08:30:00']);
        $invitation('expired@example.test', 'd', '2026-09-01 08:00:00', '2026-09-08 08:00:00');

        $response = $this->api('GET', "/{$id}")->assertOk();

        $response->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.name', 'PT Maju Bersama')
            ->assertJsonPath('data.slug', 'pt-maju-bersama')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.website', 'https://maju.example')
            ->assertJsonPath('data.contact_email', 'hr@maju.example')
            ->assertJsonPath('data.verified_at', '2026-10-01T08:30:00+00:00')
            ->assertJsonPath('data.has_logo', true)
            ->assertJsonPath('data.has_banner', false)
            ->assertJsonPath('data.members_count', 1)
            ->assertJsonPath('data.pending_invitations_count', 1)
            ->assertJsonPath('data.created_by_admin_id', $this->seededAdminId())
            ->assertJsonPath('data.created_at', '2026-09-01T10:00:00+00:00')
            ->assertJsonPath('data.suspended_at', null)
            ->assertJsonPath('data.suspension_reason', null);
        $this->assertArrayNotHasKey('logo_url', $response->json('data'));
        $this->assertArrayNotHasKey('banner_url', $response->json('data'));
    }

    public function test_an_unknown_company_is_not_found(): void
    {
        $this->api('GET', '/999999')->assertNotFound();
        $this->api('GET', '/abc')->assertNotFound();
    }

    // ----- suspending -----

    public function test_suspending_stores_the_reason_and_shows_it_to_admins(): void
    {
        $id = $this->company('PT Maju', 'pt-maju');

        $this->api('POST', "/{$id}/suspend", ['reason' => 'Fake job postings reported by three applicants.'])->assertNoContent();

        $row = DB::table('company_companies')->where('id', $id)->first();
        $this->assertSame('suspended', $row->status);
        $this->assertSame('2026-10-05 09:00:00', $row->suspended_at);
        $this->assertSame('Fake job postings reported by three applicants.', $row->suspension_reason);

        $this->api('GET', "/{$id}")->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.suspended_at', '2026-10-05T09:00:00+00:00')
            ->assertJsonPath('data.suspension_reason', 'Fake job postings reported by three applicants.');
        $this->assertSame('suspended', $this->api('GET', '?filter[status]=suspended')->json('data.0.status'));
    }

    public function test_the_reason_needs_five_to_a_thousand_characters(): void
    {
        $id = $this->company('PT Maju', 'pt-maju');

        $this->api('POST', "/{$id}/suspend", [])->assertStatus(422)->assertJsonValidationErrors('reason', 'errors');
        $this->api('POST', "/{$id}/suspend", ['reason' => 'four'])->assertStatus(422);
        $this->api('POST', "/{$id}/suspend", ['reason' => '      '])->assertStatus(422);
        $this->api('POST', "/{$id}/suspend", ['reason' => str_repeat('a', 1001)])->assertStatus(422);
        $this->assertSame('active', DB::table('company_companies')->where('id', $id)->value('status'));

        $this->api('POST', "/{$id}/suspend", ['reason' => 'fives'])->assertNoContent();
    }

    public function test_a_suspended_company_vanishes_from_the_public_and_its_members_are_blocked_but_can_still_sign_in(): void
    {
        $id = $this->company('PT Maju', 'pt-maju', ['logo_path' => 'company/logos/abc.jpg']);
        $admin = $this->member($id, 'andi@example.test', 'company_admin');
        $token = $this->userToken($admin);
        $this->get('/api/v1/companies/pt-maju')->assertOk();

        $this->api('POST', "/{$id}/suspend", ['reason' => 'Under investigation.'])->assertNoContent();

        $this->get('/api/v1/companies/pt-maju')->assertNotFound();
        $this->get('/api/v1/companies/pt-maju/logo')->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/company/members')->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/company/invitations', ['email' => 'new@example.test', 'role' => 'recruiter'])->assertStatus(403);

        // The member still signs in and still sees the company with its status, but never the reason.
        $this->app['auth']->forgetGuards();
        $me = $this->withToken($token)->getJson('/api/v1/company/me')->assertOk()->assertJsonPath('data.company.status', 'suspended');
        $this->app['auth']->forgetGuards();
        $profile = $this->withToken($token)->getJson('/api/v1/company/profile')->assertOk();
        $this->assertStringNotContainsString('Under investigation', (string) $me->getContent().(string) $profile->getContent());
    }

    public function test_the_tokens_of_the_members_are_not_revoked(): void
    {
        $id = $this->company('PT Maju', 'pt-maju');
        $member = $this->member($id, 'andi@example.test', 'company_admin');
        $this->userToken($member);

        $this->api('POST', "/{$id}/suspend", ['reason' => 'Under investigation.'])->assertNoContent();

        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $member->id)->count());
        $this->assertSame('active', UserModel::query()->findOrFail($member->id)->status);
    }

    public function test_an_invitation_to_a_suspended_company_cannot_be_accepted(): void
    {
        $id = $this->company('PT Maju', 'pt-maju');
        $plain = str_repeat('x', 43);
        DB::table('company_invitations')->insert([
            'company_id' => $id, 'email' => 'new@example.test', 'role' => 'recruiter', 'token_hash' => hash('sha256', $plain),
            'sent_at' => now(), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->api('POST', "/{$id}/suspend", ['reason' => 'Under investigation.'])->assertNoContent();

        $this->getJson("/api/v1/auth/invitations/{$plain}")->assertStatus(403)->assertJsonPath('type', 'urn:jobportal:error:company-suspended');
    }

    public function test_suspending_twice_or_an_unknown_company_is_refused_and_keeps_the_first_reason(): void
    {
        $id = $this->company('PT Maju', 'pt-maju');
        $this->api('POST', "/{$id}/suspend", ['reason' => 'First reason.'])->assertNoContent();
        Carbon::setTestNow('2026-10-06 09:00:00');

        $this->api('POST', "/{$id}/suspend", ['reason' => 'Second reason.'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:company-already-suspended');
        $this->api('POST', '/999999/suspend', ['reason' => 'Some reason.'])->assertNotFound();

        $row = DB::table('company_companies')->where('id', $id)->first();
        $this->assertSame('First reason.', $row->suspension_reason);
        $this->assertSame('2026-10-05 09:00:00', $row->suspended_at);
    }

    // ----- restoring -----

    public function test_restoring_brings_everything_back_and_clears_the_reason(): void
    {
        $id = $this->company('PT Maju', 'pt-maju');
        $this->api('POST', "/{$id}/suspend", ['reason' => 'Under investigation.'])->assertNoContent();

        $this->api('POST', "/{$id}/restore", ['note' => 'Cleared after review.'])->assertNoContent();

        $row = DB::table('company_companies')->where('id', $id)->first();
        $this->assertSame('active', $row->status);
        $this->assertNull($row->suspended_at);
        $this->assertNull($row->suspension_reason);
        $this->get('/api/v1/companies/pt-maju')->assertOk();
        $this->api('GET', "/{$id}")->assertJsonPath('data.status', 'active')->assertJsonPath('data.suspension_reason', null);
    }

    public function test_the_note_is_optional_and_limited(): void
    {
        $id = $this->company('PT Maju', 'pt-maju', ['status' => 'suspended', 'suspended_at' => now(), 'suspension_reason' => 'Because.']);

        $this->api('POST', "/{$id}/restore", ['note' => str_repeat('a', 1001)])->assertStatus(422);
        $this->assertSame('suspended', DB::table('company_companies')->where('id', $id)->value('status'));

        $this->api('POST', "/{$id}/restore")->assertNoContent();
    }

    public function test_restoring_an_active_or_unknown_company_is_refused(): void
    {
        $id = $this->company('PT Maju', 'pt-maju');

        $this->api('POST', "/{$id}/restore")->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:company-not-suspended');
        $this->api('POST', '/999999/restore')->assertNotFound();
    }

    public function test_a_company_can_be_suspended_again_after_it_was_restored(): void
    {
        $id = $this->company('PT Maju', 'pt-maju');

        $this->api('POST', "/{$id}/suspend", ['reason' => 'First time.'])->assertNoContent();
        $this->api('POST', "/{$id}/restore")->assertNoContent();
        $this->api('POST', "/{$id}/suspend", ['reason' => 'Second time.'])->assertNoContent();

        $this->assertSame('Second time.', DB::table('company_companies')->where('id', $id)->value('suspension_reason'));
    }

    // ----- audit -----

    public function test_suspending_and_restoring_are_audited_with_the_reason_and_the_note(): void
    {
        $id = $this->company('PT Maju', 'pt-maju');
        $this->api('POST', "/{$id}/suspend", ['reason' => 'Fake job postings.'])->assertNoContent();
        $this->api('POST', "/{$id}/restore", ['note' => 'Cleared.'])->assertNoContent();
        $this->api('POST', "/{$id}/suspend", ['reason' => 'Again.'])->assertNoContent();
        $this->api('POST', "/{$id}/restore")->assertNoContent();

        $suspended = DB::table('platform_audit_logs')->where('action', 'company.suspended')->orderBy('id')->first();
        $restored = DB::table('platform_audit_logs')->where('action', 'company.restored')->orderBy('id')->get();

        $this->assertSame('admin', $suspended->actor_type);
        $this->assertNotNull($suspended->actor_id);
        $this->assertSame('company', $suspended->subject_type);
        $this->assertSame($id, (int) $suspended->subject_id);
        $this->assertSame(['reason' => 'Fake job postings.'], json_decode((string) $suspended->metadata, true));
        $this->assertNotNull($suspended->ip_address);
        $this->assertSame(['note' => 'Cleared.'], json_decode((string) $restored[0]->metadata, true));
        $this->assertNull($restored[1]->metadata);
        $this->assertSame(2, DB::table('platform_audit_logs')->where('action', 'company.suspended')->count());
    }

    public function test_refused_requests_leave_no_audit_entry(): void
    {
        $id = $this->company('PT Maju', 'pt-maju');

        $this->api('POST', "/{$id}/restore")->assertStatus(409);
        $this->api('POST', "/{$id}/suspend", ['reason' => 'no'])->assertStatus(422);
        $this->api('POST', '/999999/suspend', ['reason' => 'Some reason.'])->assertNotFound();

        $this->assertSame(0, DB::table('platform_audit_logs')->whereIn('action', ['company.suspended', 'company.restored'])->count());
    }
}
