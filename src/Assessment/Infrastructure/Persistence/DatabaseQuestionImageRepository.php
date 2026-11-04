<?php

namespace Src\Assessment\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Assessment\Domain\QuestionImage;
use Src\Assessment\Domain\QuestionImageRepository;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseQuestionImageRepository implements QuestionImageRepository
{
    private const string TABLE = 'assessment_question_images';

    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private Clock $clock) {}

    public function create(QuestionImage $image): QuestionImage
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);
        $id = (int) $this->connection->table(self::TABLE)->insertGetId([
            'test_id' => $image->testId, 'question_id' => $image->questionId, 'option_position' => $image->optionPosition, 'position' => $image->position, 'path' => $image->path,
            'content_type' => $image->contentType, 'size_bytes' => $image->sizeBytes, 'width' => $image->width, 'height' => $image->height, 'alt' => $image->alt, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $this->find($id) ?? throw new \LogicException('The image was just stored.');
    }

    public function find(int $id): ?QuestionImage
    {
        $row = $this->connection->table(self::TABLE)->where('id', $id)->first();

        return $row === null ? null : $this->toImage($row);
    }

    public function manyById(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $images = [];

        foreach ($this->connection->table(self::TABLE)->whereIn('id', $ids)->get() as $row) {
            $images[(int) $row->id] = $this->toImage($row);
        }

        return $images;
    }

    public function ofQuestion(int $questionId): array
    {
        return $this->rows($this->connection->table(self::TABLE)->where('question_id', $questionId));
    }

    public function ofQuestions(array $questionIds): array
    {
        return $questionIds === [] ? [] : $this->rows($this->connection->table(self::TABLE)->whereIn('question_id', $questionIds));
    }

    public function ofTest(int $testId): array
    {
        return $this->rows($this->connection->table(self::TABLE)->where('test_id', $testId));
    }

    public function ofSection(int $sectionId): array
    {
        return $this->rows($this->connection->table(self::TABLE)->whereIn('question_id', $this->connection->table('assessment_questions')->where('section_id', $sectionId)->select('id')));
    }

    public function detachFrom(int $questionId): void
    {
        $this->connection->table(self::TABLE)->where('question_id', $questionId)->update(['question_id' => null, 'option_position' => null, 'position' => 0]);
    }

    public function attach(int $imageId, int $questionId, ?int $optionPosition, int $position): void
    {
        $this->connection->table(self::TABLE)->where('id', $imageId)->update(['question_id' => $questionId, 'option_position' => $optionPosition, 'position' => $position, 'updated_at' => $this->clock->now()->format(self::TIME_FORMAT)]);
    }

    public function delete(array $ids): void
    {
        if ($ids !== []) {
            $this->connection->table(self::TABLE)->whereIn('id', $ids)->delete();
        }
    }

    public function unattachedBefore(DateTimeImmutable $moment, int $limit): array
    {
        return $this->rows($this->connection->table(self::TABLE)->whereNull('question_id')->where('created_at', '<', $moment->format(self::TIME_FORMAT))->limit($limit));
    }

    /** @return list<QuestionImage> */
    private function rows(Builder $query): array
    {
        return array_map($this->toImage(...), $query->orderByRaw('option_position is not null')->orderBy('option_position')->orderBy('position')->orderBy('id')->get()->all());
    }

    private function toImage(stdClass $row): QuestionImage
    {
        return new QuestionImage(
            (int) $row->id, (int) $row->test_id, $row->question_id === null ? null : (int) $row->question_id, $row->option_position === null ? null : (int) $row->option_position, (int) $row->position,
            $row->path, $row->content_type, (int) $row->size_bytes, (int) $row->width, (int) $row->height, $row->alt, new DateTimeImmutable($row->created_at),
        );
    }
}
