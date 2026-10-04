<?php

namespace Src\Content\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\UniqueConstraintViolationException;
use Src\Content\Domain\LegalDocument;
use Src\Content\Domain\LegalDocumentRepository;
use Src\Content\Domain\LegalDocumentStatus;
use Src\Content\Domain\LegalDocumentType;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\TranslatedText;

final class EloquentLegalDocumentRepository implements LegalDocumentRepository
{
    public function nextVersion(LegalDocumentType $type): int
    {
        return ((int) LegalDocumentModel::query()->where('type', $type->value)->max('version')) + 1;
    }

    public function findById(int $id): ?LegalDocument
    {
        $model = LegalDocumentModel::query()->find($id);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findPublished(LegalDocumentType $type): ?LegalDocument
    {
        $model = LegalDocumentModel::query()
            ->where('type', $type->value)
            ->where('status', LegalDocumentStatus::Published->value)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function findVersion(LegalDocumentType $type, int $version): ?LegalDocument
    {
        $model = LegalDocumentModel::query()->where('type', $type->value)->where('version', $version)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function list(?LegalDocumentType $type): array
    {
        return LegalDocumentModel::query()
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->orderBy('type')
            ->orderByDesc('version')
            ->get()
            ->map(fn (LegalDocumentModel $model): LegalDocument => $this->toDomain($model))
            ->values()
            ->all();
    }

    public function lockType(LegalDocumentType $type): void
    {
        LegalDocumentModel::query()->where('type', $type->value)->lockForUpdate()->get(['id']);
    }

    public function save(LegalDocument $document): LegalDocument
    {
        $model = $document->id === null ? new LegalDocumentModel : LegalDocumentModel::query()->findOrFail($document->id);

        $model->forceFill([
            'type' => $document->type->value,
            'version' => $document->version,
            'status' => $document->status()->value,
            'published_at' => $document->publishedAt(),
            'created_by_admin_id' => $document->createdByAdminId,
        ]);
        $model->setTranslations('title', $document->title()->toArray());
        $model->setTranslations('body', $document->body()->toArray());

        try {
            $model->save();
        } catch (UniqueConstraintViolationException) {
            throw new StateConflict('Another version with this number was created at the same time. Try again.', 'legal-document-version-taken');
        }

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        LegalDocumentModel::query()->whereKey($id)->delete();
    }

    private function toDomain(LegalDocumentModel $model): LegalDocument
    {
        return new LegalDocument(
            $model->id,
            LegalDocumentType::from($model->type),
            $model->version,
            TranslatedText::fromArray($model->getTranslations('title')),
            TranslatedText::fromArray($model->getTranslations('body')),
            LegalDocumentStatus::from($model->status),
            $model->published_at?->toDateTimeImmutable(),
            $model->created_by_admin_id,
        );
    }
}
