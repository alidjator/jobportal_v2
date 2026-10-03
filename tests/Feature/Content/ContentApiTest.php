<?php

namespace Tests\Feature\Content;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Content\Domain\CaptchaVerifier;
use Src\Content\Domain\ContactNotifier;
use Src\Content\Infrastructure\Adapters\ContactNotification;
use Src\Identity\Infrastructure\Persistence\UserModel;
use Tests\FeatureTestCase;

class ContentApiTest extends FeatureTestCase
{
    private function admin(): int
    {
        return DB::table('admins')->insertGetId([
            'name' => 'Admin '.Str::random(5), 'email' => Str::random(10).'@example.test', 'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function category(?string $name = null): int
    {
        return DB::table('post_categories')->insertGetId([
            'name' => $name ?? 'Cat '.Str::random(6), 'slug' => Str::random(8), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makePost(int $category, ?string $title = null, string $status = 'published', ?int $author = null): array
    {
        $title ??= 'Title '.Str::random(10);
        $slug = Str::slug($title).'-'.Str::random(5);
        $id = DB::table('posts')->insertGetId([
            'category_id' => $category, 'author_id' => $author ?? $this->admin(), 'title' => $title, 'slug' => $slug,
            'short_description' => 's', 'description' => 'd', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$id, $slug, $title];
    }

    private function user(): UserModel
    {
        return UserModel::create(['name' => 'U '.Str::random(5), 'email' => Str::random(10).'@example.test', 'password' => 'secret123']);
    }

    private function ensureCms(): void
    {
        if (! DB::table('cms')->exists()) {
            DB::table('cms')->insert(['contact_map' => '']);
        }
        DB::table('cms')->update(['privary_page' => 'default privacy', 'terms_page' => 'default terms', 'refund_page' => 'default refund']);
    }

    public function test_about_returns_testimonials_and_states(): void
    {
        $name = 'T '.Str::random(8);
        DB::table('testimonials')->insert(['name' => $name, 'position' => 'CEO', 'description' => 'nice', 'stars' => 5]);
        $before = $this->getJson('/api/about')->assertOk()->json('data.states.live_jobs');

        DB::table('jobs')->insert(['title' => 'J', 'slug' => Str::random(10), 'status' => 'active', 'deadline' => now()->addDays(3)->toDateString(), 'waiting_for_edit_approval' => 0]);
        DB::table('jobs')->insert(['title' => 'J2', 'slug' => Str::random(10), 'status' => 'active', 'deadline' => now()->subDays(3)->toDateString(), 'waiting_for_edit_approval' => 0]);

        $res = $this->getJson('/api/about')->assertOk()
            ->assertJsonStructure(['data' => ['testimonials', 'states' => ['live_jobs', 'companies', 'candidates']]]);
        $this->assertSame($before + 1, $res->json('data.states.live_jobs'));
        $item = collect($res->json('data.testimonials'))->firstWhere('name', $name);
        $this->assertSame('backend/image/default.png', $item['image']);
    }

    public function test_posts_list_hides_drafts_filters_and_paginates(): void
    {
        $cat = $this->category();
        $other = $this->category();
        [, , $titleA] = $this->makePost($cat, 'Alpha '.Str::random(8));
        [, , $titleB] = $this->makePost($other, 'Beta '.Str::random(8));
        [, , $draft] = $this->makePost($cat, 'Draft '.Str::random(8), 'draft');

        $all = $this->getJson('/api/posts')->assertOk()
            ->assertJsonStructure(['data' => ['posts' => ['data', 'current_page', 'per_page'], 'recent_posts', 'categories']]);
        $this->assertSame(8, $all->json('data.posts.per_page'));
        $this->assertNotContains($draft, array_column($all->json('data.recent_posts'), 'title'));

        $res = $this->getJson("/api/posts?category=$cat,$other&search=".substr($titleA, 0, 12))->assertOk();
        $titles = array_column($res->json('data.posts.data'), 'title');
        $this->assertSame([$titleA], $titles);
        $this->assertSame(0, $res->json('data.posts.data.0.comments_count'));

        $res = $this->getJson("/api/posts?category=$other")->assertOk();
        $this->assertSame([$titleB], array_column($res->json('data.posts.data'), 'title'));

        $res = $this->getJson("/api/posts?category=$cat&search=Draft")->assertOk();
        $this->assertSame([], $res->json('data.posts.data'));
        $this->assertContains($cat, array_column($all->json('data.categories'), 'id'));
    }

    public function test_post_details_with_comments_and_replies(): void
    {
        $cat = $this->category();
        $author = $this->admin();
        [$id, $slug] = $this->makePost($cat, null, 'published', $author);
        $user = $this->user();
        $root = DB::table('post_comments')->insertGetId(['author_id' => $user->id, 'post_id' => $id, 'body' => 'root', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('post_comments')->insert(['author_id' => $user->id, 'post_id' => $id, 'parent_id' => $root, 'body' => 'reply', 'created_at' => now(), 'updated_at' => now()]);

        $res = $this->getJson("/api/post/$slug")->assertOk();
        $this->assertSame($id, $res->json('data.id'));
        $this->assertSame($author, $res->json('data.author.id'));
        $this->assertSame('root', $res->json('data.comments.0.body'));
        $this->assertSame($user->id, $res->json('data.comments.0.user.id'));
        $this->assertSame('reply', $res->json('data.comments.0.replies.0.body'));
        $this->assertArrayHasKey('image_url', $res->json('data'));
        $this->assertArrayNotHasKey('email', $res->json('data.comments.0.user'));
    }

    public function test_post_details_404_for_missing_and_draft(): void
    {
        [, $slug] = $this->makePost($this->category(), null, 'draft');

        $this->getJson("/api/post/$slug")->assertNotFound()->assertJsonStructure(['error']);
        $this->getJson('/api/post/nope-'.Str::random(8))->assertNotFound();
    }

    public function test_comment_requires_auth(): void
    {
        [, $slug] = $this->makePost($this->category());

        $this->postJson("/api/comment/$slug/store", ['body' => 'hello'])->assertUnauthorized();
    }

    public function test_comment_store_and_reply(): void
    {
        [$id, $slug] = $this->makePost($this->category());
        $user = $this->user();
        Sanctum::actingAs($user);

        $res = $this->postJson("/api/comment/$slug/store", ['body' => 'First!'])->assertOk();
        $this->assertSame('First!', $res->json('data.comment.body'));
        $this->assertSame($user->id, $res->json('data.comment.user.id'));
        $this->assertNotEmpty($res->json('data.message'));
        $parent = $res->json('data.comment.id');

        $reply = $this->postJson("/api/comment/$slug/store", ['body' => 'reply', 'parent_id' => $parent])->assertOk();
        $this->assertSame($parent, $reply->json('data.comment.parent_id'));
        $this->assertDatabaseHas('post_comments', ['post_id' => $id, 'author_id' => $user->id, 'parent_id' => $parent]);
    }

    public function test_comment_validation(): void
    {
        [, $slug] = $this->makePost($this->category());
        [, $otherSlug] = $this->makePost($this->category());
        Sanctum::actingAs($this->user());

        $this->postJson("/api/comment/$slug/store", [])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson("/api/comment/$slug/store", ['body' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson("/api/comment/$slug/store", ['body' => str_repeat('a', 2501)])->assertUnprocessable();

        $foreign = $this->postJson("/api/comment/$otherSlug/store", ['body' => 'on other'])->json('data.comment.id');
        $this->postJson("/api/comment/$slug/store", ['body' => 'hi', 'parent_id' => $foreign])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->postJson("/api/comment/$slug/store", ['body' => 'hi', 'parent_id' => 999999999])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    }

    public function test_comment_on_draft_or_missing_post_is_404(): void
    {
        [, $draft] = $this->makePost($this->category(), null, 'draft');
        Sanctum::actingAs($this->user());

        $this->postJson("/api/comment/$draft/store", ['body' => 'hello'])->assertNotFound();
        $this->postJson('/api/comment/missing-'.Str::random(6).'/store', ['body' => 'hello'])->assertNotFound();
    }

    public function test_faq_filters_by_language_code(): void
    {
        $slug = Str::random(8);
        $cat = DB::table('faq_categories')->insertGetId(['name' => 'FAQ '.$slug, 'slug' => $slug, 'icon' => 'i']);
        DB::table('faqs')->insert([
            ['faq_category_id' => $cat, 'question' => 'Q-en', 'answer' => 'A', 'code' => 'en'],
            ['faq_category_id' => $cat, 'question' => 'Q-xx', 'answer' => 'A', 'code' => 'xx'],
        ]);
        $code = 'z'.Str::random(3);
        DB::table('languages')->insert(['name' => 'L'.$code, 'code' => $code, 'icon' => 'i'.$code]);
        DB::table('faqs')->insert(['faq_category_id' => $cat, 'question' => 'Q-'.$code, 'answer' => 'A', 'code' => $code]);

        $find = fn ($json) => collect($json)->firstWhere('id', $cat);

        $default = $find($this->getJson('/api/faq')->assertOk()->json('data'));
        $this->assertSame(['Q-en'], array_column($default['faqs'], 'question'));

        $custom = $find($this->getJson('/api/faq', ['X-Language' => $code])->json('data'));
        $this->assertSame(['Q-'.$code], array_column($custom['faqs'], 'question'));

        // bahasa tidak terdaftar -> default
        $unknown = $find($this->getJson('/api/faq', ['X-Language' => 'xx'])->json('data'));
        $this->assertSame(['Q-en'], array_column($unknown['faqs'], 'question'));
    }

    #[DataProvider('pages')]
    public function test_cms_pages_language_fallback(string $path, string $slug, string $default): void
    {
        $this->ensureCms();
        $code = 'y'.Str::random(3);
        DB::table('languages')->insert(['name' => 'L'.$code, 'code' => $code, 'icon' => 'i'.$code]);
        $en = config('jobportal.default_language');
        DB::table('cms_contents')->where('page_slug', $slug)->whereIn('translation_code', [$en, $code])->delete();

        // 1. tidak ada baris -> kolom default Cms
        $this->getJson($path)->assertOk()->assertExactJson(['data' => ['content' => $default, 'default_content' => $default]]);

        // 2. baris bahasa default dipakai bila bahasa diminta tidak punya baris
        DB::table('cms_contents')->insert(['page_slug' => $slug, 'translation_code' => $en, 'text' => 'EN text']);
        $this->getJson($path, ['X-Language' => $code])->assertOk()->assertJsonPath('data.content', 'EN text')->assertJsonPath('data.default_content', $default);

        // 3. bahasa diminta punya baris
        DB::table('cms_contents')->insert(['page_slug' => $slug, 'translation_code' => $code, 'text' => 'Own text']);
        $this->getJson($path, ['X-Language' => $code])->assertJsonPath('data.content', 'Own text');
        $this->getJson($path)->assertJsonPath('data.content', 'EN text');
    }

    public static function pages(): array
    {
        return [
            'privacy' => ['/api/privacy-policy', 'privacy_page', 'default privacy'],
            'terms' => ['/api/terms-condition', 'terms_condition_page', 'default terms'],
            'refund' => ['/api/refund-policy', 'refund_page', 'default refund'],
        ];
    }

    public function test_contact_validation(): void
    {
        $this->postJson('/api/contact', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
        $this->postJson('/api/contact', ['name' => 'ab', 'email' => 'a@b.c', 'subject' => 'abcd', 'message' => 'too short'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'subject', 'message']);
    }

    public function test_contact_persists_and_notifies(): void
    {
        $spy = new class implements ContactNotifier
        {
            public array $sent = [];

            public function notifyAdmins(array $contact): void
            {
                $this->sent[] = $contact;
            }
        };
        $this->app->instance(ContactNotifier::class, $spy);
        $email = Str::random(8).'@example.test';

        $this->postJson('/api/contact', ['name' => 'Jane', 'email' => $email, 'subject' => 'Hello there', 'message' => 'A long enough message'])
            ->assertOk()->assertJsonPath('data.status', true);

        $this->assertDatabaseHas('contacts', ['email' => $email, 'subject' => 'Hello there']);
        $this->assertCount(1, $spy->sent);
    }

    public function test_contact_mail_failure_does_not_break_request(): void
    {
        Notification::fake();
        config(['mail.default' => 'log', 'mail.from.address' => 'a@b.test', 'mail.from.name' => 'X']);
        $this->admin();

        $this->postJson('/api/contact', ['name' => 'Jane', 'email' => 'j@example.test', 'subject' => 'Hello there', 'message' => 'A long enough message'])
            ->assertOk();
        Notification::assertSentTimes(ContactNotification::class, DB::table('admins')->count());
    }

    public function test_contact_captcha_when_active(): void
    {
        $this->app->instance(CaptchaVerifier::class, new class implements CaptchaVerifier
        {
            public function isActive(): bool
            {
                return true;
            }

            public function verify(?string $response, ?string $ip = null): bool
            {
                return $response === 'good';
            }
        });
        $this->app->instance(ContactNotifier::class, new class implements ContactNotifier
        {
            public function notifyAdmins(array $contact): void {}
        });
        $payload = ['name' => 'Jane', 'email' => 'j@example.test', 'subject' => 'Hello there', 'message' => 'A long enough message'];

        $this->postJson('/api/contact', $payload)->assertUnprocessable()
            ->assertJsonPath('errors.g-recaptcha-response.0', 'Please verify that you are not a robot.');
        $this->postJson('/api/contact', $payload + ['g-recaptcha-response' => 'bad'])->assertUnprocessable()
            ->assertJsonPath('errors.g-recaptcha-response.0', 'Captcha error! try again later or contact site admin.');
        $this->postJson('/api/contact', $payload + ['g-recaptcha-response' => 'good'])->assertOk();
    }

    public function test_page_list_uses_configured_base_url(): void
    {
        config(['content.website_url' => 'https://karir.example.test/']);

        $this->getJson('/api/page-list')->assertOk()->assertExactJson(['data' => [
            'blog' => 'https://karir.example.test/posts',
            'about' => 'https://karir.example.test/about',
            'contact' => 'https://karir.example.test/contact',
            'faq' => 'https://karir.example.test/faq',
            'privacy_policy' => 'https://karir.example.test/privacy-policy',
            'term_condition' => 'https://karir.example.test/terms-condition',
            'refund_policy' => 'https://karir.example.test/refund-policy',
        ]]);
    }
}
