<?php

namespace Tests\Feature\Identity;

use DateTimeImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Src\Identity\Application\Auth\VerificationCodeSender;
use Src\Identity\Infrastructure\Mail\EmailVerificationMail;
use Src\Identity\Infrastructure\Mail\PasswordResetMail;
use Tests\FeatureTestCase;

final class EmailVerificationMailTest extends FeatureTestCase
{
    private function mail(): EmailVerificationMail
    {
        return new EmailVerificationMail('Siti', '049213', new DateTimeImmutable('2026-10-03 10:15:00'), new DateTimeImmutable('2026-10-03 10:00:00'));
    }

    public function test_the_mail_is_rendered_in_english(): void
    {
        App::setLocale('en');

        $html = $this->mail()->render();

        $this->assertStringContainsString('049213', $html);
        $this->assertStringContainsString('Hello Siti,', $html);
        $this->assertStringContainsString('valid for 15 minutes', $html);
    }

    public function test_the_mail_is_rendered_in_indonesian(): void
    {
        App::setLocale('id');

        $html = $this->mail()->render();

        $this->assertStringContainsString('049213', $html);
        $this->assertStringContainsString('Halo Siti,', $html);
        $this->assertStringContainsString('berlaku selama 15 menit', $html);
    }

    public function test_the_password_reset_mail_is_rendered_in_both_languages(): void
    {
        $mail = new PasswordResetMail('Siti', '049213', new DateTimeImmutable('2026-10-03 10:15:00'), new DateTimeImmutable('2026-10-03 10:00:00'));

        App::setLocale('en');
        $this->assertStringContainsString('set a new password', $mail->render());

        App::setLocale('id');
        $html = $mail->render();
        $this->assertStringContainsString('kata sandi baru', $html);
        $this->assertStringContainsString('049213', $html);
    }

    public function test_queued_mails_survive_the_queue_and_are_delivered_in_the_recipients_language(): void
    {
        $sender = $this->app->make(VerificationCodeSender::class);
        $expiresAt = new DateTimeImmutable('+15 minutes');

        $sender->sendEmailVerification('siti@example.test', 'Siti', 'id', '049213', $expiresAt);
        $sender->sendPasswordReset('budi@example.test', 'Budi', 'en', '123456', $expiresAt);

        $messages = $this->app->make('mail.manager')->mailer()->getSymfonyTransport()->messages();

        $this->assertCount(2, $messages);
        $first = $messages[0]->getOriginalMessage();
        $second = $messages[1]->getOriginalMessage();
        $this->assertSame('Kode verifikasi Anda', $first->getSubject());
        $this->assertStringContainsString('049213', $first->getHtmlBody());
        $this->assertStringContainsString('Halo Siti,', $first->getHtmlBody());
        $this->assertSame('budi@example.test', $second->getTo()[0]->getAddress());
        $this->assertSame('Your password reset code', $second->getSubject());
        $this->assertStringContainsString('123456', $second->getHtmlBody());
    }

    public function test_the_sender_queues_the_reset_mail_on_the_critical_queue(): void
    {
        Mail::fake();

        $this->app->make(VerificationCodeSender::class)
            ->sendPasswordReset('siti@example.test', 'Siti', 'id', '049213', new DateTimeImmutable('2026-10-03 10:15:00'));

        Mail::assertQueued(
            PasswordResetMail::class,
            fn (PasswordResetMail $mail) => $mail->hasTo('siti@example.test') && $mail->queue === 'critical' && $mail->locale === 'id',
        );
    }

    public function test_the_sender_queues_the_mail_on_the_critical_queue_in_the_recipients_locale(): void
    {
        Mail::fake();

        $this->app->make(VerificationCodeSender::class)
            ->sendEmailVerification('siti@example.test', 'Siti', 'en', '049213', new DateTimeImmutable('2026-10-03 10:15:00'));

        Mail::assertQueued(
            EmailVerificationMail::class,
            fn (EmailVerificationMail $mail) => $mail->hasTo('siti@example.test') && $mail->queue === 'critical' && $mail->locale === 'en',
        );
    }
}
