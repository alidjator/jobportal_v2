<?php

namespace Tests\Unit\Recruitment\JobManagement;

use PHPUnit\Framework\Attributes\DataProvider;
use Src\Recruitment\Domain\JobManagement\ApplyChannel;
use Src\Recruitment\Domain\JobManagement\InvalidJobData;
use Src\Recruitment\Domain\JobManagement\JobSlug;
use Src\Recruitment\Domain\JobManagement\JobStatus;
use Src\Recruitment\Domain\JobManagement\JobStatusPolicy;
use Src\Recruitment\Domain\JobManagement\PlanQuota;
use Src\Recruitment\Domain\JobManagement\PromotionBadge;
use Src\Recruitment\Domain\JobManagement\SalaryRange;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Tests\TestCase;

class DomainRulesTest extends TestCase
{
    public function test_salary_range_accepts_valid_range(): void
    {
        $range = SalaryRange::of('1000', 2500.7);

        $this->assertSame(1000, $range->minAsInt());
        $this->assertSame(2500, $range->maxAsInt());
        $this->assertSame(0, SalaryRange::of(0, 0)->maxAsInt());
    }

    #[DataProvider('invalidSalaries')]
    public function test_salary_range_rejects_invalid(mixed $min, mixed $max, string $field): void
    {
        try {
            SalaryRange::of($min, $max);
            $this->fail('expected InvalidJobData');
        } catch (InvalidJobData $e) {
            $this->assertSame($field, $e->field);
        }
    }

    /** @return array<string, array{mixed, mixed, string}> */
    public static function invalidSalaries(): array
    {
        return [
            'min greater than max' => [10, 5, 'max_salary'],
            'negative min' => [-1, 5, 'min_salary'],
            'min not numeric' => ['abc', 5, 'min_salary'],
            'max not numeric' => [1, 'abc', 'max_salary'],
        ];
    }

    public function test_apply_channel_requires_target_for_email_and_url(): void
    {
        $this->assertSame('app', ApplyChannel::of('app')->type);
        $this->assertSame('hr@example.test', ApplyChannel::of('email', 'hr@example.test')->email);
        $this->assertSame('https://a.test/x', ApplyChannel::of('custom_url', null, 'https://a.test/x')->url);

        foreach ([['email', null, null, 'apply_email'], ['email', 'bad', null, 'apply_email'], ['custom_url', null, 'nope', 'apply_url'], ['fax', null, null, 'apply_on']] as [$type, $email, $url, $field]) {
            try {
                ApplyChannel::of($type, $email, $url);
                $this->fail("expected failure for {$type}");
            } catch (InvalidJobData $e) {
                $this->assertSame($field, $e->field);
            }
        }
    }

    /** @return array<string, array{JobStatus, JobStatus, bool, bool, bool}> */
    public static function transitions(): array
    {
        $p = JobStatus::Pending;
        $a = JobStatus::Active;
        $e = JobStatus::Expired;

        return [
            'same status' => [$p, $p, false, true, true],
            'close active' => [$a, $e, false, false, true],
            'close pending' => [$p, $e, false, false, true],
            'reopen before deadline' => [$e, $a, false, false, true],
            'reopen after deadline' => [$e, $a, false, true, false],
            'pending to active needs approval' => [$p, $a, false, false, false],
            'pending to active when auto approved' => [$p, $a, true, false, true],
            'never back to pending' => [$a, $p, true, false, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_status_policy(JobStatus $from, JobStatus $to, bool $auto, bool $passed, bool $allowed): void
    {
        $this->assertSame($allowed, JobStatusPolicy::canTransition($from, $to, $auto, $passed));
    }

    public function test_status_values_match_db_enum_and_deadline_status(): void
    {
        $this->assertSame(['pending', 'active', 'expired'], JobStatus::values());
        $this->assertSame(JobStatus::Active, JobStatus::forDeadline(true));
        $this->assertSame(JobStatus::Expired, JobStatus::forDeadline(false));
    }

    public function test_plan_quota_consumption(): void
    {
        $quota = new PlanQuota(1, 1, 1, 0);

        $quota->consumeJob();
        $this->assertSame(0, $quota->jobLimit());
        $quota->consumeBadge(PromotionBadge::Featured);
        $this->assertSame(0, $quota->featuredLimit());

        foreach ([fn () => $quota->consumeJob(), fn () => $quota->consumeBadge(PromotionBadge::Featured), fn () => $quota->consumeBadge(PromotionBadge::Highlight)] as $attempt) {
            try {
                $attempt();
                $this->fail('expected BusinessRuleViolation');
            } catch (BusinessRuleViolation) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_inherit_badges_only_keeps_those_with_quota(): void
    {
        $quota = new PlanQuota(1, 5, 1, 0);

        $this->assertSame([true, false], $quota->inheritBadges(true, true));
        $this->assertSame(0, $quota->featuredLimit());
        $this->assertSame([false, false], $quota->inheritBadges(true, false));
    }

    public function test_slug_gets_suffix_only_when_taken(): void
    {
        $this->assertSame('hello-world', JobSlug::make('Hello World', fn () => false));

        $taken = JobSlug::make('Hello World', fn (string $slug) => $slug === 'hello-world');
        $this->assertMatchesRegularExpression('/^hello-world-\d+-[0-9a-f]+$/', $taken);
    }
}
