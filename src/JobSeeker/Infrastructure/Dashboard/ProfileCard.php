<?php

namespace Src\JobSeeker\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;

/**
 * How complete the profile is and which sections are still empty, and how many companies looked at it in the last 30 days. Eight sections count, 12.5 percent each.
 * The optional personal details (religion, gender, age, marital status, nationality) and the photo never count, so nobody is pushed to fill them in.
 */
final readonly class ProfileCard implements DashboardCardProvider
{
    /** @var list<string> */
    public const array SECTIONS = ['headline_summary', 'contact', 'location', 'experience', 'education', 'skills', 'languages', 'resume'];

    /** Table of a list section => the section. */
    private const array LISTS = [
        'jobseeker_experiences' => 'experience',
        'jobseeker_educations' => 'education',
        'jobseeker_skills' => 'skills',
        'jobseeker_languages' => 'languages',
        'jobseeker_resumes' => 'resume',
    ];

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function key(): string
    {
        return 'profile';
    }

    public function audiences(): array
    {
        return [DashboardAudience::JobSeeker];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function build(DashboardScope $scope): array
    {
        $profile = $this->connection->table('jobseeker_profiles')->where('user_id', $scope->userId)->first(['headline', 'summary', 'phone', 'province_id', 'city_id']);

        $filled = [
            'headline_summary' => $profile !== null && $this->text($profile->headline) && $this->text($profile->summary),
            'contact' => $profile !== null && $this->text($profile->phone),
            'location' => $profile !== null && $profile->province_id !== null && $profile->city_id !== null,
        ];

        foreach (self::LISTS as $table => $section) {
            $filled[$section] = $this->connection->table($table)->where('user_id', $scope->userId)->exists();
        }

        $empty = array_values(array_filter(self::SECTIONS, fn (string $section): bool => ! $filled[$section]));
        $window = DashboardWindow::at($this->clock->now());

        return [
            'completeness_percent' => (int) round((count(self::SECTIONS) - count($empty)) / count(self::SECTIONS) * 100, 0),
            'empty_sections' => $empty,
            'viewed_by_companies' => (int) $this->connection->table('jobseeker_profile_views')
                ->where('user_id', $scope->userId)
                ->where('day', '>=', $window->from)
                ->distinct()
                ->count('company_id'),
        ];
    }

    private function text(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
