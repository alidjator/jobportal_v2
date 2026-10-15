<?php

namespace Tests\Feature\Company;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Src\Platform\Application\Settings\SettingsStore;
use Tests\FeatureTestCase;
use Tests\Support\CompanyMemberRows;

final class CompanyDocumentTest extends FeatureTestCase
{
    private int $companyId;

    private int $otherCompanyId;

    private string $adminToken;

    private string $recruiterToken;

    private int $nib;

    private int $tax;

    private int $deed;

    private int $retired;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-05 09:00:00');
        Storage::fake();

        $this->companyId = $this->company('PT Maju Bersama', 'pt-maju-bersama');
        $this->otherCompanyId = $this->company('PT Lain', 'pt-lain');
        $this->adminToken = $this->token($this->member($this->companyId, 'Andi Admin', 'andi@example.test', 'company_admin'));
        $this->recruiterToken = $this->token($this->member($this->companyId, 'Rina Rekruter', 'rina@example.test', 'recruiter'));

        $this->nib = $this->type('nib', required: true, sortOrder: 1);
        $this->tax = $this->type('tax-id', required: true, sortOrder: 2);
        $this->deed = $this->type('deed', required: false, sortOrder: 3);
        $this->retired = $this->type('retired', required: false, active: false, sortOrder: 4);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function master(string $table, string $slug): int
    {
        return (int) DB::table($table)->insertGetId([
            'name' => json_encode(['id' => $slug, 'en' => $slug]), 'slug' => $slug, 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function company(string $name, string $slug, array $attributes = []): int
    {
        $industry = DB::table('reference_industries')->value('id') ?? $this->master('reference_industries', 'technology');
        $organization = DB::table('reference_organization_types')->value('id') ?? $this->master('reference_organization_types', 'private-company');

        return (int) DB::table('company_companies')->insertGetId([
            'name' => $name, 'slug' => $slug, 'status' => 'active', 'industry_id' => $industry, 'organization_type_id' => $organization,
            'created_at' => now(), 'updated_at' => now(), ...$attributes,
        ]);
    }

    private function member(int $companyId, string $name, string $email, string $role): UserModel
    {
        $user = UserModel::query()->create(['type' => 'company_member', 'name' => $name, 'email' => $email, 'status' => 'active', 'locale' => 'en', 'email_verified_at' => '2026-10-01 00:00:00']);
        CompanyMemberRows::insert($companyId, $user->id, $role);

        return $user;
    }

    private function token(UserModel $user): string
    {
        return $user->createToken('phone', ['*'], now()->addDay())->plainTextToken;
    }

    private function type(string $slug, bool $required, int $sortOrder, bool $active = true): int
    {
        return (int) DB::table('company_document_types')->insertGetId([
            'slug' => $slug, 'name' => json_encode(['id' => ucfirst($slug).' ID', 'en' => ucfirst($slug)]),
            'is_required' => $required, 'is_active' => $active, 'sort_order' => $sortOrder, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function pdf(string $marker = 'one'): string
    {
        return "%PDF-1.4\n1 0 obj << /Marker ({$marker}) >> endobj\ntrailer << >>\n%%EOF\n";
    }

    private function png(int $width = 100, int $height = 50): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 90, 200));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function upload(int $typeId, string $contents, string $clientName = 'scan.pdf', ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->post(
            "/api/v1/company/documents/{$typeId}",
            ['file' => UploadedFile::fake()->createWithContent($clientName, $contents)],
            ['Accept' => 'application/json'],
        );
    }

    /** @param  array<string, string>  $headers */
    private function api(string $method, string $uri, ?string $token = null, array $headers = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->adminToken)->json($method, $uri, [], $headers);
    }

    private function row(int $typeId, ?int $companyId = null): ?object
    {
        return DB::table('company_documents')->where('company_id', $companyId ?? $this->companyId)->where('document_type_id', $typeId)->first();
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        return Storage::allFiles();
    }

    private function seedDocument(int $typeId, string $status, ?int $companyId = null, string $path = 'company/documents/seed.pdf'): void
    {
        Storage::put($path, $this->pdf('seed'));
        DB::table('company_documents')->insert([
            'company_id' => $companyId ?? $this->companyId, 'document_type_id' => $typeId, 'status' => $status, 'file_path' => $path,
            'original_name' => 'seed.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100,
            'rejection_reason' => $status === 'rejected' ? 'unreadable' : null, 'rejection_note' => $status === 'rejected' ? 'Too blurry.' : null,
            'submitted_at' => '2026-10-01 08:00:00', 'reviewed_at' => $status === 'pending' ? null : '2026-10-02 08:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ----- access -----

    public function test_every_document_route_needs_a_signed_in_user(): void
    {
        $this->getJson('/api/v1/company/documents')->assertStatus(401);
        $this->postJson("/api/v1/company/documents/{$this->nib}")->assertStatus(401);
        $this->deleteJson("/api/v1/company/documents/{$this->nib}")->assertStatus(401);
        $this->getJson("/api/v1/company/documents/{$this->nib}/file")->assertStatus(401);
    }

    public function test_a_recruiter_and_a_job_seeker_are_refused(): void
    {
        $this->seedDocument($this->nib, 'pending');
        $seeker = $this->token(UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'en']));

        foreach ([['GET', ''], ['DELETE', "/{$this->nib}"], ['GET', "/{$this->nib}/file"]] as [$method, $suffix]) {
            $this->api($method, '/api/v1/company/documents'.$suffix, $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
            $this->api($method, '/api/v1/company/documents'.$suffix, $seeker)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        }

        $this->upload($this->tax, $this->pdf(), token: $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        $this->upload($this->tax, $this->pdf(), token: $seeker)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        $this->assertNull($this->row($this->tax));
    }

    public function test_a_suspended_company_cannot_use_the_documents(): void
    {
        $this->seedDocument($this->nib, 'pending');
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        foreach ([['GET', ''], ['DELETE', "/{$this->nib}"], ['GET', "/{$this->nib}/file"]] as [$method, $suffix]) {
            $this->api($method, '/api/v1/company/documents'.$suffix)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-suspended');
        }

        $this->upload($this->tax, $this->pdf())->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-suspended');
        $this->assertNull($this->row($this->tax));
        $this->assertNotNull($this->row($this->nib));
    }

    // ----- the list -----

    public function test_the_list_shows_the_active_types_in_order_with_the_names_in_the_request_language(): void
    {
        $response = $this->api('GET', '/api/v1/company/documents', headers: ['Accept-Language' => 'id'])->assertOk();

        $this->assertFalse($response->json('data.verified'));
        $this->assertSame(['nib', 'tax-id', 'deed'], array_column(array_column($response->json('data.documents'), 'type'), 'slug'));
        $this->assertSame(
            ['id' => $this->nib, 'slug' => 'nib', 'name' => 'Nib ID', 'is_required' => true],
            $response->json('data.documents.0.type'),
        );
        $this->assertNull($response->json('data.documents.0.document'));

        $english = $this->api('GET', '/api/v1/company/documents', headers: ['Accept-Language' => 'en'])->assertOk();
        $this->assertSame('Nib', $english->json('data.documents.0.type.name'));
    }

    public function test_the_list_shows_the_documents_of_this_company_only_and_never_the_path(): void
    {
        $this->seedDocument($this->nib, 'approved');
        $this->seedDocument($this->tax, 'rejected');
        $this->seedDocument($this->deed, 'pending', $this->otherCompanyId, 'company/documents/other.pdf');
        DB::table('company_companies')->where('id', $this->companyId)->update(['verified_at' => '2026-10-02 08:00:00']);

        $response = $this->api('GET', '/api/v1/company/documents')->assertOk();

        $this->assertTrue($response->json('data.verified'));
        $this->assertSame([
            'status' => 'approved', 'original_name' => 'seed.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100,
            'submitted_at' => '2026-10-01T08:00:00+00:00', 'reviewed_at' => '2026-10-02T08:00:00+00:00',
        ], $response->json('data.documents.0.document'));
        $this->assertSame(['unreadable', 'Too blurry.'], [
            $response->json('data.documents.1.document.rejection_reason'), $response->json('data.documents.1.document.rejection_note'),
        ]);
        $this->assertNull($response->json('data.documents.2.document'), 'the other company document does not show');
        $this->assertStringNotContainsString('company/documents', (string) $response->getContent());
    }

    public function test_an_inactive_type_is_not_listed_even_when_a_document_exists(): void
    {
        $this->seedDocument($this->retired, 'approved');

        $slugs = array_column(array_column($this->api('GET', '/api/v1/company/documents')->json('data.documents'), 'type'), 'slug');

        $this->assertNotContains('retired', $slugs);
    }

    // ----- submitting -----

    public function test_a_pdf_is_stored_as_uploaded_under_a_random_name_and_waits_for_review(): void
    {
        $pdf = $this->pdf('first');

        $this->upload($this->nib, $pdf, 'NIB PT Maju.pdf')->assertCreated();

        $row = $this->row($this->nib);
        $this->assertMatchesRegularExpression('#^company/documents/[0-9a-f]{32}\.pdf$#', $row->file_path);
        $this->assertSame($pdf, Storage::get($row->file_path));
        $this->assertSame(['pending', 'NIB PT Maju.pdf', 'application/pdf', strlen($pdf)], [$row->status, $row->original_name, $row->mime_type, (int) $row->size_bytes]);
        $this->assertSame('2026-10-05 09:00:00', $row->submitted_at);
        $this->assertNull($row->reviewed_at);
        $this->assertSame([$row->file_path], $this->storedFiles());

        $listed = $this->api('GET', '/api/v1/company/documents')->json('data.documents.0.document');
        $this->assertSame('pending', $listed['status']);
        $this->assertArrayNotHasKey('rejection_reason', $listed);
    }

    public function test_an_image_is_encoded_again_and_a_big_one_is_shrunk_to_the_limit(): void
    {
        $this->upload($this->nib, $this->png(100, 50), 'small.png')->assertCreated();
        $this->upload($this->tax, $this->png(4000, 2000), 'big.png')->assertCreated();

        $small = $this->row($this->nib);
        $big = $this->row($this->tax);

        $this->assertMatchesRegularExpression('#^company/documents/[0-9a-f]{32}\.jpg$#', $small->file_path);
        $this->assertSame('image/jpeg', $small->mime_type);
        $this->assertSame([100, 50], array_slice(getimagesizefromstring((string) Storage::get($small->file_path)), 0, 2), 'never enlarged');
        $this->assertSame([3508, 1754], array_slice(getimagesizefromstring((string) Storage::get($big->file_path)), 0, 2));
        $this->assertSame(strlen((string) Storage::get($big->file_path)), (int) $big->size_bytes);
    }

    public function test_the_client_name_and_type_do_not_decide_what_the_file_is(): void
    {
        $this->upload($this->nib, $this->pdf(), 'looks-like-an-image.png')->assertCreated();
        $this->upload($this->tax, $this->png(), 'looks-like-a-pdf.pdf')->assertCreated();

        $this->assertStringEndsWith('.pdf', $this->row($this->nib)->file_path);
        $this->assertStringEndsWith('.jpg', $this->row($this->tax)->file_path);
    }

    public function test_the_stored_client_name_is_cleaned(): void
    {
        $this->upload($this->nib, $this->pdf(), "../../etc/pass\x00wd.pdf")->assertCreated();
        $this->upload($this->tax, $this->pdf(), 'C:\\Users\\andi\\nib.pdf')->assertCreated();
        $this->upload($this->deed, $this->pdf(), str_repeat('a', 300).'.pdf')->assertCreated();

        $this->assertSame('passwd.pdf', $this->row($this->nib)->original_name);
        $this->assertSame('nib.pdf', $this->row($this->tax)->original_name);
        $this->assertSame(255, mb_strlen($this->row($this->deed)->original_name));
        $this->assertCount(3, $this->storedFiles());
        $this->assertSame([], array_filter($this->storedFiles(), fn (string $path): bool => ! preg_match('#^company/documents/[0-9a-f]{32}\.(pdf|jpg)$#', $path)));
    }

    public function test_a_file_that_is_neither_a_pdf_nor_an_image_is_refused(): void
    {
        $this->upload($this->nib, 'just some text', 'nib.pdf')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-document');
        $this->upload($this->nib, "PK\x03\x04 fake office file", 'nib.docx')->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:invalid-document');
        $this->upload($this->nib, '', 'empty.pdf')->assertStatus(422);

        $this->assertNull($this->row($this->nib));
        $this->assertSame([], $this->storedFiles());
    }

    public function test_the_file_field_is_required(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withToken($this->adminToken)->postJson("/api/v1/company/documents/{$this->nib}")->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_an_unknown_type_is_404_and_an_inactive_type_is_refused(): void
    {
        $this->upload(999999, $this->pdf())->assertNotFound();
        $this->upload($this->retired, $this->pdf())->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:document-type-inactive');

        $this->assertSame(0, DB::table('company_documents')->count());
        $this->assertSame([], $this->storedFiles());
    }

    public function test_the_size_limit_is_a_setting_that_a_company_can_override(): void
    {
        $padded = $this->pdf().str_repeat('x', 101 * 1024);

        $this->upload($this->nib, $padded)->assertCreated();

        $store = $this->app->make(SettingsStore::class);
        $store->setCompanyOverride('company.document_max_size_kb', $this->companyId, 100, null);

        $this->upload($this->tax, $padded)->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:file-too-large')
            ->assertJsonPath('max_size_kb', 100);
        $this->assertNull($this->row($this->tax));
        $this->assertCount(1, $this->storedFiles());

        $otherAdmin = $this->token($this->member($this->otherCompanyId, 'Budi', 'budi@example.test', 'company_admin'));
        $this->upload($this->tax, $padded, token: $otherAdmin)->assertCreated();
        $this->assertNotNull($this->row($this->tax, $this->otherCompanyId));
    }

    public function test_a_global_limit_applies_to_every_company(): void
    {
        $this->app->make(SettingsStore::class)->setGlobal('company.document_max_size_kb', 100, null);

        $this->upload($this->nib, $this->pdf().str_repeat('x', 101 * 1024))->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:file-too-large');
        $this->upload($this->nib, $this->pdf())->assertCreated();
    }

    // ----- replacing -----

    public function test_a_pending_document_is_replaced_and_the_old_file_is_deleted(): void
    {
        $this->upload($this->nib, $this->pdf('first'), 'first.pdf')->assertCreated();
        $first = $this->row($this->nib);

        Carbon::setTestNow('2026-10-05 15:00:00');
        $this->upload($this->nib, $this->pdf('second'), 'second.pdf')->assertNoContent();
        $second = $this->row($this->nib);

        $this->assertSame(1, DB::table('company_documents')->count());
        $this->assertSame($first->id, $second->id);
        $this->assertNotSame($first->file_path, $second->file_path);
        Storage::assertMissing($first->file_path);
        Storage::assertExists($second->file_path);
        $this->assertSame([$second->file_path], $this->storedFiles());
        $this->assertSame(['pending', 'second.pdf', '2026-10-05 15:00:00'], [$second->status, $second->original_name, $second->submitted_at]);
        $this->assertNotSame($first->submitted_at, $second->submitted_at);
    }

    public function test_a_rejected_document_goes_back_to_pending_and_the_reason_is_cleared(): void
    {
        $this->seedDocument($this->nib, 'rejected');

        $this->upload($this->nib, $this->pdf('better'))->assertNoContent();

        $row = $this->row($this->nib);
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->rejection_reason);
        $this->assertNull($row->rejection_note);
        $this->assertNull($row->reviewed_at);
        $this->assertNull($row->reviewed_by_admin_id);
        Storage::assertMissing('company/documents/seed.pdf');
    }

    public function test_replacing_an_approved_document_withdraws_the_badge(): void
    {
        $this->seedDocument($this->nib, 'approved');
        $this->seedDocument($this->tax, 'approved', path: 'company/documents/seed-tax.pdf');
        DB::table('company_companies')->where('id', $this->companyId)->update(['verified_at' => '2026-10-02 08:00:00']);

        $this->upload($this->nib, $this->pdf('new'))->assertNoContent();

        $this->assertSame('pending', $this->row($this->nib)->status);
        $this->assertSame('approved', $this->row($this->tax)->status);
        $this->assertNull(DB::table('company_companies')->where('id', $this->companyId)->value('verified_at'));
        $this->assertFalse($this->api('GET', '/api/v1/company/documents')->json('data.verified'));
    }

    public function test_replacing_a_pending_document_leaves_the_badge_alone(): void
    {
        $this->seedDocument($this->nib, 'approved');
        $this->seedDocument($this->tax, 'approved', path: 'company/documents/seed-tax.pdf');
        $this->seedDocument($this->deed, 'pending', path: 'company/documents/seed-deed.pdf');
        DB::table('company_companies')->where('id', $this->companyId)->update(['verified_at' => '2026-10-02 08:00:00']);

        $this->upload($this->deed, $this->pdf('new'))->assertNoContent();

        $this->assertSame('2026-10-02 08:00:00', DB::table('company_companies')->where('id', $this->companyId)->value('verified_at'));
    }

    public function test_documents_of_two_companies_do_not_touch_each_other(): void
    {
        $otherAdmin = $this->token($this->member($this->otherCompanyId, 'Budi', 'budi@example.test', 'company_admin'));

        $this->upload($this->nib, $this->pdf('mine'))->assertCreated();
        $this->upload($this->nib, $this->pdf('theirs'), token: $otherAdmin)->assertCreated();

        $this->assertSame(2, DB::table('company_documents')->count());
        $this->assertNotSame($this->row($this->nib)->file_path, $this->row($this->nib, $this->otherCompanyId)->file_path);
    }

    // ----- deleting -----

    public function test_a_pending_or_rejected_document_can_be_deleted_with_its_file(): void
    {
        $this->seedDocument($this->nib, 'pending');
        $this->seedDocument($this->tax, 'rejected', path: 'company/documents/seed-tax.pdf');

        $this->api('DELETE', "/api/v1/company/documents/{$this->nib}")->assertNoContent();
        $this->api('DELETE', "/api/v1/company/documents/{$this->tax}")->assertNoContent();

        $this->assertSame(0, DB::table('company_documents')->count());
        $this->assertSame([], $this->storedFiles());
    }

    public function test_an_approved_document_cannot_be_deleted(): void
    {
        $this->seedDocument($this->nib, 'approved');

        $this->api('DELETE', "/api/v1/company/documents/{$this->nib}")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:document-approved');

        $this->assertNotNull($this->row($this->nib));
        Storage::assertExists('company/documents/seed.pdf');
    }

    public function test_deleting_a_missing_document_or_an_unknown_type_is_404(): void
    {
        $this->seedDocument($this->deed, 'pending', $this->otherCompanyId, 'company/documents/other.pdf');

        $this->api('DELETE', "/api/v1/company/documents/{$this->nib}")->assertNotFound();
        $this->api('DELETE', '/api/v1/company/documents/999999')->assertNotFound();
        $this->api('DELETE', "/api/v1/company/documents/{$this->deed}")->assertNotFound();

        $this->assertNotNull($this->row($this->deed, $this->otherCompanyId), 'the other company document is untouched');
        Storage::assertExists('company/documents/other.pdf');
    }

    // ----- the file -----

    public function test_the_own_file_is_sent_as_a_download_with_a_safe_name(): void
    {
        $pdf = $this->pdf('mine');
        $this->upload($this->tax, $pdf, 'secret client name.pdf')->assertCreated();

        $response = $this->api('GET', "/api/v1/company/documents/{$this->tax}/file")->assertOk();

        $this->assertSame($pdf, $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=tax-id.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('secret', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_an_image_document_is_downloaded_with_its_own_extension(): void
    {
        $this->upload($this->nib, $this->png())->assertCreated();

        $response = $this->api('GET', "/api/v1/company/documents/{$this->nib}/file")->assertOk();

        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=nib.jpg', $response->headers->get('Content-Disposition'));
    }

    public function test_nobody_reads_the_file_of_another_company_or_a_missing_one(): void
    {
        $this->seedDocument($this->deed, 'approved', $this->otherCompanyId, 'company/documents/other.pdf');

        $this->api('GET', "/api/v1/company/documents/{$this->deed}/file")->assertNotFound();
        $this->api('GET', "/api/v1/company/documents/{$this->nib}/file")->assertNotFound();
        $this->api('GET', '/api/v1/company/documents/999999/file')->assertNotFound();

        $this->seedDocument($this->nib, 'pending', path: 'company/documents/gone.pdf');
        Storage::delete('company/documents/gone.pdf');
        $this->api('GET', "/api/v1/company/documents/{$this->nib}/file")->assertNotFound();
    }

    // ----- audit -----

    public function test_submitting_and_deleting_are_audited_without_names_or_content(): void
    {
        $this->upload($this->nib, $this->pdf('one'), 'private-name.pdf')->assertCreated();
        $this->upload($this->nib, $this->pdf('two'), 'private-name.pdf')->assertNoContent();
        $this->api('DELETE', "/api/v1/company/documents/{$this->nib}")->assertNoContent();

        $submitted = DB::table('platform_audit_logs')->where('action', 'company.document_submitted')->orderBy('id')->get();
        $deleted = DB::table('platform_audit_logs')->where('action', 'company.document_deleted')->first();

        $this->assertCount(2, $submitted);
        $this->assertSame('user', $submitted[0]->actor_type);
        $this->assertSame('company', $submitted[0]->subject_type);
        $this->assertSame($this->companyId, (int) $submitted[0]->subject_id);
        $this->assertSame(['document_type_id' => $this->nib, 'replaced' => false], json_decode((string) $submitted[0]->metadata, true));
        $this->assertSame(['document_type_id' => $this->nib, 'replaced' => true], json_decode((string) $submitted[1]->metadata, true));
        $this->assertSame(['document_type_id' => $this->nib, 'status' => 'pending'], json_decode((string) $deleted->metadata, true));
        $this->assertStringNotContainsString('private-name', (string) DB::table('platform_audit_logs')->pluck('metadata')->implode(''));
    }

    public function test_refused_requests_leave_no_audit_entry(): void
    {
        $this->seedDocument($this->nib, 'approved');

        $this->upload($this->tax, 'not a document')->assertStatus(422);
        $this->upload(999999, $this->pdf())->assertNotFound();
        $this->upload($this->tax, $this->pdf(), token: $this->recruiterToken)->assertStatus(403);
        $this->api('DELETE', "/api/v1/company/documents/{$this->nib}")->assertStatus(409);
        $this->api('DELETE', "/api/v1/company/documents/{$this->tax}")->assertNotFound();

        $this->assertSame(0, DB::table('platform_audit_logs')->where('action', 'like', 'company.document_%')->count());
    }
}
