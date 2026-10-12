<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Src\Recruitment\Domain\InterviewKit;
use Src\Recruitment\Domain\InterviewKitRepository;
use Src\Recruitment\Domain\KitQuestion;
use Src\Shared\Domain\Exceptions\StateConflict;
use stdClass;

final readonly class DatabaseInterviewKitRepository implements InterviewKitRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $kitId): ?InterviewKit
    {
        $row = $this->connection->table('recruitment_interview_kits')->where('id', $kitId)->first();

        return $row === null ? null : $this->toKits([$row])[0];
    }

    public function forCompany(int $companyId): array
    {
        $rows = $this->connection->table('recruitment_interview_kits')->where('company_id', $companyId)->orderByRaw('lower(name)')->orderBy('id')->get()->all();

        return $this->toKits($rows);
    }

    public function countForCompany(int $companyId): int
    {
        return $this->connection->table('recruitment_interview_kits')->where('company_id', $companyId)->count();
    }

    public function add(InterviewKit $kit): int
    {
        try {
            $id = (int) $this->connection->table('recruitment_interview_kits')->insertGetId([
                'company_id' => $kit->companyId,
                'name' => $kit->name,
                'created_by' => $kit->createdBy,
                'updated_by' => $kit->updatedBy,
                'created_at' => $this->format($kit->createdAt),
                'updated_at' => $this->format($kit->updatedAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw self::nameTaken();
        }

        $this->insertQuestions($id, $kit->questions);

        return $id;
    }

    public function replace(InterviewKit $kit): void
    {
        try {
            $this->connection->table('recruitment_interview_kits')->where('id', $kit->id)->update([
                'name' => $kit->name,
                'updated_by' => $kit->updatedBy,
                'updated_at' => $this->format($kit->updatedAt),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw self::nameTaken();
        }

        $this->connection->table('recruitment_interview_kit_questions')->where('kit_id', $kit->id)->delete();
        $this->insertQuestions((int) $kit->id, $kit->questions);
    }

    public function delete(int $kitId): void
    {
        $this->connection->table('recruitment_interview_kits')->where('id', $kitId)->delete();
    }

    /** @param  list<KitQuestion>  $questions */
    private function insertQuestions(int $kitId, array $questions): void
    {
        $this->connection->table('recruitment_interview_kit_questions')->insert(array_map(
            static fn (KitQuestion $question, int $index): array => ['kit_id' => $kitId, 'position' => $index + 1, 'text' => $question->text, 'guidance' => $question->guidance, 'weight' => $question->weight],
            $questions,
            array_keys($questions),
        ));
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<InterviewKit>
     */
    private function toKits(array $rows): array
    {
        $questions = [];

        $questionRows = $this->connection->table('recruitment_interview_kit_questions')
            ->whereIn('kit_id', array_map(static fn (stdClass $row): int => (int) $row->id, $rows))
            ->orderBy('kit_id')
            ->orderBy('position')
            ->get();

        foreach ($questionRows as $question) {
            $questions[(int) $question->kit_id][] = new KitQuestion((string) $question->text, $question->guidance === null ? null : (string) $question->guidance, (int) $question->weight);
        }

        return array_map(fn (stdClass $row): InterviewKit => new InterviewKit(
            (int) $row->company_id,
            (string) $row->name,
            $questions[(int) $row->id] ?? [],
            (int) $row->created_by,
            (int) $row->updated_by,
            $this->time((string) $row->created_at),
            $this->time((string) $row->updated_at),
            (int) $row->id,
        ), $rows);
    }

    private static function nameTaken(): StateConflict
    {
        return new StateConflict('Another kit of this company already has this name.', 'kit-name-taken');
    }

    private function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::TIME_FORMAT);
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
