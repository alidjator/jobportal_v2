<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\ApplicationNote;
use Src\Recruitment\Domain\ApplicationNoteRepository;
use stdClass;

final readonly class DatabaseApplicationNoteRepository implements ApplicationNoteRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $applicationId, int $noteId): ?ApplicationNote
    {
        $row = $this->connection->table('recruitment_application_notes')->where('application_id', $applicationId)->where('id', $noteId)->first();

        return $row === null ? null : $this->toNote($row);
    }

    public function forApplication(int $applicationId): array
    {
        return $this->connection->table('recruitment_application_notes')
            ->where('application_id', $applicationId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (stdClass $row): ApplicationNote => $this->toNote($row))
            ->all();
    }

    public function add(ApplicationNote $note): ApplicationNote
    {
        $at = $this->format($note->createdAt);

        $id = (int) $this->connection->table('recruitment_application_notes')->insertGetId([
            'application_id' => $note->applicationId,
            'author_id' => $note->authorId,
            'body' => $note->body,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return new ApplicationNote($note->applicationId, $note->authorId, $note->body, $note->createdAt, null, $id);
    }

    public function update(ApplicationNote $note): void
    {
        $at = $note->editedAt === null ? null : $this->format($note->editedAt);

        $this->connection->table('recruitment_application_notes')->where('id', $note->id)->update([
            'body' => $note->body,
            'edited_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function delete(int $noteId): void
    {
        $this->connection->table('recruitment_application_notes')->where('id', $noteId)->delete();
    }

    private function toNote(stdClass $row): ApplicationNote
    {
        return new ApplicationNote(
            (int) $row->application_id,
            (int) $row->author_id,
            (string) $row->body,
            $this->time((string) $row->created_at),
            $row->edited_at === null ? null : $this->time((string) $row->edited_at),
            (int) $row->id,
        );
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
