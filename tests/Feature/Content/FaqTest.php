<?php

namespace Tests\Feature\Content;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\FeatureTestCase;

final class FaqTest extends FeatureTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $this->token = $this->adminToken(['faqs.manage']);
    }

    /** @param  list<string>  $permissions */
    private function adminToken(array $permissions, string $email = 'admin@example.test'): string
    {
        $admin = AdminModel::query()->create(['name' => 'Admin', 'email' => $email, 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo($permissions);

        return $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    private function asAdmin(string $method, string $uri, array $data = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, "/api/v1/admin/{$uri}", $data);
    }

    /** @return array<string, mixed> */
    private function categoryPayload(array $overrides = []): array
    {
        return ['name' => ['id' => 'Akun', 'en' => 'Account'], 'sort_order' => 1, 'is_active' => true, ...$overrides];
    }

    /** @return array<string, mixed> */
    private function faqPayload(int $categoryId, array $overrides = []): array
    {
        return [
            'category_id' => $categoryId,
            'question' => ['id' => 'Bagaimana cara mendaftar?', 'en' => 'How do I sign up?'],
            'answer' => ['id' => 'Klik **Daftar**.', 'en' => 'Click **Sign up**.'],
            'sort_order' => 1,
            'is_active' => true,
            ...$overrides,
        ];
    }

    private function category(array $overrides = []): int
    {
        return $this->asAdmin('POST', 'faq-categories', $this->categoryPayload($overrides))->assertCreated()->json('data.id');
    }

    private function faq(int $categoryId, array $overrides = []): int
    {
        return $this->asAdmin('POST', 'faqs', $this->faqPayload($categoryId, $overrides))->assertCreated()->json('data.id');
    }

    public function test_management_needs_the_faqs_permission(): void
    {
        $this->getJson('/api/v1/admin/faq-categories')->assertStatus(401);

        $legalOnly = $this->adminToken(['legal_documents.manage'], 'legal@example.test');
        $this->asAdmin('GET', 'faq-categories', token: $legalOnly)->assertStatus(403);
        $this->asAdmin('GET', 'faqs', token: $legalOnly)->assertStatus(403);
    }

    public function test_a_category_is_created_updated_and_listed_in_display_order(): void
    {
        $second = $this->category(['name' => ['id' => 'Lowongan', 'en' => 'Jobs'], 'sort_order' => 2]);
        $first = $this->category();

        $this->asAdmin('PUT', "faq-categories/{$second}", $this->categoryPayload(['name' => ['id' => 'Pekerjaan', 'en' => 'Work'], 'sort_order' => 3, 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('data.name.en', 'Work')
            ->assertJsonPath('data.sort_order', 3)
            ->assertJsonPath('data.is_active', false);

        $list = $this->asAdmin('GET', 'faq-categories')->assertOk();
        $this->assertSame([$first, $second], array_column($list->json('data'), 'id'));
    }

    public function test_a_category_needs_its_name_in_both_languages(): void
    {
        $this->asAdmin('POST', 'faq-categories', ['name' => ['id' => 'Akun']])->assertStatus(422)->assertJsonValidationErrors(['name.en'], 'errors');
        $this->asAdmin('POST', 'faq-categories', $this->categoryPayload(['sort_order' => -1]))->assertStatus(422);
        $this->asAdmin('PUT', 'faq-categories/999999', $this->categoryPayload())->assertStatus(404);
    }

    public function test_a_category_with_questions_cannot_be_deleted_but_an_empty_one_can(): void
    {
        $category = $this->category();
        $faq = $this->faq($category);

        $this->asAdmin('DELETE', "faq-categories/{$category}")
            ->assertStatus(409)
            ->assertJsonPath('type', 'urn:jobportal:error:faq-category-not-empty');

        $this->asAdmin('DELETE', "faqs/{$faq}")->assertNoContent();
        $this->asAdmin('DELETE', "faq-categories/{$category}")->assertNoContent();
        $this->asAdmin('DELETE', "faq-categories/{$category}")->assertStatus(404);
    }

    public function test_a_question_is_created_updated_moved_and_filtered_by_category(): void
    {
        $accounts = $this->category();
        $jobs = $this->category(['name' => ['id' => 'Lowongan', 'en' => 'Jobs'], 'sort_order' => 2]);
        $faq = $this->faq($accounts);
        $this->faq($jobs, ['question' => ['id' => 'Cara melamar?', 'en' => 'How to apply?']]);

        $this->asAdmin('PUT', "faqs/{$faq}", $this->faqPayload($jobs, ['question' => ['id' => 'Pertanyaan baru', 'en' => 'New question'], 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('data.category_id', $jobs)
            ->assertJsonPath('data.question.en', 'New question')
            ->assertJsonPath('data.is_active', false);

        $this->assertCount(2, $this->asAdmin('GET', 'faqs')->json('data'));
        $this->assertCount(2, $this->asAdmin('GET', "faqs?filter[category_id]={$jobs}")->json('data'));
        $this->assertCount(0, $this->asAdmin('GET', "faqs?filter[category_id]={$accounts}")->json('data'));
        $this->asAdmin('GET', 'faqs?filter[text]=x')->assertStatus(422);
    }

    public function test_a_question_needs_an_existing_category_and_both_languages(): void
    {
        $this->asAdmin('POST', 'faqs', $this->faqPayload(999999))
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:jobportal:error:faq-category-not-found');

        $category = $this->category();
        $this->asAdmin('POST', 'faqs', $this->faqPayload($category, ['answer' => ['id' => 'Hanya Indonesia']]))->assertStatus(422)->assertJsonValidationErrors(['answer.en'], 'errors');

        $faq = $this->faq($category);
        $this->asAdmin('PUT', "faqs/{$faq}", $this->faqPayload(999999))->assertStatus(422)->assertJsonPath('type', 'urn:jobportal:error:faq-category-not-found');
        $this->asAdmin('PUT', 'faqs/999999', $this->faqPayload($category))->assertStatus(404);
        $this->asAdmin('DELETE', 'faqs/999999')->assertStatus(404);
    }

    public function test_the_public_sees_active_categories_and_questions_in_order_in_the_request_language(): void
    {
        $accounts = $this->category();
        $jobs = $this->category(['name' => ['id' => 'Lowongan', 'en' => 'Jobs'], 'sort_order' => 2]);
        $hidden = $this->category(['name' => ['id' => 'Tersembunyi', 'en' => 'Hidden'], 'sort_order' => 3, 'is_active' => false]);
        $empty = $this->category(['name' => ['id' => 'Kosong', 'en' => 'Empty'], 'sort_order' => 4]);
        $onlyInactive = $this->category(['name' => ['id' => 'Nonaktif', 'en' => 'Inactive questions'], 'sort_order' => 5]);

        $this->faq($accounts, ['sort_order' => 2, 'question' => ['id' => 'Kedua', 'en' => 'Second']]);
        $this->faq($accounts, ['sort_order' => 1, 'question' => ['id' => 'Pertama', 'en' => 'First']]);
        $this->faq($accounts, ['sort_order' => 3, 'is_active' => false, 'question' => ['id' => 'Disembunyikan', 'en' => 'Hidden question']]);
        $this->faq($jobs);
        $this->faq($hidden);
        $this->faq($onlyInactive, ['is_active' => false]);

        $response = $this->getJson('/api/v1/faq', ['Accept-Language' => 'en'])->assertOk();
        $data = $response->json('data');

        $this->assertSame(['Account', 'Jobs'], array_column($data, 'name'));
        $this->assertSame(['First', 'Second'], array_column($data[0]['faqs'], 'question'));
        $this->assertSame('Click **Sign up**.', $data[0]['faqs'][0]['answer']);
        $this->assertNotContains($empty, array_column($data, 'id'));

        $this->getJson('/api/v1/faq', ['Accept-Language' => 'id'])->assertOk()->assertJsonPath('data.0.name', 'Akun')->assertJsonPath('data.0.faqs.0.question', 'Pertama');
    }

    public function test_the_public_faq_is_empty_without_content_and_needs_no_login(): void
    {
        $this->getJson('/api/v1/faq')->assertOk()->assertExactJson(['data' => []]);
        $this->assertSame(0, DB::table('content_faqs')->count());
    }
}
