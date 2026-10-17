<?php

namespace Tests\Feature\Recruitment;

use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Src\Identity\Infrastructure\Persistence\Eloquent\AdminModel;
use Src\Recruitment\Application\RecruitmentEmailTemplates;
use Src\Shared\Application\Email\EmailTemplateCatalog;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

final class StageNotificationsTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    private int $applicationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31']);
        $this->applicationId = (int) $this->apply($this->job)->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function stage(string $type): int
    {
        return (int) DB::table('recruitment_stages')->where('company_id', $this->companyId)->where('type', $type)->where('is_default', 1)->value('id');
    }

    private function move(string $type, ?int $id = null): TestResponse
    {
        $body = ['stage_id' => $this->stage($type)];

        // A move to an interview stage comes with its schedule and one to an offer stage with its offer; the notifications below are about the stage only.
        if ($type === 'interview') {
            $body['interview'] = $this->interviewSchedule();
        }

        if ($type === 'offer') {
            $body['offer'] = $this->offerDetails();
        }

        return $this->api('POST', '/company/applications/'.($id ?? $this->applicationId).'/move', $body, $this->recruiterToken)->assertNoContent();
    }

    /** @return list<object> the in-app notifications of the applicant, oldest first */
    private function notifications(?int $userId = null): array
    {
        return DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId ?? $this->seekerId)->where('type', 'not like', 'interview.%')->where('type', 'not like', 'offer.%')->orderBy('created_at')->orderBy('id')->get()->all();
    }

    /** @return list<Email> the emails the array mailer received so far */
    private function emails(): array
    {
        /** @var TransportInterface $transport */
        $transport = $this->app->make(MailManager::class)->mailer()->getSymfonyTransport();

        return array_values(array_map(fn ($sent): Email => $sent->getOriginalMessage(), $transport->messages()->all()));
    }

    /** The emails that went to the applicant about the stage of their application (not the receipt of it, the interview or the offer). */
    private function emailsToApplicant(): array
    {
        return array_values(array_filter($this->emails(), fn (Email $email): bool => $email->getTo()[0]->getAddress() === 'siti@example.test' && ! preg_match('/Interview scheduled|Your interview for|Reminder: your interview|Jadwal wawancara|Wawancara untuk|Pengingat|Job offer for|Your job offer for|Penawaran kerja untuk|We received your application|sudah diterima/', $email->getSubject() ?? '')));
    }

    // ----- the four outcomes -----

    public function test_each_outcome_writes_an_in_app_notification_and_sends_an_email(): void
    {
        $expected = [
            'interview' => ['application.moved_to_interview', 'moved to the interview stage'],
            'offer' => ['application.moved_to_offer', 'moved to the offer stage'],
            'hired' => ['application.hired', 'Congratulations!'],
        ];

        foreach ($expected as $type => [$notificationType]) {
            $this->move($type);
        }

        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'position_filled', 'note' => 'Secret internal note'], $this->recruiterToken)->assertNoContent();

        $notifications = $this->notifications();
        $types = array_column($notifications, 'type');
        sort($types);
        $this->assertSame(['application.hired', 'application.moved_to_interview', 'application.moved_to_offer', 'application.rejected'], $types);

        $data = json_decode(collect($notifications)->firstWhere('type', 'application.moved_to_interview')->data, true);
        $this->assertSame(['application_id' => $this->applicationId, 'job_id' => $this->job, 'job_title' => 'Backend Developer', 'company_name' => DB::table('company_companies')->where('id', $this->companyId)->value('name')], $data);

        $emails = $this->emailsToApplicant();
        $this->assertCount(4, $emails);
        $this->assertStringContainsString('interview stage', $emails[0]->getSubject() ?? '');
        $this->assertStringContainsString('Backend Developer', $emails[0]->getSubject() ?? '');
        $this->assertStringContainsString('Siti Rahma', (string) $emails[0]->getTextBody());
        $this->assertStringContainsString((string) DB::table('company_companies')->where('id', $this->companyId)->value('name'), (string) $emails[0]->getTextBody());
        $this->assertStringContainsString('offer stage', $emails[1]->getSubject() ?? '');
        $this->assertStringContainsString('Congratulations', $emails[2]->getSubject() ?? '');
        $this->assertStringContainsString('update on your application', strtolower($emails[3]->getSubject() ?? ''));
    }

    public function test_the_notification_never_carries_the_rejection_reason_the_note_or_the_recruiter(): void
    {
        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'salary_expectation_mismatch', 'note' => 'Secret internal note'], $this->recruiterToken)->assertNoContent();

        $everything = (string) json_encode($this->notifications()).($this->emailsToApplicant()[0]->getTextBody()).($this->emailsToApplicant()[0]->getHtmlBody()).($this->emailsToApplicant()[0]->getSubject());

        foreach (['Secret internal note', 'salary_expectation_mismatch', 'Salary', 'Rina', 'Rekruter'] as $leak) {
            $this->assertStringNotContainsString($leak, $everything);
        }
    }

    // ----- when nobody is told -----

    public function test_moves_to_applied_or_screening_and_between_stages_of_one_type_tell_nobody(): void
    {
        $extra = (int) $this->api('POST', '/company/stages', ['name' => 'HR interview', 'type' => 'interview'], $this->adminToken)->assertCreated()->json('data.id');

        $this->move('screening');
        $this->move('applied');
        $this->assertSame([], $this->notifications());
        $this->assertSame([], $this->emailsToApplicant());

        $this->move('interview');
        $this->api('POST', "/company/applications/{$this->applicationId}/move", ['stage_id' => $extra], $this->recruiterToken)->assertNoContent();

        // Only the move into `interview` told the applicant; the move to the other interview stage did not.
        $this->assertCount(1, $this->notifications());
        $this->assertCount(1, $this->emailsToApplicant());
    }

    public function test_a_move_back_into_an_announced_type_tells_again(): void
    {
        $this->move('hired');
        $this->move('interview');
        $this->move('hired');

        $types = array_column($this->notifications(), 'type');
        sort($types);
        $this->assertSame(['application.hired', 'application.hired', 'application.moved_to_interview'], $types);
        $this->assertCount(3, $this->emailsToApplicant());
    }

    public function test_the_applicants_withdrawal_and_the_team_are_not_notified_by_a_move(): void
    {
        $this->move('interview');

        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->recruiterId)->where('type', 'like', 'application.moved%')->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->adminId)->count());

        $before = count($this->notifications());
        $this->api('POST', "/me/applications/{$this->applicationId}/withdraw", token: $this->seekerToken)->assertNoContent();
        $this->assertCount($before, $this->notifications());
    }

    // ----- the email preference -----

    public function test_with_the_email_preference_off_only_the_in_app_notification_is_written(): void
    {
        $this->api('PUT', '/me/notification-preferences/application_update/email', ['enabled' => false], $this->seekerToken)->assertNoContent();

        $this->move('interview');

        $this->assertCount(1, $this->notifications());
        $this->assertSame([], $this->emailsToApplicant());
    }

    // ----- language -----

    public function test_the_email_follows_the_language_of_the_applicants_account(): void
    {
        DB::table('identity_users')->where('id', $this->seekerId)->update(['locale' => 'id']);

        $this->move('interview');

        $email = $this->emailsToApplicant()[0];
        $this->assertStringContainsString('tahap wawancara', $email->getSubject() ?? '');
        $this->assertStringContainsString('Halo Siti Rahma', (string) $email->getTextBody());
    }

    // ----- an applicant who is gone -----

    public function test_nothing_is_sent_to_an_anonymised_account(): void
    {
        DB::table('identity_users')->where('id', $this->seekerId)->update(['anonymized_at' => now()]);

        $this->move('interview');
        $this->api('POST', "/company/applications/{$this->applicationId}/reject", ['reason' => 'position_filled'], $this->recruiterToken)->assertNoContent();

        $this->assertSame([], $this->notifications());
        $this->assertSame([], $this->emailsToApplicant());
        $this->assertSame('rejected', DB::table('recruitment_applications')->where('id', $this->applicationId)->value('stage_type'));
    }

    // ----- the templates -----

    public function test_the_four_templates_are_in_the_catalog_with_both_languages_and_no_required_variable(): void
    {
        $catalog = $this->app->make(EmailTemplateCatalog::class);

        foreach ([RecruitmentEmailTemplates::INTERVIEW, RecruitmentEmailTemplates::OFFER, RecruitmentEmailTemplates::HIRED, RecruitmentEmailTemplates::REJECTED] as $key) {
            $template = $catalog->find($key);

            $this->assertNotNull($template, $key);
            $this->assertSame(['name', 'job_title', 'company_name'], $template->variableNames());
            $this->assertSame([], $template->requiredVariableNames());
            $this->assertTrue($template->defaultSubject->isCompleteInAllLanguages());
            $this->assertTrue($template->defaultBody->isCompleteInAllLanguages());
        }

        $this->api('GET', '/admin/email-templates', token: $this->adminAccountToken())->assertOk();
    }

    private function adminAccountToken(): string
    {
        $admin = AdminModel::query()->create(['name' => 'Root', 'email' => 'root@example.test', 'password' => 'hash', 'status' => 'active']);
        $role = Role::findOrCreate('probe', 'admin');
        $permission = Permission::findOrCreate('email_templates.manage', 'admin');
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        return $admin->createToken('console', ['*'], now()->addHour())->plainTextToken;
    }
}
