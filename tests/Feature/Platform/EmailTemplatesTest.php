<?php

namespace Tests\Feature\Platform;

use DateTimeImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Tests\FeatureTestCase;

final class EmailTemplatesTest extends FeatureTestCase
{
    private const string KEY = 'identity.email_verification';

    private int $adminId;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Artisan::call('identity:sync-permissions');
        $admin = AdminModel::query()->create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'hash', 'status' => 'active']);
        $admin->givePermissionTo('email_templates.manage');
        $this->adminId = $admin->id;
        $this->token = $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }

    private function asAdmin(string $method, string $uri, array $data = [], ?string $token = null): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, "/api/v1/admin/{$uri}", $data);
    }

    /** @return list<object> */
    private function audit(string $action): array
    {
        return DB::table('platform_audit_logs')->where('action', $action)->orderBy('id')->get()->all();
    }

    private function send(string $name = 'Siti', string $locale = 'en'): object
    {
        $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->flush();
        $this->app->make(VerificationCodeSender::class)->sendEmailVerification('siti@example.test', $name, $locale, '049213', new DateTimeImmutable('+15 minutes'));

        return $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages()[0]->getOriginalMessage();
    }

    public function test_the_templates_need_the_email_templates_permission(): void
    {
        $this->getJson('/api/v1/admin/email-templates')->assertStatus(401);

        $other = AdminModel::query()->create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'hash', 'status' => 'active']);
        $other->givePermissionTo('settings.manage');
        $token = $other->createToken('console', ['*'], now()->addHour())->plainTextToken;

        foreach ([['GET', 'email-templates'], ['GET', 'email-templates/'.self::KEY], ['PUT', 'email-templates/'.self::KEY.'/en'], ['DELETE', 'email-templates/'.self::KEY.'/en'], ['POST', 'email-templates/'.self::KEY.'/preview']] as [$method, $uri]) {
            $this->asAdmin($method, $uri, [], $token)->assertStatus(403);
        }
    }

    public function test_the_list_shows_each_template_with_its_variables_and_defaults(): void
    {
        $response = $this->asAdmin('GET', 'email-templates')->assertOk();

        $this->assertSame(['account.data_export_ready', 'assessment.explanation_ready', 'assessment.explanation_revised', 'assessment.psychologist_invitation', 'assessment.test_cancelled', 'assessment.test_expired', 'assessment.test_invited', 'assessment.test_reminder', 'company.invitation', 'company.member_reactivated', 'company.member_suspended', 'company.ownership_gained', 'company.ownership_handed_over', 'identity.email_verification', 'identity.password_reset', 'identity.two_factor_required', 'invitation.received', 'job_seeker.profile_reminder', 'jobalert.digest', 'matching.recommendations', 'messaging.unread_message', 'newsletter.confirm', 'recruitment.application_hired', 'recruitment.application_interview', 'recruitment.application_offer', 'recruitment.application_rejected', 'recruitment.interview_cancelled', 'recruitment.interview_reminder', 'recruitment.interview_rescheduled', 'recruitment.interview_scheduled', 'recruitment.offer_expiring', 'recruitment.offer_revised', 'recruitment.offer_sent', 'recruitment.offer_withdrawn'], array_column($response->json('data'), 'key'));

        $template = $response->json('data.13');
        $this->assertSame(['name', 'code', 'minutes'], array_column($template['variables'], 'name'));
        $this->assertSame([false, true, false], array_column($template['variables'], 'required'));
        $this->assertSame('049213', $template['variables'][1]['sample']);
        $this->assertSame('Your verification code', $template['languages']['en']['default']['subject']);
        $this->assertStringContainsString('{{code}}', $template['languages']['id']['default']['body']);
        $this->assertNull($template['languages']['en']['override']);
        $this->assertFalse($template['languages']['en']['is_customized']);
    }

    public function test_one_template_can_be_read_and_an_unknown_one_is_a_404(): void
    {
        $this->asAdmin('GET', 'email-templates/'.self::KEY)->assertOk()->assertJsonPath('data.key', self::KEY);
        $this->asAdmin('GET', 'email-templates/probe.nothing')->assertStatus(404)->assertJsonPath('type', 'urn:onesis:error:not-found');
    }

    public function test_an_override_is_saved_per_language_and_audited_without_its_text(): void
    {
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => 'Code {{code}}', 'body' => "Hi {{name}},\n\nYour code: **{{code}}**"])
            ->assertOk()
            ->assertJsonPath('data.languages.en.is_customized', true)
            ->assertJsonPath('data.languages.en.override.subject', 'Code {{code}}')
            ->assertJsonPath('data.languages.en.override.updated_by_admin_id', $this->adminId)
            ->assertJsonPath('data.languages.id.is_customized', false);

        $entries = $this->audit('email_template.updated');
        $this->assertCount(1, $entries);
        $this->assertSame('admin', $entries[0]->actor_type);
        $this->assertSame($this->adminId, (int) $entries[0]->actor_id);
        $this->assertSame(['template' => self::KEY, 'locale' => 'en'], json_decode($entries[0]->metadata, true));
        $this->assertStringNotContainsString('Your code', json_encode($entries));
    }

    public function test_saving_again_replaces_the_override_instead_of_adding_one(): void
    {
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => 'One {{code}}', 'body' => 'Body {{code}}'])->assertOk();
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => 'Two {{code}}', 'body' => 'Body {{code}}'])->assertOk()->assertJsonPath('data.languages.en.override.subject', 'Two {{code}}');

        $this->assertSame(1, DB::table('platform_email_template_overrides')->count());
        $this->assertCount(2, $this->audit('email_template.updated'));
    }

    public function test_invalid_templates_are_refused_with_the_problem_per_field(): void
    {
        $unknown = $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => 'Hi', 'body' => 'Code {{code}} and {{secret}}'])
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-email-template');
        $this->assertStringContainsString('{{secret}}', $unknown->json('errors.body.0'));
        $this->assertStringContainsString('{{name}}, {{code}}, {{minutes}}', $unknown->json('errors.body.0'));

        $missing = $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => 'Hi', 'body' => 'No code here'])->assertStatus(422);
        $this->assertStringContainsString('must contain: {{code}}', $missing->json('errors.body.0'));

        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => 'Hi {{nope}}', 'body' => '{{code}}'])->assertStatus(422)->assertJsonStructure(['errors' => ['subject']]);
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => "Two\nlines", 'body' => '{{code}}'])->assertStatus(422)->assertJsonStructure(['errors' => ['subject']]);
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => str_repeat('x', 256), 'body' => '{{code}}'])->assertStatus(422);
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => 'Hi', 'body' => str_repeat('x', 20001).'{{code}}'])->assertStatus(422);
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => '', 'body' => ''])->assertStatus(422);
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', [])->assertStatus(422);

        $this->assertSame(0, DB::table('platform_email_template_overrides')->count());
        $this->assertSame([], $this->audit('email_template.updated'));
    }

    public function test_only_the_two_languages_exist(): void
    {
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/fr', ['subject' => 'Hi', 'body' => '{{code}}'])->assertStatus(404);
        $this->asAdmin('DELETE', 'email-templates/'.self::KEY.'/fr')->assertStatus(404);
        $this->asAdmin('POST', 'email-templates/'.self::KEY.'/preview', ['locale' => 'fr'])->assertStatus(422)->assertJsonValidationErrors(['locale'], 'errors');
        $this->asAdmin('PUT', 'email-templates/probe.nothing/en', ['subject' => 'Hi', 'body' => 'x'])->assertStatus(404);
    }

    public function test_deleting_an_override_restores_the_default_and_is_audited_only_when_there_was_one(): void
    {
        $this->asAdmin('DELETE', 'email-templates/'.self::KEY.'/en')->assertOk()->assertJsonPath('data.languages.en.is_customized', false);
        $this->assertSame([], $this->audit('email_template.reset'));

        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => 'Code {{code}}', 'body' => 'Body {{code}}'])->assertOk();
        $this->asAdmin('DELETE', 'email-templates/'.self::KEY.'/en')->assertOk()->assertJsonPath('data.languages.en.is_customized', false);

        $this->assertSame(0, DB::table('platform_email_template_overrides')->count());
        $this->assertSame(['template' => self::KEY, 'locale' => 'en'], json_decode($this->audit('email_template.reset')[0]->metadata, true));
    }

    public function test_a_real_email_uses_the_default_until_an_override_exists_and_only_in_that_language(): void
    {
        $default = $this->send();
        $this->assertSame('Your verification code', $default->getSubject());
        $this->assertStringContainsString('049213', $default->getHtmlBody());
        $this->assertStringContainsString('049213', $default->getTextBody());

        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => 'Welcome! Code: {{code}}', 'body' => "Dear {{name}},\n\nEnter **{{code}}** within {{minutes}} minutes."])->assertOk();

        $custom = $this->send('Siti', 'en');
        $this->assertSame('Welcome! Code: 049213', $custom->getSubject());
        $this->assertStringContainsString('Dear Siti,', $custom->getHtmlBody());
        $this->assertMatchesRegularExpression('/<strong[^>]*>049213<\/strong>/', $custom->getHtmlBody());
        $this->assertStringContainsString('within 15 minutes', $custom->getHtmlBody());
        $this->assertStringContainsString('Enter 049213 within 15 minutes.', $custom->getTextBody());

        $indonesian = $this->send('Siti', 'id');
        $this->assertSame('Kode verifikasi Anda', $indonesian->getSubject());
    }

    public function test_the_standard_email_layout_wraps_the_body(): void
    {
        $html = $this->send()->getHtmlBody();

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString(config('app.name'), $html);
        $this->assertStringContainsString('style=', $html);
    }

    public function test_a_recipient_name_cannot_inject_markup_or_formatting(): void
    {
        $html = $this->send('*Siti* <script>alert(1)</script> [x](javascript:alert(1))')->getHtmlBody();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertDoesNotMatchRegularExpression('/<em[ >]/', $html);
        $this->assertStringNotContainsString('href="javascript', $html);
        $this->assertStringContainsString('*Siti*', $html);
    }

    public function test_html_and_unsafe_links_written_by_an_admin_are_not_executed(): void
    {
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', [
            'subject' => 'Code',
            'body' => "{{code}}\n\n<script>alert('x')</script>\n\n<img src=x onerror=alert(1)>\n\n[click](javascript:alert(1)) and [safe](https://example.test/page)",
        ])->assertOk();

        $html = $this->send()->getHtmlBody();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('href="javascript', $html);
        $this->assertStringContainsString('href="https://example.test/page"', $html);
    }

    public function test_a_subject_cannot_be_used_to_inject_headers_through_a_variable(): void
    {
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => 'Hi {{name}}', 'body' => '{{code}}'])->assertOk();

        $message = $this->send("Siti\r\nBcc: evil@example.test");

        $this->assertSame('Hi Siti Bcc: evil@example.test', $message->getSubject());
        $this->assertNull($message->getBcc()[0] ?? null);
    }

    public function test_the_preview_renders_the_default_with_sample_values_and_saves_nothing(): void
    {
        $response = $this->asAdmin('POST', 'email-templates/'.self::KEY.'/preview', ['locale' => 'en'])->assertOk();

        $this->assertSame('Your verification code', $response->json('data.subject'));
        $this->assertStringContainsString('049213', $response->json('data.html'));
        $this->assertStringContainsString('Hello Siti,', $response->json('data.html'));
        $this->assertStringContainsString('valid for 15 minutes', $response->json('data.text'));
        $this->assertSame(0, DB::table('platform_email_template_overrides')->count());
        $this->assertSame([], $this->audit('email_template.updated'));
    }

    public function test_the_preview_renders_a_draft_that_is_not_saved(): void
    {
        $response = $this->asAdmin('POST', 'email-templates/'.self::KEY.'/preview', ['locale' => 'en', 'subject' => 'Draft {{code}}', 'body' => 'Look **{{code}}** <script>x</script>'])->assertOk();

        $this->assertSame('Draft 049213', $response->json('data.subject'));
        $this->assertMatchesRegularExpression('/<strong[^>]*>049213<\/strong>/', $response->json('data.html'));
        $this->assertStringNotContainsString('<script>', $response->json('data.html'));
        $this->assertSame(0, DB::table('platform_email_template_overrides')->count());
    }

    public function test_the_preview_of_a_draft_follows_the_same_rules_as_saving(): void
    {
        $this->asAdmin('POST', 'email-templates/'.self::KEY.'/preview', ['locale' => 'en', 'body' => 'No code'])
            ->assertStatus(422)
            ->assertJsonPath('type', 'urn:onesis:error:invalid-email-template');
        $this->asAdmin('POST', 'email-templates/'.self::KEY.'/preview', ['locale' => 'en', 'subject' => 'Hi {{nope}}'])->assertStatus(422);
        $this->asAdmin('POST', 'email-templates/probe.nothing/preview', ['locale' => 'en'])->assertStatus(404);
    }

    public function test_the_preview_of_a_single_draft_field_uses_the_stored_override_for_the_other(): void
    {
        $this->asAdmin('PUT', 'email-templates/'.self::KEY.'/en', ['subject' => 'Saved {{code}}', 'body' => 'Saved body {{code}}'])->assertOk();

        $response = $this->asAdmin('POST', 'email-templates/'.self::KEY.'/preview', ['locale' => 'en', 'subject' => 'Draft subject {{code}}'])->assertOk();

        $this->assertSame('Draft subject 049213', $response->json('data.subject'));
        $this->assertStringContainsString('Saved body 049213', $response->json('data.html'));
    }
}
