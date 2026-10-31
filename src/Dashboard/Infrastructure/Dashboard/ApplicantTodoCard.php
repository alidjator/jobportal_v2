<?php

namespace Src\Dashboard\Infrastructure\Dashboard;

use Src\Dashboard\Application\ApplicantTaskView;
use Src\Dashboard\Application\GetApplicantTasks;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;
use Src\Shared\Application\Dashboard\DashboardWindow;

/** "Perlu tindakan": the first items of the to-do list of the job seeker and how many there are in all (the full list is `GET me/tasks`). */
final readonly class ApplicantTodoCard implements DashboardCardProvider
{
    private const int LIMIT = 5;

    public function __construct(private GetApplicantTasks $tasks) {}

    public function key(): string
    {
        return 'todo';
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
        $list = $this->tasks->forUser($scope->userId);

        return [
            'total' => $list['total'],
            'items' => array_map(static fn (ApplicantTaskView $task): array => [
                'key' => $task->key,
                'subject_id' => $task->subjectId,
                'application_id' => $task->applicationId,
                'job_id' => $task->jobId,
                'job_title' => $task->jobTitle,
                'company_name' => $task->companyName,
                'due_at' => $task->dueAt === null ? null : DashboardWindow::iso($task->dueAt->format('Y-m-d H:i:s')),
            ], array_slice($list['items'], 0, self::LIMIT)),
        ];
    }
}
