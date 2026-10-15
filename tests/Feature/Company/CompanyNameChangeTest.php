<?php

namespace Tests\Feature\Company;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

/** `docs/design/phase-8-platform-admin.md` section 3: the name of a verified company is locked; a change goes through a request with a PDF, decided by an admin. */
final class CompanyNameChangeTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    private int $companyId;

    private int $adminId;

    private string $adminToken;

    private string $recruiterToken;

    private string $opsToken;

    private int $opsId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        Storage::fake();

        $this->companyId = $this->company('PT Maju Bersama', 'pt-maju-bersama', ['verified_at' => '2026-09-01 00:00:00']);
        $admin = $this->member($this->companyId, 'Andi Admin', 'andi@example.test', 'company_admin');
        $this->adminId = (int) $admin->id;
        $this->adminToken = $this->tokenOf($admin);
        $this->recruiterToken = $this->tokenOf($this->member($this->companyId, 'Rina Rekruter', 'rina@example.test', 'recruiter'));

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $ops = AdminModel::query()->create(['name' => 'Ops', 'email' => 'ops@example.test', 'password' => 'hash', 'status' => 'active']);
        $ops->givePermissionTo(['companies.manage']);
        $this->opsId = (int) $ops->id;
        $this->opsToken = $ops->createToken('console', ['*'], now()->addDays(30))->plainTextToken;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $attributes */
    private function company(string $name, string $slug, array $attributes = []): int
    {
        return (int) DB::table('company_companies')->insertGetId([
            'name' => $name, 'slug' => $slug, 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'created_at' => now(), 'updated_at' => now(), ...$attributes,
        ]);
    }

    private function member(int $companyId, string $name, string $email, string $role): UserModel
    {
        $user = UserModel::query()->create([
            'type' => 'company_member', 'name' => $name, 'email' => $email, 'password' => Hash::make('password-123'),
            'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00',
        ]);
        CompanyMemberRows::insert($companyId, $user->id, $role);

        return $user;
    }

    private function tokenOf(UserModel $user): string
    {
        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    private function pdf(string $marker = 'deed'): string
    {
        return "%PDF-1.4\n1 0 obj << /Marker ({$marker}) >> endobj\ntrailer << >>\n%%EOF\n";
    }

    /** @param  array<string, mixed>  $fields */
    private function ask(array $fields = [], ?string $token = null, ?string $contents = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $fields = ['new_name' => 'PT Maju Sejahtera', 'reason' => 'The company was renamed by a deed of amendment.', ...$fields];

        if ($contents !== '') {
            $fields['file'] = UploadedFile::fake()->createWithContent('Akta Perubahan.pdf', $contents ?? $this->pdf());
        }

        return $this->withToken($token ?? $this->adminToken)->post('/api/v1/company/name-change-requests', $fields, ['Accept' => 'application/json']);
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->json($method, '/api/v1'.$uri, $body);
    }

    private function ops(string $method, string $uri = '', array $body = []): TestResponse
    {
        return $this->api($method, '/admin/company-name-changes'.$uri, $body, $this->opsToken);
    }

    private function requestId(): int
    {
        return (int) $this->ask()->assertCreated()->json('data.id');
    }

    /** @return list<array{type: string, data: array<string, mixed>}> */
    private function notificationsOf(int $userId): array
    {
        return array_map(
            fn ($row): array => ['type' => (string) $row->type, 'data' => json_decode((string) $row->data, true)],
            DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->orderBy('created_at')->orderBy('id')->get()->all(),
        );
    }

    // ----- the lock -----

    public function test_a_verified_company_cannot_rename_itself_in_the_profile_but_may_change_the_rest(): void
    {
        $this->api('PATCH', '/company/profile', ['name' => 'PT Baru'])->assertConflict()->assertJsonPath('type', 'urn:onesis:error:name-locked');
        $this->assertSame('PT Maju Bersama', DB::table('company_companies')->where('id', $this->companyId)->value('name'));

        $this->api('PATCH', '/company/profile', ['name' => 'PT Maju Bersama', 'bio' => 'We hire.'])->assertNoContent();
        $this->assertSame('We hire.', DB::table('company_companies')->where('id', $this->companyId)->value('bio'));
    }

    public function test_an_unverified_company_renames_itself_freely_and_cannot_ask_for_a_change(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['verified_at' => null]);

        $this->api('PATCH', '/company/profile', ['name' => 'PT Baru'])->assertNoContent();
        $this->assertSame('PT Baru', DB::table('company_companies')->where('id', $this->companyId)->value('name'));

        $this->ask()->assertConflict()->assertJsonPath('type', 'urn:onesis:error:name-not-locked');
        $this->assertSame(0, DB::table('company_name_change_requests')->count());
    }

    // ----- asking -----

    public function test_a_company_admin_asks_for_a_change_with_a_pdf(): void
    {
        $response = $this->ask(['new_name' => '  PT   Maju Sejahtera '])->assertCreated();

        $id = (int) $response->json('data.id');
        $this->assertSame(
            ['id' => $id, 'current_name' => 'PT Maju Bersama', 'new_name' => 'PT Maju Sejahtera', 'reason' => 'The company was renamed by a deed of amendment.', 'document_name' => 'Akta Perubahan.pdf',
                'status' => 'pending', 'decision_note' => null, 'created_at' => '2026-10-05T09:00:00+00:00', 'decided_at' => null],
            $response->json('data'),
        );

        $row = DB::table('company_name_change_requests')->find($id);
        $this->assertSame([$this->companyId, $this->adminId], [(int) $row->company_id, (int) $row->requested_by_user_id]);
        $this->assertSame($this->pdf(), Storage::get($row->document_path));
        $this->assertStringStartsWith('company/name-changes/', $row->document_path);
        $this->assertSame('PT Maju Bersama', DB::table('company_companies')->where('id', $this->companyId)->value('name'), 'nothing changes until an admin approves');

        $entry = DB::table('platform_audit_logs')->where('action', 'company.name_change_requested')->first();
        $this->assertSame(['user', $this->adminId, 'company', $this->companyId, $this->companyId], [$entry->actor_type, (int) $entry->actor_id, $entry->subject_type, (int) $entry->subject_id, (int) $entry->company_id]);
        $this->assertSame(['request_id' => $id], json_decode((string) $entry->metadata, true));
    }

    public function test_only_a_member_who_may_change_the_profile_asks(): void
    {
        $this->ask(token: $this->recruiterToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        $this->api('GET', '/company/name-change-requests', token: $this->recruiterToken)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->postJson('/api/v1/company/name-change-requests', [])->assertUnauthorized();

        DB::table('company_companies')->update(['status' => 'suspended']);
        $this->ask()->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-suspended');

        $this->assertSame(0, DB::table('company_name_change_requests')->count());
        $this->assertSame([], Storage::allFiles());
    }

    public function test_the_fields_and_the_file_are_checked(): void
    {
        $this->ask(['new_name' => 'pt maju bersama'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-name-change')->assertJsonPath('errors.new_name.0', 'The new name is the same as the current name.');
        $this->ask(['new_name' => 'X', 'reason' => 'no'])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-name-change')
            ->assertJsonStructure(['errors' => ['new_name', 'reason']]);
        $this->ask(['reason' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-name-change');
        $this->ask(contents: '')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');
        $this->ask(contents: "\x89PNG\r\n\x1a\n not a pdf")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-document');
        $this->ask(contents: '%PDF-'.str_repeat('a', 10 * 1024 * 1024))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:file-too-large')->assertJsonPath('max_size_kb', 10240);

        $this->assertSame(0, DB::table('company_name_change_requests')->count());
        $this->assertSame([], Storage::allFiles());
    }

    public function test_one_request_waits_at_a_time_and_a_cancelled_one_frees_the_place(): void
    {
        $first = $this->requestId();

        $this->ask(['new_name' => 'PT Lain Lagi'])->assertConflict()->assertJsonPath('type', 'urn:onesis:error:name-change-pending');
        $this->assertCount(1, Storage::allFiles(), 'the file of the refused request is not kept');

        $this->api('POST', "/company/name-change-requests/{$first}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.decided_at', '2026-10-05T09:00:00+00:00');
        $this->ask(['new_name' => 'PT Lain Lagi'])->assertCreated();

        $this->assertSame(['cancelled', 'pending'], DB::table('company_name_change_requests')->orderBy('id')->pluck('status')->all());
    }

    public function test_the_company_reads_its_requests_newest_first_and_cancels_only_its_own(): void
    {
        $first = $this->requestId();
        $this->api('POST', "/company/name-change-requests/{$first}/cancel")->assertOk();
        Carbon::setTestNow('2026-10-06 09:00:00');
        $second = $this->requestId();

        $rows = $this->api('GET', '/company/name-change-requests')->assertOk()->json('data');
        $this->assertSame([$second, $first], array_column($rows, 'id'));
        $this->assertSame(['pending', 'cancelled'], array_column($rows, 'status'));
        $this->assertArrayNotHasKey('decided_by_admin_id', $rows[0]);

        $otherCompany = $this->company('PT Lain', 'pt-lain', ['verified_at' => '2026-09-01 00:00:00']);
        $otherToken = $this->tokenOf($this->member($otherCompany, 'Budi Admin', 'budi@example.test', 'company_admin'));
        $this->api('POST', "/company/name-change-requests/{$second}/cancel", token: $otherToken)->assertNotFound();
        $this->api('GET', '/company/name-change-requests', token: $otherToken)->assertOk()->assertJsonCount(0, 'data');

        $this->api('POST', "/company/name-change-requests/{$first}/cancel")->assertConflict()->assertJsonPath('type', 'urn:onesis:error:name-change-not-pending');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'company.name_change_cancelled')->count());
    }

    // ----- the admin -----

    public function test_every_admin_route_needs_the_companies_permission(): void
    {
        $id = $this->requestId();
        $weak = AdminModel::query()->create(['name' => 'Weak', 'email' => 'weak@example.test', 'password' => 'hash', 'status' => 'active']);
        $weak->givePermissionTo(['faqs.manage']);
        $weakToken = $weak->createToken('console', ['*'], now()->addDay())->plainTextToken;

        foreach ([['GET', ''], ['GET', "/{$id}"], ['GET', "/{$id}/document"], ['POST', "/{$id}/approve"], ['POST', "/{$id}/reject"]] as [$method, $suffix]) {
            $this->app['auth']->forgetGuards();
            $this->flushHeaders()->json($method, '/api/v1/admin/company-name-changes'.$suffix)->assertUnauthorized();
            $this->api($method, '/admin/company-name-changes'.$suffix, ['note' => 'Not valid.'], $weakToken)->assertForbidden();
            $this->api($method, '/admin/company-name-changes'.$suffix, ['note' => 'Not valid.'], $this->adminToken)->assertUnauthorized();
        }

        $this->assertSame('pending', DB::table('company_name_change_requests')->value('status'));
    }

    public function test_the_admin_lists_the_pending_requests_first_and_reads_one_with_its_company(): void
    {
        $old = $this->requestId();
        $this->ops('POST', "/{$old}/reject", ['note' => 'The deed is not readable.'])->assertOk();
        Carbon::setTestNow('2026-10-06 09:00:00');
        $waiting = $this->requestId();

        $rows = $this->ops('GET')->assertOk()->assertJsonPath('meta.total', 2)->json('data');
        $this->assertSame([$waiting, $old], array_column($rows, 'id'));

        $this->assertSame([$waiting], array_column($this->ops('GET', '?filter[status]=pending')->assertOk()->json('data'), 'id'));
        $this->ops('GET', '?filter[status]=other')->assertStatus(422);

        $this->assertSame(
            [
                'id' => $waiting, 'company' => ['id' => $this->companyId, 'name' => 'PT Maju Bersama', 'slug' => 'pt-maju-bersama', 'status' => 'active'], 'requested_by_user_id' => $this->adminId,
                'current_name' => 'PT Maju Bersama', 'new_name' => 'PT Maju Sejahtera', 'reason' => 'The company was renamed by a deed of amendment.', 'document_name' => 'Akta Perubahan.pdf',
                'has_document' => true, 'status' => 'pending', 'decided_by_admin_id' => null, 'decision_note' => null, 'created_at' => '2026-10-06T09:00:00+00:00', 'decided_at' => null,
            ],
            $this->ops('GET', "/{$waiting}")->assertOk()->json('data'),
        );
        $this->ops('GET', '/999999')->assertNotFound();
    }

    public function test_the_admin_opens_the_proof_as_a_download_and_every_opening_is_audited(): void
    {
        $id = $this->requestId();

        $response = $this->ops('GET', "/{$id}/document")->assertOk();

        $this->assertSame($this->pdf(), $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("attachment; filename=name-change-{$id}.pdf", (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));

        $entry = DB::table('platform_audit_logs')->where('action', 'company.name_change_document_viewed')->first();
        $this->assertSame(['admin', $this->opsId, $this->companyId], [$entry->actor_type, (int) $entry->actor_id, (int) $entry->subject_id]);

        $this->ops('GET', '/999999/document')->assertNotFound();
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'company.name_change_document_viewed')->count());
    }

    public function test_approving_changes_the_name_keeps_the_badge_and_the_slug_and_tells_the_company_admins(): void
    {
        $id = $this->requestId();

        $this->ops('POST', "/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.decided_by_admin_id', $this->opsId)->assertJsonPath('data.decided_at', '2026-10-05T09:00:00+00:00');

        $company = DB::table('company_companies')->find($this->companyId);
        $this->assertSame(['PT Maju Sejahtera', 'pt-maju-bersama', '2026-09-01 00:00:00'], [$company->name, $company->slug, $company->verified_at]);

        $this->assertSame([['type' => 'company.name_change_approved', 'data' => ['request_id' => $id]]], $this->notificationsOf($this->adminId));
        $this->assertSame([], $this->notificationsOf((int) UserModel::query()->where('email', 'rina@example.test')->value('id')), 'only the company admins are told');

        $entry = DB::table('platform_audit_logs')->where('action', 'company.name_change_approved')->first();
        $this->assertSame(['admin', $this->opsId, $this->companyId], [$entry->actor_type, (int) $entry->actor_id, (int) $entry->company_id]);
        $this->assertSame(['request_id' => $id, 'from' => 'PT Maju Bersama', 'to' => 'PT Maju Sejahtera'], json_decode((string) $entry->metadata, true));

        $this->ops('POST', "/{$id}/approve")->assertConflict()->assertJsonPath('type', 'urn:onesis:error:name-change-not-pending');
        $this->ops('POST', "/{$id}/reject", ['note' => 'Too late now.'])->assertConflict()->assertJsonPath('type', 'urn:onesis:error:name-change-not-pending');
        $this->assertSame('PT Maju Sejahtera', DB::table('company_companies')->where('id', $this->companyId)->value('name'));
    }

    public function test_rejecting_needs_a_note_that_the_company_admins_read_and_leaves_the_name_alone(): void
    {
        $id = $this->requestId();

        $this->ops('POST', "/{$id}/reject")->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:validation-failed');
        $this->ops('POST', "/{$id}/reject", ['note' => 'abc'])->assertStatus(422);
        $this->assertSame('pending', DB::table('company_name_change_requests')->value('status'));

        $this->ops('POST', "/{$id}/reject", ['note' => 'The deed does not show this name.'])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertSame('PT Maju Bersama', DB::table('company_companies')->where('id', $this->companyId)->value('name'));
        $this->assertSame([['type' => 'company.name_change_rejected', 'data' => ['request_id' => $id, 'note' => 'The deed does not show this name.']]], $this->notificationsOf($this->adminId));

        $mine = $this->api('GET', '/company/name-change-requests')->assertOk()->json('data.0');
        $this->assertSame(['rejected', 'The deed does not show this name.'], [$mine['status'], $mine['decision_note']]);
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'company.name_change_rejected')->count());

        $this->ask(['new_name' => 'PT Maju Makmur'])->assertCreated();
    }

    // ----- retention -----

    public function test_the_proof_is_deleted_90_days_after_the_decision_and_the_request_stays(): void
    {
        $approved = $this->requestId();
        $this->ops('POST', "/{$approved}/approve")->assertOk();
        $rejected = (int) $this->ask(['new_name' => 'PT Maju Makmur'])->assertCreated()->json('data.id');
        $this->ops('POST', "/{$rejected}/reject", ['note' => 'Not the right deed.'])->assertOk();
        $recent = (int) $this->ask(['new_name' => 'PT Maju Jaya'])->assertCreated()->json('data.id');
        $this->ops('POST', "/{$recent}/reject", ['note' => 'Not the right deed.'])->assertOk();
        $pending = (int) $this->ask(['new_name' => 'PT Maju Abadi'])->assertCreated()->json('data.id');

        DB::table('company_name_change_requests')->where('id', $approved)->update(['decided_at' => '2026-07-05 08:59:59']);
        DB::table('company_name_change_requests')->where('id', $rejected)->update(['decided_at' => '2026-07-10 09:00:00']);
        DB::table('company_name_change_requests')->where('id', $recent)->update(['decided_at' => '2026-07-07 09:00:00']);
        $paths = DB::table('company_name_change_requests')->pluck('document_path', 'id')->all();

        Artisan::call('company:purge-name-change-documents');

        $this->assertNull(DB::table('company_name_change_requests')->where('id', $approved)->value('document_path'));
        Storage::assertMissing($paths[$approved]);

        foreach ([$rejected, $recent, $pending] as $kept) {
            $this->assertNotNull(DB::table('company_name_change_requests')->where('id', $kept)->value('document_path'), "request {$kept}");
            Storage::assertExists($paths[$kept]);
        }

        $row = DB::table('company_name_change_requests')->find($approved);
        $this->assertSame(['approved', 'Akta Perubahan.pdf'], [$row->status, $row->document_name]);
        $this->ops('GET', "/{$approved}/document")->assertNotFound();
        $this->ops('GET', "/{$approved}")->assertOk()->assertJsonPath('data.has_document', false);
    }
}
