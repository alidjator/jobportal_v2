<?php

namespace Src\JobSeeker\Application\Candidates;

use DateTimeImmutable;
use DateTimeZone;
use Src\JobSeeker\Application\JakartaToday;
use Src\JobSeeker\Application\JobSeekerSettings;
use Src\JobSeeker\Domain\ProfileViewKind;
use Src\JobSeeker\Domain\ProfileViewRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** Writes the view log. Opening profiles is limited per member and Jakarta day; opening the same candidate again the same day is free. */
final readonly class ProfileViews
{
    public const string LIMIT_CODE = 'candidate-view-limit';

    public function __construct(
        private ProfileViewRepository $views,
        private Settings $settings,
        private JakartaToday $today,
        private Clock $clock,
    ) {}

    /** @throws TooManyAttempts `candidate-view-limit` */
    public function profileOpened(CompanyViewer $viewer, int $userId): void
    {
        $day = $this->today->date();

        if (! $this->views->exists($viewer->memberId, $userId, ProfileViewKind::Profile, $day)
            && $this->views->countOfMember($viewer->memberId, ProfileViewKind::Profile, $day) >= $this->settings->integer(JobSeekerSettings::CANDIDATE_VIEWS_PER_DAY)) {
            throw new TooManyAttempts('You opened as many candidate profiles as you may today. Try again tomorrow.', $this->secondsUntilTomorrow(), self::LIMIT_CODE);
        }

        $this->views->record($userId, $viewer->companyId, $viewer->memberId, ProfileViewKind::Profile, $day, $this->clock->now());
    }

    public function resumeDownloaded(CompanyViewer $viewer, int $userId): void
    {
        $this->views->record($userId, $viewer->companyId, $viewer->memberId, ProfileViewKind::Resume, $this->today->date(), $this->clock->now());
    }

    private function secondsUntilTomorrow(): int
    {
        $zone = new DateTimeZone('Asia/Jakarta');
        $now = $this->clock->now()->setTimezone($zone);
        $midnight = (new DateTimeImmutable($now->format('Y-m-d').' 00:00:00', $zone))->modify('+1 day');

        return max(1, $midnight->getTimestamp() - $now->getTimestamp());
    }
}
