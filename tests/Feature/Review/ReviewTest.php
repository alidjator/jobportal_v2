<?php

namespace Tests\Feature\Review;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Review\Application\EraseReviewerData;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class ReviewTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private string $moderatorToken;

    private int $moderatorId;

    private int $strangerId;

    private string $strangerToken;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');

        $moderator = AdminModel::query()->create(['name' => 'Moderator', 'email' => 'mod@example.test', 'password' => 'hash', 'status' => 'active']);
        $moderator->givePermissionTo(['reviews.moderate']);
        $this->moderatorId = (int) $moderator->id;
        $this->moderatorToken = $moderator->createToken('console', ['*'], now()->addDays(30))->plainTextToken;

        $this->strangerId = $this->seeker('stranger@example.test', name: 'Stranger');
        $this->strangerToken = $this->tokenFor($this->strangerId);

        // The seeker applied to the company, so they may review it.
        $this->apply($this->liveJob())->assertCreated();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $overrides */
    private function body(array $overrides = []): array
    {
        return [
            'rating' => 4, 'culture_rating' => 5, 'pay_rating' => 3, 'hiring_rating' => null,
            'title' => 'A good place to grow',
            'body' => 'The team was kind, the interviews were clear and I learned a lot during the first months there.',
            ...$overrides,
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    private function write(array $overrides = [], ?string $token = null, string $slug = 'pt-maju'): TestResponse
    {
        return $this->api('POST', "/companies/{$slug}/reviews", $this->body($overrides), $token ?? $this->seekerToken);
    }

    private function writeId(array $overrides = []): int
    {
        return (int) $this->write($overrides)->assertCreated()->json('data.id');
    }

    private function admin(string $method, string $uri, array $body = []): TestResponse
    {
        return $this->api($method, "/admin{$uri}", $body, $this->moderatorToken);
    }

    private function approve(int $id): void
    {
        $this->admin('POST', "/reviews/{$id}/approve")->assertOk();
    }

    private function publishedId(array $overrides = []): int
    {
        $id = $this->writeId($overrides);
        $this->approve($id);

        return $id;
    }

    /** A published review of another person, inserted directly. */
    private function otherReview(int $rating = 4, ?int $culture = null, ?int $company = null, string $status = 'published'): int
    {
        $this->counter++;
        $user = $this->seeker("reviewer{$this->counter}@example.test", name: "Reviewer {$this->counter}");

        return (int) DB::table('review_reviews')->insertGetId([
            'company_id' => $company ?? $this->companyId, 'user_id' => $user, 'rating' => $rating, 'culture_rating' => $culture, 'title' => "Title {$this->counter}",
            'body' => 'Some words about the company that are long enough to be a real review of it.', 'status' => $status,
            'published_at' => $status === 'published' ? now()->subDays($this->counter) : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function publicList(string $query = '', string $slug = 'pt-maju'): TestResponse
    {
        return $this->api('GET', "/companies/{$slug}/reviews{$query}", [], $this->strangerToken);
    }

    // ----- writing -----

    public function test_an_applicant_writes_a_review_that_waits_for_an_admin(): void
    {
        $this->write()->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.aspects.culture', 5)
            ->assertJsonPath('data.aspects.hiring', null)
            ->assertJsonPath('data.company_id', $this->companyId);

        $this->assertSame(0, $this->publicList()->assertOk()->json('summary.count'));
        $this->publicList()->assertJsonCount(0, 'data');
    }

    public function test_only_someone_who_applied_to_the_company_may_review_it(): void
    {
        $this->write([], $this->strangerToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:review-requires-application');
        $this->write([], $this->adminToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
    }

    public function test_a_withdrawn_application_still_counts(): void
    {
        DB::table('recruitment_applications')->where('user_id', $this->seekerId)->update(['stage_type' => 'withdrawn']);

        $this->write()->assertCreated();
    }

    public function test_a_hidden_company_cannot_be_reviewed_or_listed(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['is_visible' => false]);

        $this->write()->assertNotFound();
        $this->publicList()->assertNotFound();
        $this->publicList('', 'no-such-company')->assertNotFound();
    }

    public function test_a_company_is_reviewed_once_per_person(): void
    {
        $this->write()->assertCreated();

        $this->write()->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-reviewed');
        $this->assertSame(1, DB::table('review_reviews')->count());
    }

    public function test_the_fields_are_validated(): void
    {
        $this->write(['rating' => 6])->assertStatus(422)->assertJsonValidationErrors(['rating'], 'errors');
        $this->write(['rating' => null])->assertStatus(422)->assertJsonValidationErrors(['rating'], 'errors');
        $this->write(['pay_rating' => 0])->assertStatus(422)->assertJsonValidationErrors(['pay_rating'], 'errors');
        $this->write(['title' => 'ab'])->assertStatus(422)->assertJsonValidationErrors(['title'], 'errors');
        $this->write(['body' => 'too short'])->assertStatus(422)->assertJsonValidationErrors(['body'], 'errors');
        $this->write(['body' => str_repeat('a', 3001)])->assertStatus(422)->assertJsonValidationErrors(['body'], 'errors');
    }

    // ----- the author's own reviews -----

    public function test_an_author_edits_and_a_published_review_goes_back_to_the_queue(): void
    {
        $id = $this->publishedId();
        $this->assertSame(1, $this->publicList()->json('summary.count'));

        $this->api('PUT', "/me/reviews/{$id}", $this->body(['rating' => 2, 'title' => 'Changed my mind']), $this->seekerToken)->assertOk()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.rating', 2)->assertJsonPath('data.published_at', null);

        $this->assertSame(0, $this->publicList()->json('summary.count'));
        $this->publicList()->assertJsonCount(0, 'data');
    }

    public function test_a_rejected_review_can_be_edited_and_sent_again_but_a_removed_one_cannot(): void
    {
        $id = $this->writeId();
        $this->admin('POST', "/reviews/{$id}/reject", ['reason' => 'personal-data', 'note' => 'Remove the phone number.'])->assertOk();

        $this->api('GET', "/me/reviews/{$id}", [], $this->seekerToken)->assertOk()
            ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.rejection_reason', 'personal-data')->assertJsonPath('data.rejection_note', 'Remove the phone number.');

        $this->api('PUT', "/me/reviews/{$id}", $this->body(), $this->seekerToken)->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.rejection_reason', null);

        $this->approve($id);
        $this->admin('POST', "/reviews/{$id}/remove", ['reason' => 'false-claim'])->assertOk();

        $this->api('PUT', "/me/reviews/{$id}", $this->body(), $this->seekerToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:review-not-editable');
    }

    public function test_an_author_deletes_their_review_with_its_reply(): void
    {
        $id = $this->publishedId();
        $this->api('PUT', "/company/reviews/{$id}/reply", ['body' => 'Thank you for the kind words.'])->assertOk();

        $this->api('DELETE', "/me/reviews/{$id}", [], $this->seekerToken)->assertNoContent();

        $this->assertSame(0, DB::table('review_reviews')->count());
        $this->assertSame(0, DB::table('review_replies')->count());
    }

    public function test_nobody_reads_edits_or_deletes_someone_elses_review(): void
    {
        $id = $this->writeId();

        $this->api('GET', "/me/reviews/{$id}", [], $this->strangerToken)->assertNotFound();
        $this->api('PUT', "/me/reviews/{$id}", $this->body(), $this->strangerToken)->assertNotFound();
        $this->api('DELETE', "/me/reviews/{$id}", [], $this->strangerToken)->assertNotFound();
        $this->api('GET', '/me/reviews', [], $this->adminToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:job-seeker-only');
    }

    public function test_the_list_of_my_reviews_shows_every_state(): void
    {
        $this->writeId();

        $this->api('GET', '/me/reviews', [], $this->seekerToken)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'pending');
        $this->api('GET', '/me/reviews', [], $this->strangerToken)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_writes_are_limited_per_account(): void
    {
        $id = $this->writeId();

        for ($i = 0; $i < 9; $i++) {
            $this->api('PUT', "/me/reviews/{$id}", $this->body(), $this->seekerToken)->assertOk();
        }

        $this->api('PUT', "/me/reviews/{$id}", $this->body(), $this->seekerToken)->assertStatus(429);
    }

    // ----- the public list -----

    public function test_the_public_list_shows_published_reviews_with_the_authors_name_and_the_published_reply(): void
    {
        $id = $this->publishedId();
        $this->otherReview(status: 'pending');
        $this->api('PUT', "/company/reviews/{$id}/reply", ['body' => 'Thank you for the kind words.'])->assertOk();

        $this->publicList()->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.author.name', 'Siti Rahma')
            ->assertJsonPath('data.0.title', 'A good place to grow')
            ->assertJsonPath('data.0.reply', null)
            ->assertJsonMissingPath('data.0.status')
            ->assertJsonPath('meta.total', 1);

        $this->admin('POST', "/review-replies/{$id}/approve")->assertNoContent();

        $this->publicList()->assertJsonPath('data.0.reply.body', 'Thank you for the kind words.');
    }

    public function test_the_list_is_sorted_and_the_page_size_is_capped(): void
    {
        $low = $this->otherReview(2);
        $high = $this->otherReview(5);
        $mid = $this->otherReview(3);

        $this->assertSame([$low, $high, $mid], array_map('intval', array_column($this->publicList('?sort=newest')->json('data'), 'id')));
        $this->assertSame([$high, $mid, $low], array_map('intval', array_column($this->publicList('?sort=highest')->json('data'), 'id')));
        $this->assertSame([$low, $mid, $high], array_map('intval', array_column($this->publicList('?sort=lowest')->json('data'), 'id')));

        $this->publicList('?sort=best')->assertStatus(422)->assertJsonValidationErrors(['sort'], 'errors');
        $this->publicList('?per_page=51')->assertStatus(422)->assertJsonValidationErrors(['per_page'], 'errors');
    }

    public function test_the_public_list_needs_no_token_and_is_cacheable(): void
    {
        $this->otherReview();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $response = $this->getJson('/api/v1/companies/pt-maju/reviews')->assertOk();

        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertNotNull($response->headers->get('ETag'));
    }

    public function test_an_erased_author_reads_as_deleted_user(): void
    {
        $id = $this->otherReview();
        $userId = (int) DB::table('review_reviews')->where('id', $id)->value('user_id');
        DB::table('identity_users')->where('id', $userId)->update(['anonymized_at' => now()]);

        $this->publicList()->assertJsonPath('data.0.author.name', 'Deleted user');
    }

    // ----- the summary -----

    public function test_the_averages_appear_from_three_reviews_and_aspects_need_three_ratings(): void
    {
        $this->otherReview(5, 4);
        $this->otherReview(4, 2);

        $this->publicList()->assertJsonPath('summary.count', 2)->assertJsonPath('summary.average', null)->assertJsonPath('summary.aspects.culture', null);

        $this->otherReview(3);
        $this->app['cache']->flush();

        $this->publicList()->assertJsonPath('summary.count', 3)->assertJsonPath('summary.average', 4)->assertJsonPath('summary.aspects.culture', null);

        $this->otherReview(4, 3);
        $this->app['cache']->flush();

        $this->publicList()->assertJsonPath('summary.count', 4)->assertJsonPath('summary.average', 4)->assertJsonPath('summary.aspects.culture', 3)->assertJsonPath('summary.aspects.pay', null);
    }

    public function test_the_summary_follows_publishing_and_taking_down(): void
    {
        $this->otherReview();
        $this->otherReview();
        $id = $this->writeId(['rating' => 1]);
        $this->publicList()->assertJsonPath('summary.count', 2);

        $this->approve($id);
        $this->publicList()->assertJsonPath('summary.count', 3)->assertJsonPath('summary.average', 3);

        $this->admin('POST', "/reviews/{$id}/remove", ['reason' => 'abusive'])->assertOk();
        $this->publicList()->assertJsonPath('summary.count', 2);
    }

    // ----- moderation -----

    public function test_publishing_tells_the_author_and_the_company_admins_and_is_audited(): void
    {
        $id = $this->writeId();

        $this->admin('POST', "/reviews/{$id}/approve")->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.author.name', 'Siti Rahma');

        $author = DB::table('notifications')->where('notifiable_id', $this->seekerId)->where('type', 'review.published')->first();
        $this->assertNotNull($author);
        $this->assertSame($id, json_decode($author->data, true)['review_id']);
        $this->assertNotNull(DB::table('notifications')->where('notifiable_id', $this->adminId)->where('type', 'review.received')->first());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->recruiterId)->count());

        $audit = DB::table('platform_audit_logs')->where('action', 'review.approved')->first();
        $this->assertSame($id, (int) $audit->subject_id);
        $this->assertSame($this->moderatorId, (int) $audit->actor_id);
    }

    public function test_rejecting_needs_a_reason_and_tells_the_author(): void
    {
        $id = $this->writeId();

        $this->admin('POST', "/reviews/{$id}/reject", [])->assertStatus(422)->assertJsonValidationErrors(['reason'], 'errors');
        $this->admin('POST', "/reviews/{$id}/reject", ['reason' => 'rude'])->assertStatus(422);
        $this->admin('POST', "/reviews/{$id}/reject", ['reason' => 'spam'])->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.reason', 'spam');

        $notification = DB::table('notifications')->where('notifiable_id', $this->seekerId)->where('type', 'review.rejected')->first();
        $this->assertSame('spam', json_decode($notification->data, true)['reason']);
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'review.rejected')->count());
    }

    public function test_a_decision_needs_the_right_state(): void
    {
        $id = $this->publishedId();

        $this->admin('POST', "/reviews/{$id}/approve")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:review-not-pending');
        $this->admin('POST', "/reviews/{$id}/reject", ['reason' => 'spam'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:review-not-pending');

        $pending = $this->otherReview(status: 'pending');
        $this->admin('POST', "/reviews/{$pending}/remove", ['reason' => 'spam'])->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:review-not-published');
        $this->admin('POST', '/reviews/999999/approve')->assertNotFound();
    }

    public function test_only_an_admin_with_the_permission_moderates(): void
    {
        $id = $this->writeId();
        $other = AdminModel::query()->create(['name' => 'No rights', 'email' => 'none@example.test', 'password' => 'hash', 'status' => 'active']);
        $token = $other->createToken('console', ['*'], now()->addDays(30))->plainTextToken;

        $this->api('POST', "/admin/reviews/{$id}/approve", [], $token)->assertStatus(403);
        $this->api('GET', '/admin/reviews', [], $token)->assertStatus(403);
        $this->api('GET', '/admin/reviews', [], $this->seekerToken)->assertStatus(401);
        $this->assertSame('pending', DB::table('review_reviews')->where('id', $id)->value('status'));
    }

    public function test_the_review_queue_filters_and_shows_the_reports(): void
    {
        $waiting = $this->writeId();
        $reported = $this->otherReview();
        $this->otherReview(company: $this->otherCompanyId);
        $this->api('POST', "/reviews/{$reported}/report", ['reason' => 'spam'], $this->strangerToken)->assertNoContent();

        $this->admin('GET', '/reviews')->assertOk()->assertJsonPath('meta.total', 3);
        $this->admin('GET', '/reviews?filter[status]=pending')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $waiting);
        $this->admin('GET', "/reviews?filter[company_id]={$this->otherCompanyId}")->assertJsonPath('meta.total', 1);
        $this->admin('GET', '/reviews?filter[reported]=1')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $reported)->assertJsonPath('data.0.open_reports', 1);
        $this->admin('GET', '/reviews?filter[status]=bogus')->assertStatus(422);
        $this->admin('GET', '/reviews?filter[nope]=1')->assertStatus(422);

        $this->admin('GET', "/reviews/{$reported}")->assertOk()->assertJsonCount(1, 'data.reports')->assertJsonPath('data.reports.0.reason', 'spam');
    }

    // ----- replies -----

    public function test_a_company_admin_replies_and_the_reply_waits(): void
    {
        $id = $this->publishedId();

        $this->api('PUT', "/company/reviews/{$id}/reply", ['body' => 'Thank you for the kind words.'])->assertOk()->assertJsonPath('data.status', 'pending');
        $this->api('GET', '/company/reviews')->assertOk()->assertJsonPath('data.0.reply.status', 'pending')->assertJsonPath('data.0.author.name', 'Siti Rahma');
        $this->api('GET', '/company/reviews', [], $this->recruiterToken)->assertOk()->assertJsonPath('data.0.reply.body', 'Thank you for the kind words.');

        $this->admin('POST', "/review-replies/{$id}/approve")->assertNoContent();
        $this->assertNotNull(DB::table('notifications')->where('notifiable_id', $this->seekerId)->where('type', 'review.reply_published')->first());
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'review.reply_approved')->count());

        $this->api('PUT', "/company/reviews/{$id}/reply", ['body' => 'Rewritten after thinking it over.'])->assertOk()->assertJsonPath('data.status', 'pending');
        $this->publicList()->assertJsonPath('data.0.reply', null);
        $this->assertSame(1, DB::table('review_replies')->count());
    }

    public function test_who_may_reply(): void
    {
        $id = $this->publishedId();
        $waiting = $this->writeIdOfAnother();
        $body = ['body' => 'Thank you for the kind words.'];

        $this->api('PUT', "/company/reviews/{$id}/reply", $body, $this->recruiterToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:company-permission-required');
        $this->api('PUT', "/company/reviews/{$id}/reply", $body, $this->seekerToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:not-a-company-member');
        $this->api('PUT', "/company/reviews/{$id}/reply", $body, $this->otherAdminToken)->assertNotFound();
        $this->api('PUT', "/company/reviews/{$waiting}/reply", $body)->assertNotFound();
        $this->api('PUT', "/company/reviews/{$id}/reply", ['body' => 'short'])->assertStatus(422)->assertJsonValidationErrors(['body'], 'errors');
    }

    private function writeIdOfAnother(): int
    {
        return $this->otherReview(status: 'pending');
    }

    public function test_a_reply_is_rejected_removed_and_deleted(): void
    {
        $id = $this->publishedId();
        $this->api('PUT', "/company/reviews/{$id}/reply", ['body' => 'Thank you for the kind words.'])->assertOk();

        $this->admin('POST', "/review-replies/{$id}/reject", [])->assertStatus(422);
        $this->admin('POST', "/review-replies/{$id}/reject", ['reason' => 'abusive'])->assertNoContent();
        $this->admin('POST', "/review-replies/{$id}/approve")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:reply-not-pending');
        $this->api('GET', '/company/reviews')->assertJsonPath('data.0.reply.status', 'rejected')->assertJsonPath('data.0.reply.reason', 'abusive');

        $this->admin('GET', '/review-replies?filter[status]=rejected')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.review_id', $id);

        $this->api('PUT', "/company/reviews/{$id}/reply", ['body' => 'A calmer answer to the review.'])->assertOk();
        $this->admin('POST', "/review-replies/{$id}/remove", ['reason' => 'other'])->assertNoContent();
        $this->assertSame(0, DB::table('review_replies')->count());
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'review.reply_removed')->count());

        $this->api('PUT', "/company/reviews/{$id}/reply", ['body' => 'A calmer answer to the review.'])->assertOk();
        $this->api('DELETE', "/company/reviews/{$id}/reply", [], $this->recruiterToken)->assertStatus(403);
        $this->api('DELETE', "/company/reviews/{$id}/reply")->assertNoContent();
        $this->api('DELETE', "/company/reviews/{$id}/reply")->assertNotFound();
    }

    // ----- reports -----

    public function test_a_job_seeker_reports_a_published_review_once(): void
    {
        $id = $this->publishedId();

        $this->api('POST', "/reviews/{$id}/report", ['reason' => 'false-claim', 'note' => 'This did not happen.'], $this->strangerToken)->assertNoContent();
        $this->api('POST', "/reviews/{$id}/report", ['reason' => 'spam'], $this->strangerToken)->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:already-reported');
        $this->api('POST', "/reviews/{$id}/report", ['reason' => 'spam'], $this->seekerToken)->assertStatus(403)->assertJsonPath('type', 'urn:onesis:error:own-review');
        $this->api('POST', "/reviews/{$id}/report", ['reason' => 'rude'], $this->strangerToken)->assertStatus(422);

        $pending = $this->otherReview(status: 'pending');
        $this->api('POST', "/reviews/{$pending}/report", ['reason' => 'spam'], $this->strangerToken)->assertNotFound();
        $this->assertSame(1, DB::table('review_reports')->count());
    }

    public function test_a_company_admin_reports_a_review_of_their_own_page_only(): void
    {
        $id = $this->publishedId();

        $this->api('POST', "/company/reviews/{$id}/report", ['reason' => 'abusive'], $this->recruiterToken)->assertStatus(403);
        $this->api('POST', "/company/reviews/{$id}/report", ['reason' => 'abusive'], $this->otherAdminToken)->assertNotFound();
        $this->api('POST', "/company/reviews/{$id}/report", ['reason' => 'abusive'])->assertNoContent();

        $report = DB::table('review_reports')->first();
        $this->assertSame('company', $report->reporter_kind);
        $this->assertSame($this->companyId, (int) $report->reporter_id);
    }

    public function test_dismissing_a_report_and_taking_the_review_down_settle_reports(): void
    {
        $id = $this->publishedId();
        $this->api('POST', "/reviews/{$id}/report", ['reason' => 'spam'], $this->strangerToken)->assertNoContent();
        $this->api('POST', "/company/reviews/{$id}/report", ['reason' => 'abusive'])->assertNoContent();

        $reports = $this->admin('GET', '/review-reports')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.review.id', $id);
        $first = (int) $reports->json('data.1.id');

        $this->admin('POST', "/review-reports/{$first}/dismiss")->assertNoContent();
        $this->admin('POST', "/review-reports/{$first}/dismiss")->assertStatus(409)->assertJsonPath('type', 'urn:onesis:error:report-not-open');
        $this->assertSame(1, DB::table('platform_audit_logs')->where('action', 'review.report_dismissed')->count());

        $this->admin('POST', "/reviews/{$id}/remove", ['reason' => 'abusive'])->assertOk()->assertJsonPath('data.status', 'removed');

        $this->admin('GET', '/review-reports')->assertJsonPath('meta.total', 0);
        $this->admin('GET', '/review-reports?filter[status]=actioned')->assertJsonPath('meta.total', 1);
        $this->admin('GET', '/review-reports?filter[status]=dismissed')->assertJsonPath('meta.total', 1);
        $this->assertNotNull(DB::table('notifications')->where('notifiable_id', $this->seekerId)->where('type', 'review.removed')->first());
    }

    public function test_a_resolved_report_does_not_stop_the_same_person_reporting_again(): void
    {
        $id = $this->publishedId();
        $this->api('POST', "/reviews/{$id}/report", ['reason' => 'spam'], $this->strangerToken)->assertNoContent();
        $reportId = (int) DB::table('review_reports')->value('id');
        $this->admin('POST', "/review-reports/{$reportId}/dismiss")->assertNoContent();

        $this->api('POST', "/reviews/{$id}/report", ['reason' => 'spam'], $this->strangerToken)->assertNoContent();
        $this->assertSame(2, DB::table('review_reports')->count());
    }

    // ----- erasing the author -----

    public function test_erasing_an_author_keeps_published_ratings_and_drops_the_rest(): void
    {
        $id = $this->publishedId();
        $this->api('POST', "/reviews/{$id}/report", ['reason' => 'spam'], $this->strangerToken)->assertNoContent();

        $strangerReview = $this->api('POST', '/companies/pt-lain/reviews', $this->body(), $this->strangerToken);
        $strangerReview->assertStatus(403);
        $pendingId = (int) DB::table('review_reviews')->insertGetId([
            'company_id' => $this->otherCompanyId, 'user_id' => $this->seekerId, 'rating' => 3, 'title' => 'Waiting', 'body' => 'Still waiting.', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('review_reports')->insert(['review_id' => $id, 'reporter_kind' => 'user', 'reporter_id' => $this->seekerId, 'reason' => 'spam', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);

        $this->app->make(EraseReviewerData::class)->execute($this->seekerId);
        $this->app->make(EraseReviewerData::class)->execute($this->seekerId);

        $row = DB::table('review_reviews')->where('id', $id)->first();
        $this->assertSame('published', $row->status);
        $this->assertSame(4, (int) $row->rating);
        $this->assertNull($row->title);
        $this->assertNull($row->body);
        $this->assertNull(DB::table('review_reviews')->where('id', $pendingId)->first());
        $this->assertSame(0, DB::table('review_reports')->where('reporter_id', $this->seekerId)->count());
        $this->assertSame(1, DB::table('review_reports')->where('reporter_id', $this->strangerId)->count());
    }

    public function test_an_account_that_is_anonymised_triggers_the_erasure(): void
    {
        $id = $this->publishedId();

        event(new UserAnonymized($this->seekerId, now()->toDateTimeImmutable()));

        $this->assertNull(DB::table('review_reviews')->where('id', $id)->value('body'));
    }
}
