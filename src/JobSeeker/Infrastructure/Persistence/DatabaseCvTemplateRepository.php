<?php

namespace Src\JobSeeker\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\JobSeeker\Domain\CvTemplate;
use Src\JobSeeker\Domain\CvTemplateRepository;
use Src\JobSeeker\Domain\CvTemplateState;

final readonly class DatabaseCvTemplateRepository implements CvTemplateRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function states(): array
    {
        $states = [];

        foreach ($this->connection->table('jobseeker_cv_templates')->get() as $row) {
            $template = CvTemplate::tryFrom((string) $row->key);

            // A row of a template that was removed from the code is ignored.
            if ($template !== null) {
                $states[$template->value] = new CvTemplateState($template, (bool) $row->enabled, (bool) $row->is_default);
            }
        }

        return $states;
    }

    public function save(CvTemplateState $state): void
    {
        if ($state->isDefault) {
            $this->connection->table('jobseeker_cv_templates')->where('key', '<>', $state->template->value)->update(['is_default' => false]);
        }

        $this->connection->table('jobseeker_cv_templates')->upsert(
            [['key' => $state->template->value, 'enabled' => $state->enabled, 'is_default' => $state->isDefault, 'updated_at' => now()]],
            ['key'],
            ['enabled', 'is_default', 'updated_at'],
        );
    }

    public function lock(): void
    {
        $this->connection->table('jobseeker_cv_templates')->lockForUpdate()->pluck('key');
    }
}
