<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\JobAlert\Application\NotifyOfLiveJob;

/**
 * The application fixtures (a verified job seeker with a token, a company with members) plus helpers to create job alerts through the API and to put jobs live
 * without the API. Time is fixed at 2026-10-05 09:00 UTC (a Monday); the token of each account lasts a day.
 */
trait BuildsJobAlertFixtures
{
    use BuildsApplicationFixtures;

    protected int $roleId;

    protected function buildJobAlertFixtures(): void
    {
        $this->buildApplicationFixtures();
        $this->roleId = $this->referenceId('reference_job_roles', 'backend-developer');
    }

    protected function tearDownJobAlertFixtures(): void
    {
        $this->tearDownApplicationFixtures();
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    protected function alertBody(array $override = []): array
    {
        return ['name' => 'Backend jobs', 'frequency' => 'immediate', 'criteria' => ['role_id' => [$this->roleId]], ...$override];
    }

    /** @param  array<string, mixed>  $override */
    protected function createAlert(array $override = [], ?string $token = null): TestResponse
    {
        return $this->api('POST', '/me/job-alerts', $this->alertBody($override), $token ?? $this->seekerToken);
    }

    /** @param  array<string, mixed>  $override */
    protected function alertId(array $override = [], ?string $token = null): int
    {
        return (int) $this->createAlert($override, $token)->assertCreated()->json('data.id');
    }

    /**
     * A live job of the backend role, inserted directly, first published now.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function liveJobFor(string $title = 'Backend Developer', array $attributes = []): int
    {
        return $this->insertJob($title, 'active', [$this->adminId], attributes: ['deadline' => '2026-12-31', 'published_at' => now(), 'role_id' => $this->roleId, ...$attributes]);
    }

    /**
     * A live job as above, followed by what the "job went live" event does.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function goLive(string $title = 'Backend Developer', array $attributes = []): int
    {
        $id = $this->liveJobFor($title, $attributes);
        $this->app->make(NotifyOfLiveJob::class)->execute($id);

        return $id;
    }

    /** @return list<array{type: string, data: array<string, mixed>}> */
    protected function alertNotifications(?int $userId = null): array
    {
        return array_map(
            fn (object $row): array => ['type' => (string) $row->type, 'data' => json_decode((string) $row->data, true)],
            DB::table('notifications')->where('notifiable_type', 'user')->where('notifiable_id', $userId ?? $this->seekerId)->where('type', 'job_alert.matches')->orderBy('created_at')->orderBy('id')->get()->all(),
        );
    }
}
