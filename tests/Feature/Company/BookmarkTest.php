<?php

namespace Tests\Feature\Company;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class BookmarkTest extends CompanyTestCase
{
    private function category(int $company, string $name = 'Cat'): int
    {
        return DB::table('company_bookmark_categories')->insertGetId(['company_id' => $company, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_requires_company_auth(): void
    {
        $this->getJson('/api/company/bookmark/categories')->assertUnauthorized();
        $this->postJson('/api/company/bookmark/candidate', [])->assertUnauthorized();
        $this->getJson('/api/company/bookmark/candidates')->assertUnauthorized();
        Sanctum::actingAs($this->makeCandidate()['user']);
        $this->getJson('/api/company/bookmark/categories')->assertUnauthorized();
    }

    public function test_category_crud(): void
    {
        $made = $this->actingAsCompany();

        $created = $this->postJson('/api/company/bookmark/categories', ['name' => 'Frontend'])->assertOk()
            ->assertJsonPath('data.message', 'Category created successfully')
            ->assertJsonPath('data.category.name', 'Frontend')
            ->assertJsonPath('data.category.company_id', $made['company'])->json('data.category');

        $this->getJson('/api/company/bookmark/categories')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/company/bookmark/categories/{$created['id']}/edit")->assertOk()->assertJsonPath('data.name', 'Frontend');

        $this->putJson("/api/company/bookmark/categories/{$created['id']}", ['name' => 'Backend'])->assertOk()
            ->assertJsonPath('data.category.name', 'Backend')->assertJsonPath('data.message', 'Category updated successfully');

        $this->deleteJson("/api/company/bookmark/categories/{$created['id']}")->assertOk()
            ->assertExactJson(['success' => 'Category deleted successfully']);
        $this->assertDatabaseMissing('company_bookmark_categories', ['id' => $created['id']]);
    }

    public function test_category_validation(): void
    {
        $this->actingAsCompany();
        $this->postJson('/api/company/bookmark/categories', [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/company/bookmark/categories', ['name' => 'a'])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_category_ownership_is_enforced(): void
    {
        $other = $this->makeCompany();
        $foreign = $this->category($other['company'], 'Secret');
        $this->actingAsCompany();

        $this->getJson("/api/company/bookmark/categories/{$foreign}/edit")->assertNotFound();
        $this->putJson("/api/company/bookmark/categories/{$foreign}", ['name' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/company/bookmark/categories/{$foreign}")->assertNotFound();
        $this->getJson('/api/company/bookmark/categories/999999999/edit')->assertNotFound();

        $this->assertDatabaseHas('company_bookmark_categories', ['id' => $foreign, 'name' => 'Secret']);
    }

    public function test_bookmark_candidate_toggles_and_notifies(): void
    {
        $made = $this->actingAsCompany([], ['name' => 'Acme']);
        $cand = $this->makeCandidate(['shortlisted_alert' => 1]);
        $cat = $this->category($made['company']);

        $this->postJson('/api/company/bookmark/candidate', ['username' => $cand['user']->username, 'category_id' => $cat])
            ->assertOk()->assertJsonPath('data.success', 'Candidate added to bookmark list');

        $this->assertDatabaseHas('bookmark_company', ['company_id' => $made['company'], 'candidate_id' => $cand['candidate'], 'category_id' => $cat]);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $cand['user']->id)->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $made['user']->id)->count());
        $this->assertStringContainsString('Acme has bookmarked you', DB::table('notifications')->where('notifiable_id', $cand['user']->id)->value('data'));

        $this->postJson('/api/company/bookmark/candidate', ['username' => $cand['user']->username])
            ->assertOk()->assertJsonPath('data.success', 'Candidate removed from bookmark list');
        $this->assertDatabaseMissing('bookmark_company', ['company_id' => $made['company'], 'candidate_id' => $cand['candidate']]);
    }

    public function test_bookmark_candidate_validation_and_errors(): void
    {
        $made = $this->actingAsCompany();
        $cand = $this->makeCandidate();

        $this->postJson('/api/company/bookmark/candidate', [])->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->postJson('/api/company/bookmark/candidate', ['username' => $cand['user']->username, 'category_id' => 'abc'])->assertUnprocessable();
        $this->postJson('/api/company/bookmark/candidate', ['username' => 'nobody-'.uniqid()])->assertNotFound()->assertJsonStructure(['error']);
        // user bukan kandidat (perusahaan) -> 404, bukan 500
        $this->postJson('/api/company/bookmark/candidate', ['username' => $made['user']->username])->assertNotFound();

        $foreignCat = $this->category($this->makeCompany()['company']);
        $this->postJson('/api/company/bookmark/candidate', ['username' => $cand['user']->username, 'category_id' => $foreignCat])->assertNotFound();
    }

    public function test_cv_view_limit_is_enforced_and_consumed(): void
    {
        $made = $this->actingAsCompany();
        $plan = DB::table('plans')->insertGetId(['label' => 'P'.uniqid(), 'price' => 1, 'job_limit' => 1, 'featured_job_limit' => 0, 'highlight_job_limit' => 0, 'candidate_cv_view_limit' => 1, 'recommended' => 0, 'frontend_show' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_plans')->insert(['company_id' => $made['company'], 'plan_id' => $plan, 'candidate_cv_view_limit' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $cat = $this->category($made['company']);
        $one = $this->makeCandidate();
        $two = $this->makeCandidate();

        $this->postJson('/api/company/bookmark/candidate', ['username' => $one['user']->username, 'category_id' => $cat])->assertOk();
        $this->assertSame(0, (int) DB::table('user_plans')->where('company_id', $made['company'])->value('candidate_cv_view_limit'));

        $this->postJson('/api/company/bookmark/candidate', ['username' => $two['user']->username, 'category_id' => $cat])
            ->assertStatus(400)->assertJsonStructure(['error']);
        $this->assertDatabaseMissing('bookmark_company', ['company_id' => $made['company'], 'candidate_id' => $two['candidate']]);
    }

    public function test_list_bookmarked_candidates_with_category_filter(): void
    {
        $made = $this->actingAsCompany();
        $cat = $this->category($made['company']);
        $a = $this->makeCandidate();
        $b = $this->makeCandidate();
        DB::table('bookmark_company')->insert([
            ['company_id' => $made['company'], 'candidate_id' => $a['candidate'], 'category_id' => $cat, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $made['company'], 'candidate_id' => $b['candidate'], 'category_id' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $all = $this->getJson('/api/company/bookmark/candidates')->assertOk()->json('data');
        $this->assertSame(2, $all['total']);
        $this->assertSame($a['user']->username, $all['data'][0]['user']['username']);
        $this->assertSame($cat, $all['data'][0]['pivot']['category_id']);
        $this->assertArrayNotHasKey('password', $all['data'][0]['user']);

        $this->getJson('/api/company/bookmark/candidates?category=all')->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson("/api/company/bookmark/candidates?category={$cat}")->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/company/bookmark/candidates?category=zzz')->assertUnprocessable();
    }
}
