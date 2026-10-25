<?php

namespace Src\Assessment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\Section;
use Src\Assessment\Domain\SectionFormat;
use Src\Assessment\Domain\SectionRepository;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseSectionRepository implements SectionRepository
{
    private const string TABLE = 'assessment_sections';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function ofTest(int $testId): array
    {
        return array_map(
            fn (stdClass $row): Section => $this->toSection($row),
            $this->connection->table(self::TABLE)->where('test_id', $testId)->orderBy('position')->orderBy('id')->get()->all(),
        );
    }

    public function totalsOf(array $testIds): array
    {
        if ($testIds === []) {
            return [];
        }

        $totals = [];

        foreach ($this->connection->table(self::TABLE)->whereIn('test_id', $testIds)->groupBy('test_id')->selectRaw('test_id, count(*) as sections, coalesce(sum(duration_seconds), 0) as seconds')->get() as $row) {
            $totals[(int) $row->test_id] = ['sections' => (int) $row->sections, 'seconds' => (int) $row->seconds];
        }

        return $totals;
    }

    public function find(int $id): ?Section
    {
        $row = $this->connection->table(self::TABLE)->where('id', $id)->first();

        return $row === null ? null : $this->toSection($row);
    }

    public function add(Section $section): Section
    {
        $now = $this->clock->now();
        $position = (int) $this->connection->table(self::TABLE)->where('test_id', $section->testId)->max('position') + 1;

        $id = $this->connection->table(self::TABLE)->insertGetId($this->values($section) + [
            'test_id' => $section->testId,
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find((int) $id) ?? throw new \LogicException('The new section was not found.');
    }

    public function update(Section $section): void
    {
        $this->connection->table(self::TABLE)->where('id', $section->id)->update($this->values($section) + ['updated_at' => $this->clock->now()]);
    }

    public function delete(int $id): void
    {
        $section = $this->connection->table(self::TABLE)->where('id', $id)->first();

        if ($section === null) {
            return;
        }

        $this->connection->table(self::TABLE)->where('id', $id)->delete();
        $this->connection->table(self::TABLE)->where('test_id', $section->test_id)->where('position', '>', $section->position)->decrement('position');
    }

    public function reorder(int $testId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            $this->connection->table(self::TABLE)->where('test_id', $testId)->where('id', $id)->update(['position' => $index + 1]);
        }
    }

    /** @return array<string, mixed> */
    private function values(Section $section): array
    {
        return [
            'title' => $section->title,
            'instructions' => $section->instructions,
            'format' => $section->format->value,
            'duration_seconds' => $section->durationSeconds,
            'allow_skip' => $section->allowSkip,
            'allow_back' => $section->allowBack,
            'allow_jump' => $section->allowJump,
            'draw_count' => $section->drawCount,
            'settings' => $section->settings === [] ? null : json_encode($section->settings, JSON_THROW_ON_ERROR),
        ];
    }

    private function toSection(stdClass $row): Section
    {
        /** @var array<string, int> $settings */
        $settings = $row->settings === null ? [] : json_decode((string) $row->settings, true, 512, JSON_THROW_ON_ERROR);

        return new Section(
            (int) $row->id,
            (int) $row->test_id,
            (int) $row->position,
            (string) $row->title,
            $row->instructions,
            SectionFormat::from($row->format),
            (int) $row->duration_seconds,
            (bool) $row->allow_skip,
            (bool) $row->allow_back,
            (bool) $row->allow_jump,
            $row->draw_count === null ? null : (int) $row->draw_count,
            $settings,
        );
    }
}
