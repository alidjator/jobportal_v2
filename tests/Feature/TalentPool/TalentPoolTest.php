<?php

namespace Tests\Feature\TalentPool;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\TalentPool\Application\CreateCategory;
use Src\TalentPool\Application\EraseTalentEntries;
use Src\TalentPool\Application\NewEntries;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class TalentPoolTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    /** @var array{id: int, slug: string, token: string, resume: int} */
    private array $ana;

    /** @var array{id: int, slug: string, token: string, resume: int} */
    private array $budi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->ana = $this->candidate('ana@example.test', 'Ana Putri');
        $this->budi = $this->candidate('budi.c@example.test', 'Budi Candidate');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** The test client with no token; the guards are reset so a user cached by an earlier request is not kept. */
    private function guest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders();
    }

    /**
     * A job seeker who can be found: a complete profile that is switched on for search.
     *
     * @return array{id: int, slug: string, token: string, resume: int}
     */
    private function candidate(string $email, string $name): array
    {
        $id = $this->seeker($email, name: $name);
        $token = $this->tokenFor($id);
        $resume = $this->completeProfile($token);
        DB::table('jobseeker_profiles')->where('user_id', $id)->update(['is_discoverable' => true]);

        return ['id' => $id, 'slug' => (string) DB::table('jobseeker_profiles')->where('user_id', $id)->value('slug'), 'token' => $token, 'resume' => $resume];
    }

    /** @param  array<string, mixed>  $extra */
    private function save(array $candidate, ?string $token = null, array $extra = []): TestResponse
    {
        return $this->api('POST', '/company/talent-pool', ['candidate' => $candidate['slug'], ...$extra], $token ?? $this->recruiterToken);
    }

    private function entryId(array $candidate, ?string $token = null, array $extra = []): int
    {
        return (int) $this->save($candidate, $token, $extra)->assertCreated()->json('data.id');
    }

    private function categoryId(string $name = 'Designers', ?string $token = null): int
    {
        return (int) $this->api('POST', '/company/talent-pool/categories', ['name' => $name], $token ?? $this->recruiterToken)->assertCreated()->json('data.id');
    }

    private function listing(string $query = '', ?string $token = null): TestResponse
    {
        return $this->api('GET', '/company/talent-pool'.$query, token: $token ?? $this->recruiterToken)->assertOk();
    }

    public function test_a_member_saves_a_findable_candidate_and_every_member_of_the_company_sees_them(): void
    {
        $id = $this->entryId($this->ana);

        $row = $this->listing()->json('data.0');

        $this->assertSame($id, $row['id']);
        $this->assertTrue($row['available']);
        $this->assertSame(['slug' => $this->ana['slug'], 'name' => 'Ana Putri', 'has_avatar' => false], $row['candidate']);
        $this->assertNull($row['category']);
        $this->assertNotNull($row['saved_at']);

        // The admin and the other recruiter see the same pool.
        $this->assertSame([$id], $this->listing(token: $this->adminToken)->json('data.*.id'));
        $this->assertSame([$id], $this->listing(token: $this->secondRecruiterToken)->json('data.*.id'));
    }

    public function test_a_candidate_is_saved_once_per_company_whoever_saves_them(): void
    {
        $this->entryId($this->ana);

        $this->save($this->ana, $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-in-talent-pool');
        $this->assertSame(1, DB::table('talentpool_entries')->count());

        // Another company has its own pool.
        $this->save($this->ana, $this->otherAdminToken)->assertCreated();
        $this->assertSame(2, DB::table('talentpool_entries')->count());
    }

    public function test_the_pool_of_a_company_is_never_shown_to_another(): void
    {
        $id = $this->entryId($this->ana);

        $this->assertSame([], $this->listing(token: $this->otherAdminToken)->json('data'));
        $this->api('PATCH', "/company/talent-pool/{$id}", ['category_id' => null], $this->otherAdminToken)->assertNotFound();
        $this->api('DELETE', "/company/talent-pool/{$id}", token: $this->otherAdminToken)->assertNoContent();

        $this->assertSame(1, DB::table('talentpool_entries')->count());
    }

    public function test_a_candidate_who_cannot_be_found_cannot_be_saved(): void
    {
        $this->save(['slug' => 'nobody-here'])->assertNotFound();

        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => false]);
        $this->save($this->ana)->assertNotFound();

        $this->assertSame(0, DB::table('talentpool_entries')->count());
    }

    public function test_a_candidate_who_stops_being_findable_is_shown_without_their_data_and_can_be_removed(): void
    {
        $id = $this->entryId($this->ana);
        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => false]);

        $row = $this->listing()->json('data.0');

        $this->assertSame($id, $row['id']);
        $this->assertFalse($row['available']);
        $this->assertNull($row['candidate']);
        $this->assertStringNotContainsString('Ana', (string) json_encode($row));

        $this->api('DELETE', "/company/talent-pool/{$id}", token: $this->recruiterToken)->assertNoContent();
        $this->assertSame([], $this->listing()->json('data'));
    }

    public function test_a_candidate_who_switches_the_profile_on_again_is_shown_again(): void
    {
        $this->entryId($this->ana);
        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => false]);
        $this->assertFalse($this->listing()->json('data.0.available'));

        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => true]);

        $this->assertTrue($this->listing()->json('data.0.available'));
    }

    public function test_a_candidate_is_saved_in_a_category_moved_and_taken_out_of_it(): void
    {
        $designers = $this->categoryId('Designers');
        $callBack = $this->categoryId('Call back');
        $id = $this->entryId($this->ana, extra: ['category_id' => $designers]);

        $this->assertSame(['id' => $designers, 'name' => 'Designers'], $this->listing()->json('data.0.category'));

        $this->api('PATCH', "/company/talent-pool/{$id}", ['category_id' => $callBack], $this->recruiterToken)->assertOk()->assertJsonPath('data.category.name', 'Call back');
        $this->api('PATCH', "/company/talent-pool/{$id}", ['category_id' => null], $this->recruiterToken)->assertOk()->assertJsonPath('data.category', null);
        $this->api('PATCH', "/company/talent-pool/{$id}", [], $this->recruiterToken)->assertStatus(422)->assertJsonValidationErrors(['category_id']);
    }

    public function test_the_list_can_be_filtered_by_category_and_is_the_most_recently_saved_first(): void
    {
        $designers = $this->categoryId('Designers');
        $first = $this->entryId($this->ana, extra: ['category_id' => $designers]);
        DB::table('talentpool_entries')->where('id', $first)->update(['created_at' => now()->subDay()]);
        $second = $this->entryId($this->budi);

        $this->assertSame([$second, $first], $this->listing()->json('data.*.id'));
        $this->assertSame([$first], $this->listing('?'.http_build_query(['filter' => ['category_id' => $designers]]))->json('data.*.id'));
        $this->api('GET', '/company/talent-pool?'.http_build_query(['filter' => ['unknown' => 1]]), token: $this->recruiterToken)->assertStatus(422);
        $this->assertSame(2, $this->listing('?per_page=1')->json('meta.total'));
    }

    public function test_a_category_of_another_company_is_refused(): void
    {
        $foreign = $this->categoryId('Foreign', $this->otherAdminToken);
        $id = $this->entryId($this->ana);

        $this->save($this->budi, extra: ['category_id' => $foreign])->assertNotFound();
        $this->api('PATCH', "/company/talent-pool/{$id}", ['category_id' => $foreign], $this->recruiterToken)->assertNotFound();
        $this->save($this->budi, extra: ['category_id' => 999_999])->assertNotFound();
    }

    public function test_categories_are_listed_with_their_counts(): void
    {
        $designers = $this->categoryId('Designers');
        $this->categoryId('Backend');
        $this->entryId($this->ana, extra: ['category_id' => $designers]);
        $this->entryId($this->budi, extra: ['category_id' => $designers]);

        $rows = $this->api('GET', '/company/talent-pool/categories', token: $this->recruiterToken)->assertOk()->json('data');

        $this->assertSame([['name' => 'Backend', 'entries' => 0], ['name' => 'Designers', 'entries' => 2]], array_map(static fn (array $row): array => ['name' => $row['name'], 'entries' => $row['entries']], $rows));
    }

    public function test_a_category_name_is_trimmed_and_unique_whatever_its_case(): void
    {
        $this->assertSame('Designers', $this->api('POST', '/company/talent-pool/categories', ['name' => '  Designers  '], $this->recruiterToken)->assertCreated()->json('data.name'));

        $this->api('POST', '/company/talent-pool/categories', ['name' => 'designers'], $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:category-name-taken');
        $this->api('POST', '/company/talent-pool/categories', ['name' => '   '], $this->recruiterToken)->assertStatus(422);
        $this->api('POST', '/company/talent-pool/categories', ['name' => str_repeat('a', 51)], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:category-name-invalid');

        // Another company may use the same name.
        $this->api('POST', '/company/talent-pool/categories', ['name' => 'Designers'], $this->otherAdminToken)->assertCreated();
    }

    public function test_a_category_is_renamed_or_deleted_only_with_the_talent_pool_permission(): void
    {
        $id = $this->categoryId('Designers');
        $limited = $this->memberWith(['applications.view', 'interviews.manage'])['token'];

        $this->api('PATCH', "/company/talent-pool/categories/{$id}", ['name' => 'UX'], $limited)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-permission-required')->assertJsonPath('permission', 'talent_pool.manage');
        $this->api('DELETE', "/company/talent-pool/categories/{$id}", token: $limited)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        $this->api('PATCH', "/company/talent-pool/categories/{$id}", ['name' => 'Visual'], $this->recruiterToken)->assertOk()->assertJsonPath('data.name', 'Visual');

        $this->api('PATCH', "/company/talent-pool/categories/{$id}", ['name' => 'UX'], $this->adminToken)->assertOk()->assertJsonPath('data.name', 'UX');

        $other = $this->categoryId('Backend');
        $this->api('PATCH', "/company/talent-pool/categories/{$other}", ['name' => 'ux'], $this->adminToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:category-name-taken');
        // Keeping its own name in another case is allowed.
        $this->api('PATCH', "/company/talent-pool/categories/{$id}", ['name' => 'Ux'], $this->adminToken)->assertOk();
        $this->api('PATCH', '/company/talent-pool/categories/999999', ['name' => 'Nothing'], $this->adminToken)->assertNotFound();
        $this->api('PATCH', "/company/talent-pool/categories/{$id}", ['name' => 'UX'], $this->otherAdminToken)->assertNotFound();
    }

    public function test_deleting_a_category_keeps_its_candidates_uncategorised(): void
    {
        $id = $this->categoryId('Designers');
        $entry = $this->entryId($this->ana, extra: ['category_id' => $id]);

        $this->api('DELETE', "/company/talent-pool/categories/{$id}", token: $this->adminToken)->assertNoContent();
        $this->api('DELETE', "/company/talent-pool/categories/{$id}", token: $this->adminToken)->assertNoContent();

        $row = $this->listing()->json('data.0');
        $this->assertSame($entry, $row['id']);
        $this->assertNull($row['category']);
    }

    public function test_removing_a_candidate_twice_is_not_an_error(): void
    {
        $id = $this->entryId($this->ana);

        $this->api('DELETE', "/company/talent-pool/{$id}", token: $this->recruiterToken)->assertNoContent();
        $this->api('DELETE', "/company/talent-pool/{$id}", token: $this->recruiterToken)->assertNoContent();

        $this->assertSame(0, DB::table('talentpool_entries')->count());
    }

    public function test_a_company_keeps_at_most_thirty_categories_and_a_thousand_candidates(): void
    {
        for ($index = 1; $index <= CreateCategory::MAX_CATEGORIES; $index++) {
            DB::table('talentpool_categories')->insert(['company_id' => $this->companyId, 'name' => "Category {$index}", 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->api('POST', '/company/talent-pool/categories', ['name' => 'One more'], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:category-limit');

        $rows = [];

        for ($index = 0; $index < NewEntries::MAX_ENTRIES; $index++) {
            $user = $this->seeker("pool{$index}@example.test", name: "Pool {$index}");
            $rows[] = ['company_id' => $this->companyId, 'candidate_user_id' => $user, 'created_at' => now(), 'updated_at' => now()];
        }

        DB::table('talentpool_entries')->insert($rows);

        $this->save($this->ana)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:talent-pool-limit');
        $this->save($this->ana, $this->otherAdminToken)->assertCreated();
    }

    public function test_only_a_member_of_an_active_company_has_a_talent_pool(): void
    {
        $this->guest()->getJson('/api/v1/company/talent-pool')->assertUnauthorized();

        foreach ([['GET', '/company/talent-pool'], ['GET', '/company/talent-pool/categories']] as [$method, $uri]) {
            $this->api($method, $uri, token: $this->ana['token'])->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        }

        $this->save($this->budi, $this->ana['token'])->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');

        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->api('GET', '/company/talent-pool', token: $this->adminToken)->assertForbidden()->assertJsonPath('type', 'urn:onesis:error:company-suspended');
    }

    public function test_the_entries_of_an_anonymised_candidate_are_deleted(): void
    {
        $this->entryId($this->ana);
        $this->save($this->ana, $this->otherAdminToken)->assertCreated();
        $this->entryId($this->budi);

        $this->app->make(EraseTalentEntries::class)->execute($this->ana['id']);
        $this->app->make(EraseTalentEntries::class)->execute($this->ana['id']);

        $this->assertSame(1, DB::table('talentpool_entries')->count());
        $this->assertSame($this->budi['id'], (int) DB::table('talentpool_entries')->value('candidate_user_id'));
    }

    public function test_a_note_is_saved_trimmed_shown_to_every_member_and_cleared_with_null(): void
    {
        $id = $this->entryId($this->ana, extra: ['note' => '  Strong on Laravel, call back in spring.  ']);

        $this->assertSame('Strong on Laravel, call back in spring.', $this->listing()->json('data.0.note'));
        $this->assertSame('Strong on Laravel, call back in spring.', $this->listing(token: $this->adminToken)->json('data.0.note'));

        $this->api('PATCH', "/company/talent-pool/{$id}", ['note' => 'Called on Monday.'], $this->adminToken)->assertOk()->assertJsonPath('data.note', 'Called on Monday.');
        $this->api('PATCH', "/company/talent-pool/{$id}", ['note' => null], $this->recruiterToken)->assertOk()->assertJsonPath('data.note', null);
        $this->assertNull(DB::table('talentpool_entries')->where('id', $id)->value('note'));
    }

    public function test_an_entry_without_a_note_shows_null_and_a_blank_note_is_not_stored(): void
    {
        $this->entryId($this->ana);
        $this->entryId($this->budi, extra: ['note' => '   ']);

        $this->assertSame([null, null], $this->listing()->json('data.*.note'));
    }

    public function test_a_note_is_at_most_five_hundred_characters(): void
    {
        $id = $this->entryId($this->ana, extra: ['note' => str_repeat('a', 500)]);

        $this->save($this->budi, extra: ['note' => str_repeat('a', 501)])->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:note-too-long');
        $this->api('PATCH', "/company/talent-pool/{$id}", ['note' => str_repeat('b', 501)], $this->recruiterToken)->assertStatus(422)->assertJsonPath('type', 'urn:onesis:error:note-too-long');

        $this->assertSame(str_repeat('a', 500), DB::table('talentpool_entries')->where('id', $id)->value('note'));
        $this->assertSame(1, DB::table('talentpool_entries')->count());
    }

    public function test_changing_the_note_keeps_the_category_and_changing_the_category_keeps_the_note(): void
    {
        $designers = $this->categoryId('Designers');
        $callBack = $this->categoryId('Call back');
        $id = $this->entryId($this->ana, extra: ['category_id' => $designers, 'note' => 'First note']);

        $this->api('PATCH', "/company/talent-pool/{$id}", ['note' => 'Second note'], $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.note', 'Second note')->assertJsonPath('data.category.id', $designers);
        $this->api('PATCH', "/company/talent-pool/{$id}", ['category_id' => $callBack], $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.note', 'Second note')->assertJsonPath('data.category.id', $callBack);
        $this->api('PATCH', "/company/talent-pool/{$id}", ['category_id' => null, 'note' => 'Third note'], $this->recruiterToken)->assertOk()
            ->assertJsonPath('data.note', 'Third note')->assertJsonPath('data.category', null);
    }

    public function test_the_note_is_hidden_at_once_while_the_candidate_cannot_be_found(): void
    {
        $this->entryId($this->ana, extra: ['note' => 'Private words about Ana']);
        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => false]);

        $row = $this->listing()->json('data.0');

        $this->assertNull($row['note']);
        $this->assertStringNotContainsString('Private words', (string) json_encode($row));

        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => true]);

        $this->assertSame('Private words about Ana', $this->listing()->json('data.0.note'));
    }

    public function test_the_nightly_clean_up_deletes_the_notes_of_candidates_who_cannot_be_found_and_keeps_the_entries(): void
    {
        $categoryId = $this->categoryId();
        $ana = $this->entryId($this->ana, extra: ['note' => 'Private words about Ana', 'category_id' => $categoryId]);
        $budi = $this->entryId($this->budi, extra: ['note' => 'Still findable']);
        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => false]);

        Artisan::call('talentpool:clear-hidden-notes');

        $this->assertStringContainsString('Cleared 1 note.', Artisan::output());
        $this->assertNull(DB::table('talentpool_entries')->where('id', $ana)->value('note'));
        $this->assertSame($categoryId, (int) DB::table('talentpool_entries')->where('id', $ana)->value('category_id'));
        $this->assertSame('Still findable', DB::table('talentpool_entries')->where('id', $budi)->value('note'));
    }

    public function test_a_candidate_who_is_findable_again_starts_without_the_deleted_note_and_a_second_run_does_nothing(): void
    {
        $id = $this->entryId($this->ana, extra: ['note' => 'Private words about Ana']);
        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => false]);
        Artisan::call('talentpool:clear-hidden-notes');

        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => true]);

        $this->assertNull($this->listing()->json('data.0.note'));
        $this->assertSame($id, $this->listing()->json('data.0.id'));

        Artisan::call('talentpool:clear-hidden-notes');

        $this->assertStringContainsString('Cleared 0 notes.', Artisan::output());
    }

    public function test_the_clean_up_clears_every_hidden_candidates_note(): void
    {
        $other = $this->candidate('citra@example.test', 'Citra Other');
        $this->entryId($this->ana, extra: ['note' => 'Note A']);
        $this->entryId($other, extra: ['note' => 'Note C']);
        DB::table('jobseeker_profiles')->where('user_id', $this->ana['id'])->update(['is_discoverable' => false]);
        DB::table('jobseeker_profiles')->where('user_id', $other['id'])->update(['is_discoverable' => false]);

        Artisan::call('talentpool:clear-hidden-notes');

        $this->assertStringContainsString('Cleared 2 notes.', Artisan::output());
        $this->assertSame(0, DB::table('talentpool_entries')->whereNotNull('note')->count());
    }

    public function test_the_clean_up_is_scheduled_nightly_without_overlapping(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'talentpool:clear-hidden-notes'));

        $this->assertCount(1, $events);
        $this->assertSame('0 4 * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }
}
