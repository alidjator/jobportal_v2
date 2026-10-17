<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Src\Dashboard\Infrastructure\Mail\TeamDigestMail;
use Tests\Concerns\BuildsApplicationFixtures;
use Tests\FeatureTestCase;

/** `docs/design/phase-8-recruiter-workday.md` section 3: the daily digest of the team. */
final class TeamDigestTest extends FeatureTestCase
{
    use BuildsApplicationFixtures;

    private int $job;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->buildApplicationFixtures();
        $this->job = $this->insertJob('Backend Developer', 'active', [$this->recruiterId], attributes: ['deadline' => '2026-12-31', 'published_at' => '2026-10-01 00:00:00']);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->apply($this->job)->assertCreated();
        $this->moveClockTo('2026-10-14 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownApplicationFixtures();

        parent::tearDown();
    }

    private function moveClockTo(string $time): void
    {
        Carbon::setTestNow($time);
        $this->recruiterToken = $this->tokenFor($this->recruiterId);
        Cache::flush();
    }

    private function send(): string
    {
        Artisan::call('company:send-team-digests');

        return trim(Artisan::output());
    }

    private function assertSentTo(string $email): void
    {
        Mail::assertQueued(TeamDigestMail::class, static fn (TeamDigestMail $mail): bool => $mail->hasTo($email));
    }

    private function assertNotSentTo(string $email): void
    {
        Mail::assertNotQueued(TeamDigestMail::class, static fn (TeamDigestMail $mail): bool => $mail->hasTo($email));
    }

    public function test_a_member_with_something_waiting_gets_one_digest_with_the_company_and_the_day(): void
    {
        $this->assertStringContainsString('Sent', $this->send());

        $this->assertSentTo('rina@example.test');
        Mail::assertQueued(TeamDigestMail::class, function (TeamDigestMail $mail): bool {
            if (! $mail->hasTo('rina@example.test')) {
                return false;
            }

            $this->assertSame('Daily summary of PT-MAJU, 14 October 2026', $mail->envelope()->subject);
            $this->assertStringContainsString('applications waiting too long in an early stage', $mail->render());

            return true;
        });
    }

    public function test_the_lines_link_to_the_company_app_when_it_is_configured(): void
    {
        config(['app.company_app_url' => 'https://app.example.test/']);

        $this->send();

        Mail::assertQueued(TeamDigestMail::class, function (TeamDigestMail $mail): bool {
            if (! $mail->hasTo('rina@example.test')) {
                return false;
            }

            $this->assertStringContainsString('app.example.test', $mail->render());

            return true;
        });
    }

    public function test_applications_of_the_last_24_hours_on_the_assigned_jobs_are_counted(): void
    {
        $this->moveClockTo('2026-10-14 08:00:00');
        $token = $this->tokenFor($this->seeker('budi.pelamar@example.test'));
        $this->apply($this->job, ['resume_id' => $this->completeProfile($token)], $token)->assertCreated();
        $this->moveClockTo('2026-10-14 09:00:00');

        $this->send();

        Mail::assertQueued(TeamDigestMail::class, function (TeamDigestMail $mail): bool {
            if (! $mail->hasTo('rina@example.test')) {
                return false;
            }

            $this->assertStringContainsString('1 new application in the last 24 hours', $mail->render());

            return true;
        });
        $this->assertNotSentTo('sari@example.test');
    }

    public function test_nothing_is_sent_to_a_member_when_every_count_is_zero(): void
    {
        $this->memberWith(['applications.view']);

        $this->send();

        $this->assertNotSentTo('limited@example.test');
    }

    public function test_a_member_who_switched_the_digest_off_gets_none(): void
    {
        $this->api('PUT', '/me/notification-preferences/team_daily_digest/email', ['enabled' => false], $this->recruiterToken)->assertNoContent();

        $this->send();

        $this->assertNotSentTo('rina@example.test');
    }

    public function test_a_deactivated_account_gets_none(): void
    {
        DB::table('identity_users')->where('id', $this->recruiterId)->update(['status' => 'deactivated']);

        $this->send();

        $this->assertNotSentTo('rina@example.test');
    }

    public function test_a_suspended_member_gets_none(): void
    {
        DB::table('company_members')->where('user_id', $this->recruiterId)->update(['status' => 'suspended']);

        $this->send();

        $this->assertNotSentTo('rina@example.test');
    }

    public function test_a_suspended_company_gets_none(): void
    {
        DB::table('company_companies')->where('id', $this->companyId)->update(['status' => 'suspended']);

        $this->send();

        $this->assertNotSentTo('rina@example.test');
    }

    public function test_the_command_runs_every_morning_but_sunday_at_seven_jakarta_time(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(static fn ($event): bool => str_contains((string) $event->command, 'company:send-team-digests'));

        $this->assertNotNull($event);
        $this->assertSame('0 7 * * 1,2,3,4,5,6', $event->expression);
        $this->assertSame('Asia/Jakarta', (string) $event->timezone);
    }
}
