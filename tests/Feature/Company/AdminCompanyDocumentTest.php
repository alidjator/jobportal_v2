<?php

namespace Tests\Feature\Company;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

final class AdminCompanyDocumentTest extends FeatureTestCase
{
    private const string SEEN = '2026-10-01T08:00:00+00:00';

    private int $companyId;

    private int $nib;

    private int $tax;

    private int $deed;

    private string $token;

    private int $adminUserId;

    private int $secondAdminUserId;

    private int $recruiterUserId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        Storage::fake();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $this->token = $this->adminToken(['companies.manage']);

        $industry = $this->master('reference_industries', 'technology');
        $organization = $this->master('reference_organization_types', 'private-company');
        $this->companyId = (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Maju Bersama', 'slug' => 'pt-maju-bersama', 'status' => 'active', 'industry_id' => $industry, 'organization_type_id' => $organization,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->adminUserId = $this->member('Andi Admin', 'andi@example.test', 'company_admin');
        $this->secondAdminUserId = $this->member('Dewi Admin', 'dewi@example.test', 'company_admin');
        $this->recruiterUserId = $this->member('Rina Rekruter', 'rina@example.test', 'recruiter');

        $this->nib = $this->type('nib', required: true, sortOrder: 1);
        $this->tax = $this->type('tax-id', required: true, sortOrder: 2);
        $this->deed = $this->type('deed', required: false, sortOrder: 3);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function seededAdminId(): int
    {
        return (int) (AdminModel::query()->where('email', 'seeded-admin@example.test')->value('id')
            ?? AdminModel::query()->create(['name' => 'Seeded', 'email' => 'seeded-admin@example.test', 'password' => 'hash', 'status' => 'active'])->id);
    }

    private function master(string $table, string $slug): int
    {
        return (int) DB::table($table)->insertGetId([
            'name' => json_encode(['id' => $slug, 'en' => $slug]), 'slug' => $slug, 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions, string $email = 'ops@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;
    }

    private function member(string $name, string $email, string $role): int
    {
        $user = UserModel::query()->create(['type' => 'company_member', 'name' => $name, 'email' => $email, 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00']);
        DB::table('company_members')->insert(['company_id' => $this->companyId, 'user_id' => $user->id, 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return (int) $user->id;
    }

    private function type(string $slug, bool $required, int $sortOrder, bool $active = true): int
    {
        return (int) DB::table('company_document_types')->insertGetId([
            'slug' => $slug, 'name' => json_encode(['id' => ucfirst($slug).' ID', 'en' => ucfirst($slug)]),
            'is_required' => $required, 'is_active' => $active, 'sort_order' => $sortOrder, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function document(int $typeId, string $status = 'pending', ?int $companyId = null, string $path = 'company/documents/seed.pdf'): void
    {
        Storage::put($path, "%PDF-1.4\nseed {$typeId}\n%%EOF\n");
        DB::table('company_documents')->insert([
            'company_id' => $companyId ?? $this->companyId, 'document_type_id' => $typeId, 'status' => $status, 'file_path' => $path,
            'original_name' => 'client name.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100,
            'rejection_reason' => $status === 'rejected' ? 'unreadable' : null, 'rejection_note' => $status === 'rejected' ? 'Blurry.' : null,
            'submitted_at' => '2026-10-01 08:00:00', 'reviewed_at' => $status === 'pending' ? null : '2026-10-02 08:00:00',
            'reviewed_by_admin_id' => $status === 'pending' ? null : $this->seededAdminId(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function row(int $typeId): ?object
    {
        return DB::table('company_documents')->where('company_id', $this->companyId)->where('document_type_id', $typeId)->first();
    }

    private function verifiedAt(): ?string
    {
        $value = DB::table('company_companies')->where('id', $this->companyId)->value('verified_at');

        return $value === null ? null : (string) $value;
    }

    /** @param  array<string, mixed>  $body */
    private function api(string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, "/api/v1/admin/companies/{$this->companyId}/documents".$uri, $body);
    }

    /** @return list<object> */
    private function notifications(int $userId): array
    {
        return DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId)->orderBy('created_at')->orderBy('id')->get()->all();
    }

    /** @return list<string> */
    private function audit(string $action): array
    {
        return array_map(fn ($row): string => (string) $row->metadata, DB::table('platform_audit_logs')->where('action', $action)->orderBy('id')->get()->all());
    }

    // ----- access -----

    public function test_every_route_needs_an_admin_with_the_permission(): void
    {
        $this->document($this->nib);
        $weak = $this->adminToken(['faqs.manage'], 'weak@example.test');

        foreach ([['GET', ''], ['GET', "/{$this->nib}/file"], ['POST', "/{$this->nib}/approve"], ['POST', "/{$this->nib}/reject"]] as [$method, $suffix]) {
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
            $this->json($method, "/api/v1/admin/companies/{$this->companyId}/documents".$suffix)->assertStatus(401);
            $this->api($method, $suffix, token: $weak)->assertStatus(403);
        }

        $member = UserModel::query()->find($this->adminUserId);
        $this->api('GET', '', token: $member->createToken('phone', ['*'], now()->addDay())->plainTextToken)->assertStatus(401);
    }

    // ----- the list -----

    public function test_the_list_shows_every_active_type_and_every_inactive_type_that_has_a_document(): void
    {
        $retiredWithDocument = $this->type('retired-a', required: false, sortOrder: 4, active: false);
        $this->type('retired-b', required: false, sortOrder: 5, active: false);
        $this->document($retiredWithDocument, 'approved', path: 'company/documents/retired.pdf');
        $this->document($this->nib, 'rejected');

        $response = $this->api('GET', '')->assertOk();

        $this->assertFalse($response->json('data.verified'));
        $this->assertSame(['nib', 'tax-id', 'deed', 'retired-a'], array_column(array_column($response->json('data.documents'), 'type'), 'slug'));
        $this->assertSame(
            ['id' => $this->nib, 'slug' => 'nib', 'name' => ['id' => 'Nib ID', 'en' => 'Nib'], 'is_required' => true, 'is_active' => true],
            $response->json('data.documents.0.type'),
        );
        $this->assertFalse($response->json('data.documents.3.type.is_active'));
        $this->assertSame([
            'status' => 'rejected', 'original_name' => 'client name.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100,
            'submitted_at' => self::SEEN, 'reviewed_at' => '2026-10-02T08:00:00+00:00', 'reviewed_by_admin_id' => $this->seededAdminId(),
            'rejection_reason' => 'unreadable', 'rejection_note' => 'Blurry.',
        ], $response->json('data.documents.0.document'));
        $this->assertNull($response->json('data.documents.1.document'));
        $this->assertStringNotContainsString('company/documents', (string) $response->getContent());
    }

    public function test_the_list_works_for_a_suspended_company_and_shows_the_badge(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended', 'verified_at' => '2026-10-02 08:00:00']);

        $this->assertTrue($this->api('GET', '')->assertOk()->json('data.verified'));
    }

    public function test_an_unknown_company_is_404_on_every_route(): void
    {
        $this->app['auth']->forgetGuards();
        $base = '/api/v1/admin/companies/999999/documents';
        $body = ['submitted_at' => self::SEEN, 'reason' => 'expired'];

        $this->withToken($this->token)->getJson($base)->assertNotFound();
        $this->withToken($this->token)->getJson("{$base}/{$this->nib}/file")->assertNotFound();
        $this->withToken($this->token)->postJson("{$base}/{$this->nib}/approve", $body)->assertNotFound();
        $this->withToken($this->token)->postJson("{$base}/{$this->nib}/reject", $body)->assertNotFound();
    }

    // ----- the file -----

    public function test_an_admin_downloads_the_file_and_the_opening_is_audited(): void
    {
        $this->document($this->nib);

        $response = $this->api('GET', "/{$this->nib}/file")->assertOk();

        $this->assertSame(Storage::get('company/documents/seed.pdf'), $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=nib.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('client name', (string) $response->headers->get('Content-Disposition'));

        $entry = DB::table('platform_audit_logs')->where('action', 'company.document_viewed')->first();
        $this->assertSame('admin', $entry->actor_type);
        $this->assertSame('company', $entry->subject_type);
        $this->assertSame($this->companyId, (int) $entry->subject_id);
        $this->assertSame(['document_type_id' => $this->nib], json_decode((string) $entry->metadata, true));

        $this->api('GET', "/{$this->nib}/file")->assertOk();
        $this->assertCount(2, $this->audit('company.document_viewed'));
    }

    public function test_a_missing_document_or_file_is_404_and_leaves_no_audit_entry(): void
    {
        $this->document($this->tax, path: 'company/documents/gone.pdf');
        Storage::delete('company/documents/gone.pdf');

        $this->api('GET', "/{$this->nib}/file")->assertNotFound();
        $this->api('GET', "/{$this->tax}/file")->assertNotFound();
        $this->api('GET', '/999999/file')->assertNotFound();

        $this->assertSame([], $this->audit('company.document_viewed'));
    }

    // ----- approving -----

    public function test_approving_records_the_decision_and_audits_it(): void
    {
        $this->document($this->nib);

        $this->api('POST', "/{$this->nib}/approve", ['submitted_at' => self::SEEN])->assertNoContent();

        $row = $this->row($this->nib);
        $this->assertSame('approved', $row->status);
        $this->assertSame('2026-10-05 09:00:00', $row->reviewed_at);
        $this->assertNotNull($row->reviewed_by_admin_id);
        $this->assertNull($row->rejection_reason);
        $this->assertSame(['document_type_id' => $this->nib], json_decode($this->audit('company.document_approved')[0], true));
        $this->assertNull($this->verifiedAt(), 'the tax document is still missing');
    }

    public function test_approving_the_last_required_document_gives_the_badge_and_tells_the_company_admins(): void
    {
        $this->document($this->nib, 'approved');
        $this->document($this->tax, path: 'company/documents/seed-tax.pdf');

        $this->api('POST', "/{$this->tax}/approve", ['submitted_at' => self::SEEN])->assertNoContent();

        $this->assertSame('2026-10-05 09:00:00', $this->verifiedAt());

        foreach ([$this->adminUserId, $this->secondAdminUserId] as $userId) {
            $types = array_map(fn ($row): string => $row->type, $this->notifications($userId));
            $this->assertEqualsCanonicalizing(['company.document_approved', 'company.verified'], $types);
        }

        $approved = collect($this->notifications($this->adminUserId))->firstWhere('type', 'company.document_approved');
        $this->assertSame(['document_type_id' => $this->tax, 'document_type_slug' => 'tax-id'], json_decode((string) $approved->data, true));
        $verified = collect($this->notifications($this->adminUserId))->firstWhere('type', 'company.verified');
        $this->assertSame('{}', $verified->data);
        $this->assertSame([], $this->notifications($this->recruiterUserId), 'a recruiter is not told');
        $this->assertNull($approved->read_at);
    }

    public function test_the_company_admin_sees_the_notifications_through_the_api(): void
    {
        $this->document($this->nib, 'approved');
        $this->document($this->tax, path: 'company/documents/seed-tax.pdf');
        $this->api('POST', "/{$this->tax}/approve", ['submitted_at' => self::SEEN])->assertNoContent();

        $token = UserModel::query()->find($this->adminUserId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $response = $this->withToken($token)->getJson('/api/v1/me/notifications')->assertOk();

        $this->assertEqualsCanonicalizing(['company.document_approved', 'company.verified'], array_column($response->json('data'), 'type'));
        $this->assertStringContainsString('"data":{}', (string) $response->getContent());
    }

    public function test_approving_a_document_of_a_suspended_company_works(): void
    {
        $this->document($this->nib);
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->api('POST', "/{$this->nib}/approve", ['submitted_at' => self::SEEN])->assertNoContent();

        $this->assertSame('approved', $this->row($this->nib)->status);
    }

    // ----- rejecting -----

    public function test_rejecting_records_the_reason_and_the_note_tells_the_company_and_leaves_the_badge_alone(): void
    {
        $this->document($this->nib);

        $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN, 'reason' => 'wrong_document', 'note' => '  This is an ID card.  '])->assertNoContent();

        $row = $this->row($this->nib);
        $this->assertSame(['rejected', 'wrong_document', 'This is an ID card.', '2026-10-05 09:00:00'], [$row->status, $row->rejection_reason, $row->rejection_note, $row->reviewed_at]);
        $this->assertNotNull($row->reviewed_by_admin_id);
        $this->assertNull($this->verifiedAt());
        $this->assertSame(
            ['document_type_id' => $this->nib, 'reason' => 'wrong_document', 'note' => 'This is an ID card.'],
            json_decode($this->audit('company.document_rejected')[0], true),
        );

        $notification = $this->notifications($this->adminUserId);
        $this->assertCount(1, $notification);
        $this->assertSame('company.document_rejected', $notification[0]->type);
        $this->assertSame(
            ['document_type_id' => $this->nib, 'document_type_slug' => 'nib', 'reason' => 'wrong_document', 'note' => 'This is an ID card.'],
            json_decode((string) $notification[0]->data, true),
        );
        $this->assertCount(1, $this->notifications($this->secondAdminUserId));
    }

    public function test_the_note_is_optional_except_for_the_reason_other(): void
    {
        $this->document($this->nib);
        $this->document($this->tax, path: 'company/documents/seed-tax.pdf');

        $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN, 'reason' => 'other'])->assertStatus(422)->assertJsonValidationErrors(['note']);
        $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN, 'reason' => 'other', 'note' => '   '])->assertStatus(422)->assertJsonValidationErrors(['note']);
        $this->assertSame('pending', $this->row($this->nib)->status);

        $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN, 'reason' => 'other', 'note' => 'See the phone call.'])->assertNoContent();
        $this->api('POST', "/{$this->tax}/reject", ['submitted_at' => self::SEEN, 'reason' => 'expired'])->assertNoContent();

        $this->assertNull($this->row($this->tax)->rejection_note);
        $this->assertSame(['document_type_id' => $this->tax, 'reason' => 'expired', 'note' => null], json_decode($this->audit('company.document_rejected')[1], true));
    }

    public function test_every_reason_code_is_accepted_and_others_are_refused(): void
    {
        foreach (['unreadable', 'expired', 'wrong_document', 'company_mismatch', 'incomplete', 'other'] as $reason) {
            DB::table('company_documents')->delete();
            $this->document($this->nib);

            $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN, 'reason' => $reason, 'note' => 'Note.'])->assertNoContent();
            $this->assertSame($reason, $this->row($this->nib)->rejection_reason);
        }

        DB::table('company_documents')->delete();
        $this->document($this->nib);
        $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN, 'reason' => 'because'])->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN])->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN, 'reason' => 'expired', 'note' => str_repeat('a', 501)])->assertStatus(422)->assertJsonValidationErrors(['note']);
        $this->assertSame('pending', $this->row($this->nib)->status);
    }

    // ----- conflicts and validation -----

    public function test_a_document_replaced_after_the_admin_looked_is_not_decided(): void
    {
        $this->document($this->nib);
        DB::table('company_documents')->update(['submitted_at' => '2026-10-04 12:00:00']);

        $this->api('POST', "/{$this->nib}/approve", ['submitted_at' => self::SEEN])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:document-changed');
        $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN, 'reason' => 'expired'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:document-changed');

        $this->assertSame('pending', $this->row($this->nib)->status);
        $this->assertSame([], $this->audit('company.document_approved'));
        $this->assertSame([], $this->notifications($this->adminUserId));

        $this->api('POST', "/{$this->nib}/approve", ['submitted_at' => '2026-10-04T12:00:00+00:00'])->assertNoContent();
        $this->assertSame('approved', $this->row($this->nib)->status);
    }

    public function test_the_submitted_at_is_compared_as_a_moment_so_other_offsets_and_z_work(): void
    {
        $this->document($this->nib);
        $this->document($this->tax, path: 'company/documents/seed-tax.pdf');

        $this->api('POST', "/{$this->nib}/approve", ['submitted_at' => '2026-10-01T15:00:00+07:00'])->assertNoContent();
        $this->api('POST', "/{$this->tax}/approve", ['submitted_at' => '2026-10-01T08:00:00Z'])->assertNoContent();
    }

    public function test_only_a_pending_document_can_be_reviewed(): void
    {
        foreach (['approved', 'rejected'] as $status) {
            DB::table('company_documents')->delete();
            $this->document($this->nib, $status);

            $this->api('POST', "/{$this->nib}/approve", ['submitted_at' => self::SEEN])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:document-not-pending');
            $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN, 'reason' => 'expired'])->assertStatus(409)->assertJsonPath('type', 'urn:jobportal:error:document-not-pending');
            $this->assertSame($status, $this->row($this->nib)->status);
        }

        $this->assertSame([], $this->audit('company.document_approved'));
        $this->assertSame([], $this->audit('company.document_rejected'));
        $this->assertSame([], $this->notifications($this->adminUserId));
    }

    public function test_the_submitted_at_is_required_and_must_be_a_timestamp(): void
    {
        $this->document($this->nib);

        $this->api('POST', "/{$this->nib}/approve")->assertStatus(422)->assertJsonValidationErrors(['submitted_at']);
        $this->api('POST', "/{$this->nib}/approve", ['submitted_at' => 'yesterday'])->assertStatus(422)->assertJsonValidationErrors(['submitted_at']);
        $this->api('POST', "/{$this->nib}/approve", ['submitted_at' => '2026-10-01'])->assertStatus(422)->assertJsonValidationErrors(['submitted_at']);
        $this->api('POST', "/{$this->nib}/reject", ['reason' => 'expired'])->assertStatus(422)->assertJsonValidationErrors(['submitted_at']);
    }

    public function test_an_unknown_type_or_missing_document_is_404(): void
    {
        $body = ['submitted_at' => self::SEEN, 'reason' => 'expired'];

        $this->api('POST', "/{$this->nib}/approve", $body)->assertNotFound();
        $this->api('POST', "/{$this->nib}/reject", $body)->assertNotFound();
        $this->api('POST', '/999999/approve', $body)->assertNotFound();
        $this->api('POST', '/999999/reject', $body)->assertNotFound();
    }

    public function test_refused_reviews_leave_no_audit_entry_and_no_notification(): void
    {
        $this->document($this->nib);

        $this->api('POST', "/{$this->nib}/approve", ['submitted_at' => 'bad'])->assertStatus(422);
        $this->api('POST', '/999999/approve', ['submitted_at' => self::SEEN])->assertNotFound();
        $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN, 'reason' => 'nope'])->assertStatus(422);

        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'like', 'company.document_%')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    // ----- the whole cycle -----

    public function test_a_rejected_company_uploads_again_and_the_admin_can_approve_the_new_file(): void
    {
        $this->document($this->nib);
        $this->api('POST', "/{$this->nib}/reject", ['submitted_at' => self::SEEN, 'reason' => 'unreadable'])->assertNoContent();

        $companyAdmin = UserModel::query()->find($this->adminUserId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $upload = UploadedFile::fake()->createWithContent('again.pdf', "%PDF-1.4\nagain\n%%EOF\n");
        $this->withToken($companyAdmin)->post("/api/v1/company/documents/{$this->nib}", ['file' => $upload], ['Accept' => 'application/json'])->assertNoContent();

        $listed = $this->api('GET', '')->json('data.documents.0.document');
        $this->assertSame('pending', $listed['status']);
        $this->assertNull($listed['rejection_reason']);

        $this->api('POST', "/{$this->nib}/approve", ['submitted_at' => $listed['submitted_at']])->assertNoContent();
        $this->assertSame('approved', $this->row($this->nib)->status);
    }

    // ----- badge changes caused by the type list -----

    public function test_making_a_missing_type_optional_gives_the_badge_and_tells_the_company(): void
    {
        $this->document($this->nib, 'approved');
        $this->document($this->tax, 'pending', path: 'company/documents/seed-tax.pdf');
        $other = (int) DB::table('company_companies')->insertGetId([
            'name' => 'PT Lain', 'slug' => 'pt-lain', 'status' => 'active', 'industry_id' => DB::table('reference_industries')->value('id'),
            'organization_type_id' => DB::table('reference_organization_types')->value('id'), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->putJson("/api/v1/admin/company-document-types/{$this->tax}", [
            'name' => ['id' => 'Tax ID', 'en' => 'Tax'], 'is_required' => false, 'sort_order' => 2,
        ])->assertOk();

        $this->assertSame('2026-10-05 09:00:00', $this->verifiedAt());
        $this->assertSame(['company.verified'], array_map(fn ($row): string => $row->type, $this->notifications($this->adminUserId)));
        $this->assertSame(['company.verified'], array_map(fn ($row): string => $row->type, $this->notifications($this->secondAdminUserId)));
        $this->assertSame([], $this->notifications($this->recruiterUserId));
        $this->assertSame(1, DB::table('notifications')->where('type', 'company.verified')->where('notifiable_id', $this->adminUserId)->count());
        $this->assertNull(DB::table('company_companies')->where('id', $other)->value('verified_at'));
    }

    public function test_a_new_required_type_withdraws_the_badge_and_tells_the_company(): void
    {
        $this->document($this->nib, 'approved');
        $this->document($this->tax, 'approved', path: 'company/documents/seed-tax.pdf');
        DB::table('company_companies')->where('id', $this->companyId)->update(['verified_at' => '2026-10-02 08:00:00']);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->postJson('/api/v1/admin/company-document-types', [
            'name' => ['id' => 'Izin', 'en' => 'License'], 'is_required' => true,
        ])->assertCreated();

        $this->assertNull($this->verifiedAt());
        $this->assertSame(['company.verification_withdrawn'], array_map(fn ($row): string => $row->type, $this->notifications($this->adminUserId)));
    }

    public function test_a_company_replacing_its_approved_document_is_not_told_about_the_badge(): void
    {
        $this->document($this->nib, 'approved');
        $this->document($this->tax, 'approved', path: 'company/documents/seed-tax.pdf');
        DB::table('company_companies')->where('id', $this->companyId)->update(['verified_at' => '2026-10-02 08:00:00']);

        $token = UserModel::query()->find($this->adminUserId)->createToken('phone', ['*'], now()->addDay())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $upload = UploadedFile::fake()->createWithContent('new.pdf', "%PDF-1.4\nnew\n%%EOF\n");
        $this->withToken($token)->post("/api/v1/company/documents/{$this->nib}", ['file' => $upload], ['Accept' => 'application/json'])->assertNoContent();

        $this->assertNull($this->verifiedAt());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_a_type_change_that_does_not_move_a_badge_sends_nothing(): void
    {
        $this->document($this->nib, 'approved');
        $this->document($this->tax, 'approved', path: 'company/documents/seed-tax.pdf');
        DB::table('company_companies')->where('id', $this->companyId)->update(['verified_at' => '2026-10-02 08:00:00']);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->putJson("/api/v1/admin/company-document-types/{$this->deed}", [
            'name' => ['id' => 'Akta', 'en' => 'Deed'], 'is_required' => false, 'is_active' => false,
        ])->assertOk();

        $this->assertSame('2026-10-02 08:00:00', $this->verifiedAt());
        $this->assertSame(0, DB::table('notifications')->count());
    }
}
