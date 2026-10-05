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

final class AdminDocumentTypeTest extends FeatureTestCase
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
    private function adminToken(array $permissions, string $email = 'ops@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Ops', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addDays(30))->plainTextToken;
    }

    private function api(string $method, string $uri = '', array $body = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/admin/company-document-types'.$uri, $body);
    }

    private function type(string $slug, bool $required = false, bool $active = true, int $sortOrder = 0): int
    {
        return (int) DB::table('company_document_types')->insertGetId([
            'slug' => $slug, 'name' => json_encode(['id' => ucfirst($slug).' ID', 'en' => ucfirst($slug)]),
            'is_required' => $required, 'is_active' => $active, 'sort_order' => $sortOrder,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function company(string $slug, ?string $verifiedAt = null): int
    {
        return (int) DB::table('company_companies')->insertGetId([
            'name' => strtoupper($slug), 'slug' => $slug, 'status' => 'active', 'industry_id' => $this->industryId(), 'organization_type_id' => $this->organizationTypeId(),
            'verified_at' => $verifiedAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function document(int $companyId, int $typeId, string $status = 'approved'): void
    {
        DB::table('company_documents')->insert([
            'company_id' => $companyId, 'document_type_id' => $typeId, 'status' => $status, 'file_path' => 'company/documents/x.pdf',
            'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1000, 'submitted_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function verifiedAt(int $companyId): ?string
    {
        $value = DB::table('company_companies')->where('id', $companyId)->value('verified_at');

        return $value === null ? null : (string) $value;
    }

    /** @return array<string, mixed> */
    private function body(string $en = 'Business License', array $override = []): array
    {
        return ['name' => ['id' => 'Izin Usaha', 'en' => $en], ...$override];
    }

    // ----- access -----

    public function test_every_route_needs_an_admin_with_the_permission(): void
    {
        $id = $this->type('nib');
        $weak = $this->adminToken(['faqs.manage'], 'weak@example.test');

        foreach ([['GET', ''], ['POST', ''], ['PUT', "/{$id}"]] as [$method, $uri]) {
            $this->flushHeaders();
            $this->app['auth']->forgetGuards();
            $this->json($method, '/api/v1/admin/company-document-types'.$uri)->assertStatus(401);
            $this->api($method, $uri, $this->body(), $weak)->assertStatus(403);
        }

        $user = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Rina', 'email' => 'rina@example.test', 'status' => 'active', 'locale' => 'en']);
        $this->api('GET', '', token: $user->createToken('phone', ['*'], now()->addDay())->plainTextToken)->assertStatus(401);
    }

    public function test_there_is_no_delete(): void
    {
        $id = $this->type('nib');

        $this->api('DELETE', "/{$id}")->assertStatus(405);
        $this->assertSame(1, DB::table('company_document_types')->count());
    }

    // ----- the list -----

    public function test_the_list_shows_every_type_including_inactive_ones_in_order(): void
    {
        $third = $this->type('third', sortOrder: 2);
        $first = $this->type('first', required: true, sortOrder: 1);
        $second = $this->type('second', active: false, sortOrder: 1);

        $response = $this->api('GET')->assertOk();

        $this->assertSame([$first, $second, $third], array_column($response->json('data'), 'id'));
        $this->assertSame([
            'id' => $first, 'slug' => 'first', 'name' => ['id' => 'First ID', 'en' => 'First'],
            'is_required' => true, 'is_active' => true, 'sort_order' => 1,
        ], $response->json('data.0'));
        $this->assertFalse($response->json('data.1.is_active'));
    }

    // ----- create -----

    public function test_an_admin_creates_a_type_and_the_slug_comes_from_the_english_name(): void
    {
        $response = $this->api('POST', '', $this->body('Business License', ['is_required' => true, 'sort_order' => 4]))->assertCreated();

        $this->assertSame([
            'id' => $response->json('data.id'), 'slug' => 'business-license', 'name' => ['id' => 'Izin Usaha', 'en' => 'Business License'],
            'is_required' => true, 'is_active' => true, 'sort_order' => 4,
        ], $response->json('data'));
        $this->assertSame(1, DB::table('company_document_types')->where('slug', 'business-license')->count());
    }

    public function test_the_defaults_are_optional_active_and_order_zero(): void
    {
        $data = $this->api('POST', '', $this->body())->assertCreated()->json('data');

        $this->assertSame([false, true, 0], [$data['is_required'], $data['is_active'], $data['sort_order']]);
    }

    public function test_a_taken_slug_gets_a_number_and_a_name_without_letters_gets_the_fallback(): void
    {
        $this->api('POST', '', $this->body('Business License'))->assertCreated();
        $second = $this->api('POST', '', $this->body('Business License'))->assertCreated();
        $third = $this->api('POST', '', $this->body('Business License'))->assertCreated();
        $symbols = $this->api('POST', '', $this->body('!!!'))->assertCreated();

        $this->assertSame('business-license-2', $second->json('data.slug'));
        $this->assertSame('business-license-3', $third->json('data.slug'));
        $this->assertSame('document', $symbols->json('data.slug'));
    }

    public function test_invalid_bodies_are_refused_per_field(): void
    {
        $this->api('POST', '', [])->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->api('POST', '', ['name' => ['id' => 'Izin']])->assertStatus(422)->assertJsonValidationErrors(['name.en']);
        $this->api('POST', '', $this->body(override: ['sort_order' => 65536]))->assertStatus(422)->assertJsonValidationErrors(['sort_order']);
        $this->api('POST', '', $this->body(override: ['is_required' => 'maybe']))->assertStatus(422)->assertJsonValidationErrors(['is_required']);
        $this->api('POST', '', $this->body(str_repeat('a', 256)))->assertStatus(422)->assertJsonValidationErrors(['name.en']);

        $this->assertSame(0, DB::table('company_document_types')->count());
    }

    // ----- update -----

    public function test_an_update_changes_everything_except_the_slug(): void
    {
        $id = $this->type('nib', sortOrder: 1);

        $response = $this->api('PUT', "/{$id}", ['name' => ['id' => 'NIB Baru', 'en' => 'New NIB'], 'is_required' => true, 'is_active' => false, 'sort_order' => 9])->assertOk();

        $this->assertSame([
            'id' => $id, 'slug' => 'nib', 'name' => ['id' => 'NIB Baru', 'en' => 'New NIB'],
            'is_required' => true, 'is_active' => false, 'sort_order' => 9,
        ], $response->json('data'));
    }

    public function test_an_unknown_type_is_404_and_the_body_is_validated(): void
    {
        $this->api('PUT', '/999999', $this->body())->assertNotFound();
        $this->api('PUT', '/abc', $this->body())->assertNotFound();

        $id = $this->type('nib');
        $this->api('PUT', "/{$id}", [])->assertStatus(422);
    }

    // ----- the badge -----

    public function test_a_new_required_type_withdraws_the_badge_from_companies_that_lack_the_document(): void
    {
        $nib = $this->type('nib', required: true);
        $complete = $this->company('pt-lengkap', '2026-01-01 10:00:00');
        $this->document($complete, $nib);

        $this->api('POST', '', $this->body('Tax ID', ['is_required' => true]))->assertCreated();

        $this->assertNull($this->verifiedAt($complete));
    }

    public function test_a_new_optional_or_inactive_type_changes_nothing(): void
    {
        $nib = $this->type('nib', required: true);
        $complete = $this->company('pt-lengkap', '2026-01-01 10:00:00');
        $this->document($complete, $nib);

        $this->api('POST', '', $this->body('Deed'))->assertCreated();
        $this->api('POST', '', $this->body('Tax ID', ['is_required' => true, 'is_active' => false]))->assertCreated();

        $this->assertSame('2026-01-01 10:00:00', $this->verifiedAt($complete));
    }

    public function test_making_a_missing_type_optional_grants_the_badge_and_a_kept_badge_keeps_its_date(): void
    {
        $nib = $this->type('nib', required: true);
        $tax = $this->type('tax', required: true);
        $both = $this->company('pt-lengkap', '2026-01-01 10:00:00');
        $this->document($both, $nib);
        $this->document($both, $tax);
        $onlyNib = $this->company('pt-sebagian');
        $this->document($onlyNib, $nib);
        $this->document($onlyNib, $tax, 'pending');
        $none = $this->company('pt-kosong');

        $this->api('PUT', "/{$tax}", $this->body('Tax', ['is_required' => false]))->assertOk();

        $this->assertSame('2026-01-01 10:00:00', $this->verifiedAt($both), 'a company that stays verified keeps its date');
        $this->assertSame('2026-10-05 09:00:00', $this->verifiedAt($onlyNib), 'its only required document is approved now');
        $this->assertNull($this->verifiedAt($none));

        $this->api('PUT', "/{$tax}", $this->body('Tax', ['is_required' => true]))->assertOk();

        $this->assertSame('2026-01-01 10:00:00', $this->verifiedAt($both));
        $this->assertNull($this->verifiedAt($onlyNib), 'the pending document does not count');
    }

    public function test_a_pending_or_rejected_document_does_not_count(): void
    {
        $nib = $this->type('nib', required: true);
        $this->type('tax');
        $pending = $this->company('pt-menunggu');
        $this->document($pending, $nib, 'pending');
        $rejected = $this->company('pt-ditolak');
        $this->document($rejected, $nib, 'rejected');

        $this->api('PUT', "/{$nib}", $this->body('Nib', ['is_required' => true, 'sort_order' => 3]))->assertOk();

        $this->assertNull($this->verifiedAt($pending));
        $this->assertNull($this->verifiedAt($rejected));
    }

    public function test_without_an_active_required_type_nobody_is_verified(): void
    {
        $nib = $this->type('nib', required: true);
        $company = $this->company('pt-lengkap', '2026-01-01 10:00:00');
        $this->document($company, $nib);

        $this->api('PUT', "/{$nib}", $this->body('Nib', ['is_required' => true, 'is_active' => false]))->assertOk();
        $this->assertNull($this->verifiedAt($company));

        $this->api('PUT', "/{$nib}", $this->body('Nib', ['is_required' => false, 'is_active' => true]))->assertOk();
        $this->assertNull($this->verifiedAt($company), 'an optional type alone gives no badge');

        $this->api('PUT', "/{$nib}", $this->body('Nib', ['is_required' => true, 'is_active' => true]))->assertOk();
        $this->assertSame('2026-10-05 09:00:00', $this->verifiedAt($company));
    }

    public function test_a_change_of_names_or_order_leaves_the_badge_alone(): void
    {
        $nib = $this->type('nib', required: true);
        $verified = $this->company('pt-lengkap', '2026-01-01 10:00:00');
        $this->document($verified, $nib);
        $unverified = $this->company('pt-kosong');

        $this->api('PUT', "/{$nib}", $this->body('Renamed', ['is_required' => true, 'sort_order' => 7]))->assertOk();

        $this->assertSame('2026-01-01 10:00:00', $this->verifiedAt($verified));
        $this->assertNull($this->verifiedAt($unverified));
    }

    // ----- audit -----

    public function test_creating_a_type_is_audited(): void
    {
        $id = (int) $this->api('POST', '', $this->body('Tax ID', ['is_required' => true]))->assertCreated()->json('data.id');

        $entry = DB::table('platform_audit_logs')->where('action', 'company_document_type.created')->first();

        $this->assertSame('admin', $entry->actor_type);
        $this->assertSame('company_document_type', $entry->subject_type);
        $this->assertSame($id, (int) $entry->subject_id);
        $this->assertSame(['slug' => 'tax-id', 'is_required' => true, 'is_active' => true], json_decode((string) $entry->metadata, true));
    }

    public function test_only_a_change_of_the_flags_is_audited(): void
    {
        $id = $this->type('nib');

        $this->api('PUT', "/{$id}", $this->body('Renamed', ['sort_order' => 5]))->assertOk();
        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'company_document_type.updated')->count());

        $this->api('PUT', "/{$id}", $this->body('Renamed', ['is_required' => true]))->assertOk();

        $entry = DB::table('platform_audit_logs')->where('action', 'company_document_type.updated')->first();
        $this->assertSame($id, (int) $entry->subject_id);
        $this->assertSame([
            'slug' => 'nib',
            'old' => ['is_required' => false, 'is_active' => true],
            'new' => ['is_required' => true, 'is_active' => true],
        ], json_decode((string) $entry->metadata, true));
    }

    public function test_refused_requests_leave_no_audit_entry(): void
    {
        $this->api('POST', '', [])->assertStatus(422);
        $this->api('PUT', '/999999', $this->body(override: ['is_required' => true]))->assertNotFound();

        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'like', 'company_document_type.%')->count());
    }
}
