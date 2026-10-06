<?php

namespace Src\ReferenceData\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Src\Shared\Application\Clock;
use Src\Shared\Application\References\TagRegistry;

final readonly class DatabaseTagRegistry implements TagRegistry
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const int MAX_SLUG_LENGTH = 150;

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function idsFor(array $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            $slug = $this->slug($name);
            $id = $this->connection->table('reference_tags')->where('slug', $slug)->value('id');

            if ($id === null) {
                // Two requests may create the same tag at the same moment: the unique slug lets one win and the other reads the winner.
                $time = $this->clock->now()->format(self::TIME_FORMAT);
                $this->connection->table('reference_tags')->insertOrIgnore([
                    'name' => json_encode(['id' => $name, 'en' => $name], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'slug' => $slug,
                    'sort_order' => 0,
                    'is_active' => true,
                    'created_at' => $time,
                    'updated_at' => $time,
                ]);
                $id = $this->connection->table('reference_tags')->where('slug', $slug)->value('id');
            }

            $ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    public function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach ($this->connection->table('reference_tags')->whereIn('id', $ids)->get(['id', 'name']) as $row) {
            /** @var array<string, string> $name */
            $name = json_decode((string) $row->name, true, flags: JSON_THROW_ON_ERROR);
            $names[(int) $row->id] = $name['en'] ?? $name['id'] ?? '';
        }

        return $names;
    }

    private function slug(string $name): string
    {
        $slug = Str::slug(str_replace(['+', '#'], ['-plus', '-sharp'], $name));

        return rtrim(mb_substr($slug, 0, self::MAX_SLUG_LENGTH), '-');
    }
}
