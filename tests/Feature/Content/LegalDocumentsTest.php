<?php

namespace Tests\Feature\Content;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Content\Domain\Events\LegalDocumentPublished;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Identity\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\FeatureTestCase;

final class LegalDocumentsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-03 10:00:00');
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions = ['legal_documents.manage'], string $email = 'admin@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Admin', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'type' => 'terms',
            'title' => ['id' => 'Syarat dan Ketentuan', 'en' => 'Terms and Conditions'],
            'body' => ['id' => '# Syarat', 'en' => '# Terms'],
            ...$overrides,
        ];
    }

    private function create(string $token, array $overrides = []): int
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/v1/admin/legal-documents', $this->payload($overrides))->assertCreated()->json('data.id');
    }

    private function publish(string $token, int $id): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson("/api/v1/admin/legal-documents/{$id}/publish");
    }

    public function test_access_needs_an_admin_with_the_permission(): void
    {
        $this->getJson('/api/v1/admin/legal-documents')->assertStatus(401);

        $user = UserModel::query()->create(['type' => 'job_seeker', 'name' => 'Siti', 'email' => 'siti@example.test', 'status' => 'active', 'locale' => 'id']);
        $this->withToken($user->createToken('phone', ['*'], now()->addDay())->plainTextToken)->getJson('/api/v1/admin/legal-documents')->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->adminToken(['faqs.manage'], 'faq@example.test'))->getJson('/api/v1/admin/legal-documents')
            ->assertStatus(403)
            ->assertJsonPath('type', 'urn:onesis:error:forbidden');
    }

    public function test_drafts_get_consecutive_versions_per_type(): void
    {
        $token = $this->adminToken();

        $this->postJsonAs($token, $this->payload())->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title.en', 'Terms and Conditions')
            ->assertJsonPath('data.published_at', null);
        $this->postJsonAs($token, $this->payload())->assertCreated()->assertJsonPath('data.version', 2);
        $this->postJsonAs($token, $this->payload(['type' => 'privacy']))->assertCreated()->assertJsonPath('data.version', 1);
    }

    private function postJsonAs(string $token, array $payload): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/v1/admin/legal-documents', $payload);
    }

    public function test_a_draft_can_be_edited_even_when_incomplete_but_not_published_then(): void
    {
        $token = $this->adminToken();
        $id = $this->create($token);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->putJson("/api/v1/admin/legal-documents/{$id}", ['title' => ['id' => 'Judul'], 'body' => ['id' => 'Isi']])
            ->assertOk()
            ->assertJsonPath('data.title', ['id' => 'Judul', 'en' => '']);

        $this->publish($token, $id)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:legal-document-incomplete');
    }

    public function test_publishing_locks_the_document_and_replaces_the_active_version(): void
    {
        Event::fake([LegalDocumentPublished::class]);
        $token = $this->adminToken();
        $first = $this->create($token);
        $second = $this->create($token);

        $this->publish($token, $first)->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.published_at', '2026-10-03T10:00:00+00:00');
        Event::assertDispatched(LegalDocumentPublished::class, fn (LegalDocumentPublished $e) => $e->documentId === $first && $e->version === 1);

        $this->publish($token, $second)->assertOk();

        $statuses = DB::table('content_legal_documents')->where('type', 'terms')->orderBy('version')->pluck('status')->all();
        $this->assertSame(['archived', 'published'], $statuses);

        $this->publish($token, $second)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:legal-document-not-draft');

        foreach ([$first, $second] as $id) {
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->putJson("/api/v1/admin/legal-documents/{$id}", $this->payload())->assertStatus(409);
            $this->app['auth']->forgetGuards();
            $this->withToken($token)->deleteJson("/api/v1/admin/legal-documents/{$id}")->assertStatus(409);
        }
    }

    public function test_publishing_one_type_does_not_touch_the_other(): void
    {
        $token = $this->adminToken();
        $terms = $this->create($token);
        $privacy = $this->create($token, ['type' => 'privacy']);

        $this->publish($token, $terms)->assertOk();
        $this->publish($token, $privacy)->assertOk();

        $this->assertSame(2, DB::table('content_legal_documents')->where('status', 'published')->count());
    }

    public function test_a_draft_can_be_deleted_and_the_next_version_number_follows_the_highest(): void
    {
        $token = $this->adminToken();
        $first = $this->create($token);
        $second = $this->create($token);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->deleteJson("/api/v1/admin/legal-documents/{$second}")->assertNoContent();
        $this->assertSame(1, DB::table('content_legal_documents')->where('id', $first)->value('version'));
        $this->assertFalse(DB::table('content_legal_documents')->where('id', $second)->exists());

        $this->postJsonAs($token, $this->payload())->assertCreated()->assertJsonPath('data.version', 2);
    }

    public function test_the_list_shows_every_version_newest_first_and_accepts_only_the_type_filter(): void
    {
        $token = $this->adminToken();
        $this->create($token);
        $this->create($token);
        $this->create($token, ['type' => 'privacy']);

        $this->app['auth']->forgetGuards();
        $all = $this->withToken($token)->getJson('/api/v1/admin/legal-documents')->assertOk();
        $this->assertCount(3, $all->json('data'));

        $this->app['auth']->forgetGuards();
        $terms = $this->withToken($token)->getJson('/api/v1/admin/legal-documents?filter[type]=terms')->assertOk();
        $this->assertSame([2, 1], array_column($terms->json('data'), 'version'));

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/admin/legal-documents?filter[type]=cookies')->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/admin/legal-documents?filter[status]=draft')->assertStatus(422);
    }

    public function test_one_document_can_be_read_and_an_unknown_one_is_a_404(): void
    {
        $token = $this->adminToken();
        $id = $this->create($token);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson("/api/v1/admin/legal-documents/{$id}")->assertOk()->assertJsonPath('data.id', $id);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/admin/legal-documents/999999')->assertStatus(404)->assertJsonPath('type', 'urn:onesis:error:not-found');
        $this->publish($token, 999999)->assertStatus(404);
    }

    public function test_the_request_is_validated(): void
    {
        $token = $this->adminToken();

        $this->postJsonAs($token, $this->payload(['type' => 'cookies']))->assertStatus(422)->assertJsonValidationErrors(['type'], 'errors');
        $this->postJsonAs($token, ['type' => 'terms', 'title' => 'text', 'body' => []])->assertStatus(422)->assertJsonValidationErrors(['title'], 'errors');
    }

    public function test_the_public_reads_the_active_version_in_the_request_language(): void
    {
        $token = $this->adminToken();
        $this->publish($token, $this->create($token))->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/legal-documents/terms', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'terms', 'version' => 1, 'title' => 'Terms and Conditions', 'body' => '# Terms', 'published_at' => '2026-10-03T10:00:00+00:00']]);

        $this->getJson('/api/v1/legal-documents/terms', ['Accept-Language' => 'id'])->assertOk()->assertJsonPath('data.title', 'Syarat dan Ketentuan');
    }

    public function test_a_missing_translation_falls_back_to_the_other_language(): void
    {
        DB::table('content_legal_documents')->insert([
            'type' => 'privacy', 'version' => 1, 'title' => json_encode(['id' => 'Privasi', 'en' => '']), 'body' => json_encode(['id' => 'Isi', 'en' => '']),
            'status' => 'published', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/legal-documents/privacy', ['Accept-Language' => 'en'])->assertOk()->assertJsonPath('data.title', 'Privasi');
    }

    public function test_the_public_cannot_see_drafts_and_gets_a_404_without_a_published_version_or_for_an_unknown_type(): void
    {
        $token = $this->adminToken();
        $this->create($token);

        $this->getJson('/api/v1/legal-documents/terms')->assertStatus(404);
        $this->getJson('/api/v1/legal-documents/terms/versions/1')->assertStatus(404);
        $this->getJson('/api/v1/legal-documents/cookies')->assertStatus(404)->assertJsonPath('type', 'urn:onesis:error:not-found');
    }

    public function test_a_specific_published_or_archived_version_can_be_read(): void
    {
        $token = $this->adminToken();
        $first = $this->create($token, ['title' => ['id' => 'Versi satu', 'en' => 'Version one']]);
        $second = $this->create($token, ['title' => ['id' => 'Versi dua', 'en' => 'Version two']]);
        $this->publish($token, $first)->assertOk();
        $this->publish($token, $second)->assertOk();

        $this->getJson('/api/v1/legal-documents/terms/versions/1', ['Accept-Language' => 'en'])->assertOk()->assertJsonPath('data.title', 'Version one');
        $this->getJson('/api/v1/legal-documents/terms/versions/2', ['Accept-Language' => 'en'])->assertOk()->assertJsonPath('data.title', 'Version two');
        $this->getJson('/api/v1/legal-documents/terms')->assertOk()->assertJsonPath('data.version', 2);
        $this->getJson('/api/v1/legal-documents/terms/versions/3')->assertStatus(404);
        $this->getJson('/api/v1/legal-documents/terms/versions/abc')->assertStatus(404);
    }
}
